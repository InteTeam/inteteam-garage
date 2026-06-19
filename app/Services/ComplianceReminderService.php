<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ComplianceRecipient;
use App\Models\ComplianceRecord;
use App\Models\ComplianceReminderSent;
use App\Models\Garage;
use App\Models\Mechanic;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

final class ComplianceReminderService
{
    /** MySQL + Postgres "integrity constraint violation" SQLSTATEs — anything else re-throws. */
    private const DEDUP_SQLSTATE_CODES = ['23000', '23505'];

    public function __construct(
        private readonly CrmApiService $crmApiService,
        private readonly ComplianceReminderCopy $copy,
    ) {}

    /**
     * Iterate every garage with reminders enabled and dispatch any due notifications.
     * Returns metrics keyed by garage id for the console command summary.
     *
     * @return array<string, array{sent: int, skipped: int, errors: int}>
     */
    public function dispatchDue(?Carbon $now = null): array
    {
        $now = $now ?? now();
        $metrics = [];

        $garages = Garage::withoutGlobalScopes()
            ->where('compliance_reminders_enabled', true)
            ->get();

        foreach ($garages as $garage) {
            $metrics[$garage->id] = $this->dispatchForGarage($garage, $now);
        }

        return $metrics;
    }

    /**
     * @return array{sent: int, skipped: int, errors: int}
     */
    private function dispatchForGarage(Garage $garage, Carbon $now): array
    {
        $sent = $skipped = $errors = 0;
        $windows = $garage->compliance_reminders_windows ?: Garage::DEFAULT_REMINDER_WINDOWS;
        $types = $garage->compliance_reminders_types ?: Garage::DEFAULT_REMINDER_TYPES;
        $channel = $garage->compliance_reminders_channel ?: $garage->default_notification_channel;
        $recipient = ComplianceRecipient::from($garage->compliance_reminders_recipient);

        // Window range used to narrow the SQL scan; we still filter per-window exactly.
        $maxWindow = max($windows);
        $minWindow = min($windows);

        $records = $this->latestRecordsForGarage($garage, $types, $now, $minWindow, $maxWindow);

        foreach ($records as $record) {
            /** @var Vehicle $vehicle */
            $vehicle = $record->vehicle;

            $daysUntil = $now->copy()->startOfDay()->diffInDays($record->expires_on->copy()->startOfDay(), false);

            if (! in_array((int) $daysUntil, $windows, true)) {
                $skipped++;

                continue;
            }

            foreach ($this->resolveRecipients($garage, $vehicle, $recipient) as $r) {
                $result = $this->sendIfNotDeduped($garage, $vehicle, $record, $r, (int) $daysUntil, $channel);

                if ($result === 'sent') {
                    $sent++;
                } elseif ($result === 'error') {
                    $errors++;
                } else {
                    $skipped++;
                }
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Latest compliance record per (vehicle, type) for this garage whose expiry falls in our window range.
     *
     * @param  array<int, string>  $types
     * @return iterable<int, ComplianceRecord>
     */
    private function latestRecordsForGarage(Garage $garage, array $types, Carbon $now, int $minWindow, int $maxWindow): iterable
    {
        $from = $now->copy()->startOfDay()->addDays($minWindow);
        $to = $now->copy()->startOfDay()->addDays($maxWindow);

        // Pull all candidate records, then dedupe to latest per (vehicle, type) in PHP.
        // Simpler than a window function and the volume is per-garage-small.
        // whereDate (not whereBetween) avoids SQLite TEXT-vs-DATETIME string comparison surprises.
        $candidates = ComplianceRecord::query()
            ->where('garage_id', $garage->id)
            ->whereIn('type', $types)
            ->whereDate('expires_on', '>=', $from->toDateString())
            ->whereDate('expires_on', '<=', $to->toDateString())
            ->with('vehicle')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $seen = [];
        $latest = [];

        foreach ($candidates as $record) {
            $key = $record->vehicle_id . '|' . $record->type->value;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $latest[] = $record;
        }

        return $latest;
    }

    /**
     * @return array<int, array{type: string, id: string}>
     */
    private function resolveRecipients(Garage $garage, Vehicle $vehicle, ComplianceRecipient $mode): array
    {
        $list = [];

        if ($mode === ComplianceRecipient::CUSTOMER || $mode === ComplianceRecipient::CUSTOMER_AND_MECHANIC) {
            $list[] = ['type' => 'customer', 'id' => $vehicle->crm_customer_id];
        }

        if ($mode === ComplianceRecipient::MECHANIC || $mode === ComplianceRecipient::CUSTOMER_AND_MECHANIC) {
            $admins = Mechanic::withoutGlobalScopes()
                ->where('garage_id', $garage->id)
                ->where('role', Mechanic::ROLE_GARAGE_ADMIN)
                ->where('is_active', true)
                ->with('user')
                ->get();

            foreach ($admins as $admin) {
                $crmUserId = $admin->user?->crm_user_id;

                if (is_string($crmUserId) && $crmUserId !== '') {
                    $list[] = ['type' => 'mechanic', 'id' => $crmUserId];
                }
            }
        }

        return $list;
    }

    /**
     * @param  array{type: string, id: string}  $recipient
     */
    private function sendIfNotDeduped(
        Garage $garage,
        Vehicle $vehicle,
        ComplianceRecord $record,
        array $recipient,
        int $windowDays,
        string $channel,
    ): string {
        try {
            ComplianceReminderSent::create([
                'garage_id' => $garage->id,
                'vehicle_id' => $vehicle->id,
                'compliance_record_id' => $record->id,
                'type' => $record->type->value,
                'channel' => $channel,
                'recipient_type' => $recipient['type'],
                'recipient_id' => $recipient['id'],
                'window_days' => $windowDays,
                'sent_at' => now(),
            ]);
        } catch (QueryException $e) {
            if (in_array($e->getCode(), self::DEDUP_SQLSTATE_CODES, true)) {
                return 'skipped';
            }

            throw $e;
        }

        try {
            $subject = $this->copy->subjectFor($vehicle, $record->type, $windowDays, $garage->locale);
            $body = $this->copy->bodyFor($vehicle, $record, $windowDays, $garage->locale);

            if ($recipient['type'] === 'customer') {
                $this->crmApiService->sendNotification(
                    crmCustomerId: $recipient['id'],
                    channel: $channel,
                    subject: $subject,
                    body: $body,
                    meta: [
                        'compliance_type' => $record->type->value,
                        'window_days' => $windowDays,
                        'expires_on' => $record->expires_on->toDateString(),
                    ],
                );
            } else {
                $this->crmApiService->sendStaffNotification(
                    crmUserId: $recipient['id'],
                    channel: $channel,
                    subject: $subject,
                    body: $body,
                    meta: [
                        'compliance_type' => $record->type->value,
                        'window_days' => $windowDays,
                        'expires_on' => $record->expires_on->toDateString(),
                        'vehicle_id' => $vehicle->id,
                    ],
                );
            }

            return 'sent';
        } catch (\Throwable $e) {
            Log::error('Compliance reminder dispatch failed', [
                'garage_id' => $garage->id,
                'vehicle_id' => $vehicle->id,
                'type' => $record->type->value,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);

            ComplianceReminderSent::query()
                ->where('compliance_record_id', $record->id)
                ->where('window_days', $windowDays)
                ->where('recipient_type', $recipient['type'])
                ->where('recipient_id', $recipient['id'])
                ->update(['error' => mb_substr($e->getMessage(), 0, 1000)]);

            return 'error';
        }
    }
}

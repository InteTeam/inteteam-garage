<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Estimate;
use App\Models\LineItem;
use App\Models\RepairJob;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EstimateService
{
    public function getAll(): Collection
    {
        return Estimate::query()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function create(array $data): Estimate
    {
        return Estimate::create($data);
    }

    /**
     * Creates the next revision of an estimate for the job. The parent job row
     * is locked for the duration so two concurrent POSTs cannot both compute
     * the same `max + 1` revision number and double-insert.
     */
    public function createForJob(RepairJob $job): Estimate
    {
        return DB::transaction(function () use ($job): Estimate {
            RepairJob::query()->whereKey($job->id)->lockForUpdate()->first();

            $nextRevision = (int) ($job->estimates()->max('revision_number') ?? 0) + 1;

            return Estimate::create([
                'garage_id' => $job->garage_id,
                'job_id' => $job->id,
                'revision_number' => $nextRevision,
            ]);
        });
    }

    public function update(Estimate $estimate, array $data): Estimate
    {
        if ($estimate->hasCustomerResponse()) {
            throw new RuntimeException(
                'Cannot modify estimate after customer response. Create a new revision instead.'
            );
        }

        $estimate->update($data);

        return $estimate->fresh();
    }

    public function delete(Estimate $estimate): void
    {
        $estimate->delete();
    }

    public function markSent(
        RepairJob $job,
        Estimate $estimate,
        JobStateMachine $stateMachine,
        CrmNotificationService $notifications,
    ): void {
        DB::transaction(function () use ($job, $estimate, $stateMachine): void {
            $stateMachine->transition($job, RepairJob::STATE_AWAITING_APPROVAL, (string) auth()->id());
            $estimate->update(['sent_at' => now()]);
        });

        $notifications->notifyEstimateSent($job);
    }

    public function guardSendable(Estimate $estimate, string $fromLocale, string $toLocale): void
    {
        if ($fromLocale === $toLocale) {
            return;
        }

        if ($estimate->preview_confirmed_at !== null) {
            return;
        }

        throw new RuntimeException(
            'Cannot send cross-locale estimate without a confirmed translation preview.'
        );
    }

    /**
     * @param  array<int, array{id: string, translated_text: string, llm_raw_text: string}>  $confirmations
     */
    public function confirmTranslation(Estimate $estimate, array $confirmations, string $editorMechanicId): Estimate
    {
        DB::transaction(function () use ($estimate, $confirmations, $editorMechanicId): void {
            foreach ($confirmations as $row) {
                $lineItem = LineItem::withoutGlobalScopes()
                    ->where('estimate_id', $estimate->id)
                    ->where('id', $row['id'])
                    ->firstOrFail();

                $edited = trim($row['translated_text']) !== trim($row['llm_raw_text']);

                $lineItem->update([
                    'translation_confirmed_text' => $row['translated_text'],
                    'translation_llm_raw' => $row['llm_raw_text'],
                    'translation_edited_by_mechanic_id' => $edited ? $editorMechanicId : null,
                ]);
            }

            $estimate->update(['preview_confirmed_at' => now()]);
        });

        return $estimate->fresh();
    }
}

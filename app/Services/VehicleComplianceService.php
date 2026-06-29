<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ComplianceSource;
use App\Enums\ComplianceType;
use App\Models\ComplianceRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Dvla\VehicleEnquiryResult;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class VehicleComplianceService
{
    /**
     * Latest record per compliance type for this vehicle. Used by the
     * "Compliance" tab to show what's currently in force.
     *
     * @return array<string, ComplianceRecord|null>
     */
    public function currentForVehicle(Vehicle $vehicle): array
    {
        $records = ComplianceRecord::query()
            ->where('vehicle_id', $vehicle->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return [
            ComplianceType::MOT->value => $records->firstWhere('type', ComplianceType::MOT),
            ComplianceType::TAX->value => $records->firstWhere('type', ComplianceType::TAX),
            ComplianceType::INSURANCE->value => $records->firstWhere('type', ComplianceType::INSURANCE),
        ];
    }

    /**
     * Full chronological history for the vehicle, newest first.
     *
     * @return Collection<int, ComplianceRecord>
     */
    public function historyForVehicle(Vehicle $vehicle): Collection
    {
        return ComplianceRecord::query()
            ->where('vehicle_id', $vehicle->id)
            ->with('recordedBy:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function record(
        Vehicle $vehicle,
        ComplianceType $type,
        CarbonInterface $expiresOn,
        ?string $note,
        User $user,
        ComplianceSource $source = ComplianceSource::MANUAL,
    ): ComplianceRecord {
        return ComplianceRecord::create([
            'vehicle_id' => $vehicle->id,
            'garage_id' => $vehicle->garage_id,
            'recorded_by_user_id' => $user->id,
            'type' => $type,
            'source' => $source,
            'expires_on' => Carbon::instance($expiresOn)->toDateString(),
            'note' => $note,
        ]);
    }

    /**
     * Apply a DVLA Vehicle Enquiry response: create MOT + Tax records when the dates differ
     * from the latest known value, so we don't spam history with no-op rows.
     *
     * @return array<int, ComplianceRecord> created records (empty if nothing changed)
     */
    public function applyDvlaResult(Vehicle $vehicle, VehicleEnquiryResult $result, ?User $user): array
    {
        $current = $this->currentForVehicle($vehicle);

        // Atomic: a single DVLA refresh inserts both MOT and Tax rows or none.
        // Without the transaction, a mid-loop DB failure (lost connection,
        // constraint hiccup) leaves the vehicle with a half-applied refresh
        // and the next click would not re-attempt the missing row because
        // the first one already changed `current` per type.
        return DB::transaction(function () use ($vehicle, $result, $current, $user): array {
            $created = [];

            foreach ([
                [ComplianceType::MOT, $result->motExpiryDate, $result->motStatus],
                [ComplianceType::TAX, $result->taxDueDate, $result->taxStatus],
            ] as [$type, $expiresOn, $status]) {
                if ($expiresOn === null) {
                    continue;
                }

                $latest = $current[$type->value] ?? null;
                $newDate = Carbon::instance($expiresOn)->toDateString();

                if ($latest !== null && $latest->expires_on->toDateString() === $newDate) {
                    continue;
                }

                $created[] = ComplianceRecord::create([
                    'vehicle_id' => $vehicle->id,
                    'garage_id' => $vehicle->garage_id,
                    'recorded_by_user_id' => $user?->id,
                    'type' => $type,
                    'source' => ComplianceSource::DVLA,
                    'expires_on' => $newDate,
                    'note' => $status !== null ? "DVLA status: {$status}" : null,
                ]);
            }

            return $created;
        });
    }
}

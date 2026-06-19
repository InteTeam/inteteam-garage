<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ComplianceType;
use App\Models\ComplianceRecord;
use App\Models\Vehicle;

/**
 * Subject / body strings for the compliance reminder dispatcher.
 * Lives outside ComplianceReminderService so the service stays focused on dispatch.
 * Locale-keyed (en/pl) for parity with the rest of the garage stack.
 */
final class ComplianceReminderCopy
{
    public function subjectFor(Vehicle $vehicle, ComplianceType $type, int $days, string $locale): string
    {
        $label = $this->typeLabel($type, $locale);

        if ($locale === 'pl') {
            return "{$label} pojazdu {$vehicle->registration} wygasa za {$days} dni";
        }

        return "{$label} for {$vehicle->registration} expires in {$days} days";
    }

    public function bodyFor(Vehicle $vehicle, ComplianceRecord $record, int $days, string $locale): string
    {
        $label = $this->typeLabel($record->type, $locale);
        $date = $record->expires_on->format('d/m/Y');
        $car = trim("{$vehicle->make} {$vehicle->model}") ?: $vehicle->registration;

        if ($locale === 'pl') {
            return "Witamy,\n\n{$label} pojazdu {$car} ({$vehicle->registration}) wygasa {$date} (za {$days} dni).\nProsimy o niezwłoczne odnowienie.";
        }

        return "Hello,\n\nThe {$label} for {$car} ({$vehicle->registration}) expires on {$date} ({$days} days from now).\nPlease arrange renewal at your earliest convenience.";
    }

    private function typeLabel(ComplianceType $type, string $locale): string
    {
        return match ($type) {
            ComplianceType::MOT => 'MOT',
            ComplianceType::TAX => $locale === 'pl' ? 'Podatek drogowy' : 'Road Tax',
            ComplianceType::INSURANCE => $locale === 'pl' ? 'Ubezpieczenie' : 'Insurance',
        };
    }
}

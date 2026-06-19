<?php

declare(strict_types=1);

namespace App\Services\Dvla;

use Illuminate\Support\Carbon;

/**
 * Strongly-typed slice of the DVLA Vehicle Enquiry Service response.
 * Only fields we actually consume — everything else from the API payload is dropped.
 */
final readonly class VehicleEnquiryResult
{
    public function __construct(
        public string $registrationNumber,
        public ?Carbon $motExpiryDate,
        public ?string $motStatus,
        public ?Carbon $taxDueDate,
        public ?string $taxStatus,
        public ?string $make,
        public ?string $colour,
        public ?int $yearOfManufacture,
        public ?string $fuelType,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        return new self(
            registrationNumber: (string) ($payload['registrationNumber'] ?? ''),
            motExpiryDate: self::parseDate($payload['motExpiryDate'] ?? null),
            motStatus: self::nullableString($payload['motStatus'] ?? null),
            taxDueDate: self::parseDate($payload['taxDueDate'] ?? null),
            taxStatus: self::nullableString($payload['taxStatus'] ?? null),
            make: self::nullableString($payload['make'] ?? null),
            colour: self::nullableString($payload['colour'] ?? null),
            yearOfManufacture: isset($payload['yearOfManufacture']) ? (int) $payload['yearOfManufacture'] : null,
            fuelType: self::nullableString($payload['fuelType'] ?? null),
        );
    }

    private static function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}

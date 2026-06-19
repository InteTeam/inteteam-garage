<?php

declare(strict_types=1);

namespace App\Services\Dvla;

final class DvlaException extends \RuntimeException
{
    public const KIND_NOT_CONFIGURED = 'not_configured';

    public const KIND_NOT_FOUND = 'not_found';

    public const KIND_BAD_REQUEST = 'bad_request';

    public const KIND_UNAVAILABLE = 'unavailable';

    private function __construct(string $message, private readonly string $kind)
    {
        parent::__construct($message);
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public static function notConfigured(): self
    {
        return new self(
            'DVLA Vehicle Enquiry Service is not configured (DVLA_VES_API_KEY missing).',
            self::KIND_NOT_CONFIGURED,
        );
    }

    public static function registrationNotFound(string $reg): self
    {
        return new self("DVLA could not find vehicle with registration {$reg}.", self::KIND_NOT_FOUND);
    }

    public static function badRequest(string $reg): self
    {
        return new self("DVLA rejected the registration {$reg} as invalid.", self::KIND_BAD_REQUEST);
    }

    public static function unavailable(int $status, string $body): self
    {
        return new self(
            "DVLA Vehicle Enquiry Service unavailable [{$status}]: " . mb_substr($body, 0, 200),
            self::KIND_UNAVAILABLE,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RepairJob;
use App\Models\SignedPortalToken;
use Illuminate\Support\Str;

final class SignedPortalTokenService
{
    private const TOKEN_EXPIRY_DAYS = 30;

    public function createForJob(RepairJob $job): SignedPortalToken
    {
        $this->revokeExisting($job);

        return SignedPortalToken::create([
            'garage_id' => $job->garage_id,
            'job_id' => $job->id,
            'token' => Str::ulid()->toString(),
            'expires_at' => now()->addDays(self::TOKEN_EXPIRY_DAYS),
            'revoked_at' => null,
        ]);
    }

    public function regenerate(RepairJob $job): SignedPortalToken
    {
        return $this->createForJob($job);
    }

    public function portalUrl(SignedPortalToken $token): string
    {
        return route('portal.show', ['token' => $token->token]);
    }

    private function revokeExisting(RepairJob $job): void
    {
        SignedPortalToken::where('job_id', $job->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}

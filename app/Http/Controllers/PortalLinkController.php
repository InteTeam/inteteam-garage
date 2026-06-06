<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RepairJob;
use App\Models\SignedPortalToken;
use App\Services\SignedPortalTokenService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class PortalLinkController extends Controller
{
    public function __construct(
        private readonly SignedPortalTokenService $tokenService,
    ) {}

    public function show(RepairJob $job): Response
    {
        $this->authorize('view', $job);

        /** @var SignedPortalToken|null $token */
        $token = $job->portalToken;
        $portalUrl = $token ? $this->tokenService->portalUrl($token) : null;

        return Inertia::render('Jobs/PortalLink', [
            'job' => $job,
            'portalUrl' => $portalUrl,
            'token' => $token,
        ]);
    }

    public function regenerate(RepairJob $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $this->tokenService->regenerate($job);

        return back()->with(['alert' => 'The portal link was regenerated.', 'type' => 'success']);
    }
}

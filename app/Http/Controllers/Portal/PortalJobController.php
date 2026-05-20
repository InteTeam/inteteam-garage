<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\RepairJob;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PortalJobController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        /** @var RepairJob $job */
        $job = $request->attributes->get('portal_job');

        $job->load([
            'vehicle',
            'currentEstimate.lineItems',
            'stateTransitions',
            'approvalEvents',
            'notificationPreference',
        ]);

        return Inertia::render('Portal/Job', [
            'job' => $job,
            'token' => $token,
        ]);
    }

    public function timeline(Request $request, string $token): Response
    {
        /** @var RepairJob $job */
        $job = $request->attributes->get('portal_job');

        $job->load(['stateTransitions', 'approvalEvents', 'stages.media']);

        return Inertia::render('Portal/Timeline', [
            'job' => $job,
            'token' => $token,
        ]);
    }
}

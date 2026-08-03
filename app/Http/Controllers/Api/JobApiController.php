<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobStage;
use App\Models\Media;
use App\Models\RepairJob;
use App\Services\GcsService;
use Illuminate\Http\JsonResponse;

/**
 * Machine-to-machine read API for the CRM (and other trusted internal callers).
 *
 * Trust boundary is `AuthenticateGarageApiKey` (`X-Garage-Secret`) — there is no
 * session and no authenticated `User`. The previous `$this->authorize('view', $job)`
 * calls could therefore never succeed: `RepairJobPolicy::view()` takes a non-nullable
 * `User`, so Laravel's Gate denies outright for a null user and BOTH endpoints
 * returned 403 unconditionally. Secret-holder callers are trusted across garages by
 * design (same posture as the CRM's own `internal/*` endpoints), so the check is
 * dropped rather than made nullable — adding a garage filter here would need a
 * garage identifier on the request, which no caller sends.
 */
final class JobApiController extends Controller
{
    public function __construct(
        private readonly GcsService $gcsService,
    ) {}

    public function show(RepairJob $job): JsonResponse
    {
        $job->load([
            'vehicle',
            'currentEstimate.lineItems',
            'stages',
            'approvalEvents',
            'stateTransitions',
        ]);

        return response()->json(['data' => $job]);
    }

    public function media(RepairJob $job): JsonResponse
    {
        $job->load('stages.media');

        $stages = $job->stages->map(function (JobStage $stage) {
            return [
                'id' => $stage->id,
                'name' => $stage->name,
                'media' => $stage->media->map(fn (Media $m) => [
                    'id' => $m->id,
                    'url' => $this->gcsService->signedUrl($m),
                    'mime_type' => $m->mime_type,
                ]),
            ];
        });

        return response()->json(['data' => $stages]);
    }
}

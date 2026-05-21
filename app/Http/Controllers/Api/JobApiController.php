<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobStage;
use App\Models\Media;
use App\Models\RepairJob;
use App\Services\GcsService;
use Illuminate\Http\JsonResponse;

final class JobApiController extends Controller
{
    public function __construct(
        private readonly GcsService $gcsService,
    ) {}

    public function show(RepairJob $job): JsonResponse
    {
        $this->authorize('view', $job);

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
        $this->authorize('view', $job);

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

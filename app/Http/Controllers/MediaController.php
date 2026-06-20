<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\JobStage;
use App\Models\RepairJob;
use App\Services\GcsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MediaController extends Controller
{
    public function __construct(
        private readonly GcsService $gcsService,
    ) {}

    public function store(Request $request, RepairJob $job, JobStage $stage): JsonResponse
    {
        $this->authorize('update', $job);
        $this->ensureStageBelongsToJob($stage, $job);

        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,mp4,mov'],
        ]);

        if ($stage->isLocked()) {
            abort(422, 'This stage is locked and no longer accepts uploads.');
        }

        $media = $this->gcsService->upload(
            file: $request->file('file'),
            job: $job,
            stage: $stage,
            uploadedBy: (string) $request->user()->id,
        );

        return response()->json([
            'id' => $media->id,
            'url' => $this->gcsService->signedUrl($media),
            'mime_type' => $media->mime_type,
            'original_filename' => $media->original_filename,
        ], 201);
    }

    private function ensureStageBelongsToJob(JobStage $stage, RepairJob $job): void
    {
        abort_if($stage->job_id !== $job->id, 404, 'Stage does not belong to this job.');
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\JobStage;
use App\Models\Media;
use App\Models\RepairJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class GcsService
{
    public function upload(
        RepairJob $job,
        JobStage $stage,
        UploadedFile $file,
        string $uploadedBy
    ): Media {
        if ($stage->isLocked()) {
            throw new RuntimeException(
                "Cannot upload to stage [{$stage->name}]: stage is locked."
            );
        }

        $timestamp = now()->getTimestamp();
        $filename = $file->getClientOriginalName();
        $path = "{$job->id}/{$stage->name}/{$timestamp}_{$filename}";

        Storage::disk('gcs')->put($path, $file->getContent());

        return Media::create([
            'garage_id' => $job->garage_id,
            'job_id' => $job->id,
            'job_stage_id' => $stage->id,
            'gcs_path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'original_filename' => $filename,
            'uploaded_by' => $uploadedBy,
            'uploaded_at' => now(),
        ]);
    }

    public function signedUrl(Media $media): string
    {
        $expiryMinutes = (int) config('services.gcs.signed_url_expiry_minutes', 30);

        return Storage::disk('gcs')->temporaryUrl(
            $media->gcs_path,
            now()->addMinutes($expiryMinutes)
        );
    }

    public function signedUrls(iterable $mediaItems): array
    {
        $urls = [];

        foreach ($mediaItems as $media) {
            $urls[$media->id] = $this->signedUrl($media);
        }

        return $urls;
    }
}

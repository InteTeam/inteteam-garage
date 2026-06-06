<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\Garage;
use App\Models\JobStage;
use App\Models\Media;
use App\Models\RepairJob;
use App\Models\Vehicle;
use App\Services\GcsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class SignedUrlTest extends TestCase
{
    use RefreshDatabase;

    private Media $media;

    protected function setUp(): void
    {
        parent::setUp();

        $garage = Garage::create([
            'name' => 'Test Garage',
            'slug' => 'test-garage',
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);

        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-su-001',
            'registration' => 'SU12 RL1',
            'make' => 'BMW',
            'model' => '3 Series',
        ]);

        $job = RepairJob::withoutGlobalScopes()->forceCreate([
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_IN_PROGRESS,
        ]);

        $stage = JobStage::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'name' => JobStage::STAGE_DISASSEMBLY,
            'sort_order' => 2,
        ]);

        $this->media = Media::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'job_stage_id' => $stage->id,
            'gcs_path' => "{$job->id}/disassembly/123_photo.jpg",
            'mime_type' => 'image/jpeg',
            'original_filename' => 'photo.jpg',
            'uploaded_by' => 'tester',
            'uploaded_at' => now(),
        ]);
    }

    public function test_signed_url_returned_with_configured_expiry(): void
    {
        config()->set('services.gcs.signed_url_expiry_minutes', 45);

        $expected = 'https://storage.googleapis.com/test-bucket/signed?expires=999';
        $capturedPath = null;
        $capturedExpiry = null;

        $disk = Mockery::mock();
        $disk->shouldReceive('temporaryUrl')
            ->with(Mockery::any(), Mockery::any())
            ->once()
            ->andReturnUsing(function ($path, $expiry) use (&$capturedPath, &$capturedExpiry, $expected) {
                $capturedPath = $path;
                $capturedExpiry = $expiry;

                return $expected;
            });

        Storage::shouldReceive('disk')
            ->with('gcs')
            ->andReturn($disk);

        $url = app(GcsService::class)->signedUrl($this->media);

        $this->assertSame($expected, $url);
        $this->assertSame($this->media->gcs_path, $capturedPath);
        $this->assertEqualsWithDelta(45.0, (float) $capturedExpiry->diffInMinutes(now(), false) * -1, 1.0);
    }

    public function test_signed_urls_returns_map_keyed_by_media_id(): void
    {
        $disk = Mockery::mock();
        $disk->shouldReceive('temporaryUrl')
            ->with(Mockery::any(), Mockery::any())
            ->once()
            ->andReturn('https://example.com/signed-url');

        Storage::shouldReceive('disk')
            ->with('gcs')
            ->andReturn($disk);

        $urls = app(GcsService::class)->signedUrls([$this->media]);

        $this->assertArrayHasKey($this->media->id, $urls);
        $this->assertSame('https://example.com/signed-url', $urls[$this->media->id]);
    }
}

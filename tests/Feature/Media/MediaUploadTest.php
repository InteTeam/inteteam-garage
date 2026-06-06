<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\Garage;
use App\Models\JobStage;
use App\Models\Mechanic;
use App\Models\RepairJob;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    private Garage $garage;

    private RepairJob $job;

    private JobStage $stage;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('gcs');

        $this->garage = Garage::create([
            'name' => 'Test Garage',
            'slug' => 'test-garage',
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);

        $this->user = User::factory()->create();

        Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'user_id' => $this->user->id,
            'role' => Mechanic::ROLE_GARAGE_ADMIN,
            'is_active' => true,
        ]);

        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'crm_customer_id' => 'crm-media-001',
            'registration' => 'MD12 IA1',
            'make' => 'Honda',
            'model' => 'Civic',
        ]);

        $this->job = RepairJob::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_IN_PROGRESS,
        ]);

        $this->stage = JobStage::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'job_id' => $this->job->id,
            'name' => JobStage::STAGE_DISASSEMBLY,
            'sort_order' => 2,
        ]);
    }

    public function test_upload_succeeds_for_unlocked_stage(): void
    {
        $this->withSession(['current_garage_id' => $this->garage->id])
            ->actingAs($this->user)
            ->postJson(route('jobs.stages.media.store', ['job' => $this->job->id, 'stage' => $this->stage->id]), [
                'file' => UploadedFile::fake()->image('photo.jpg'),
            ])
            ->assertCreated()
            ->assertJsonStructure(['id', 'url', 'mime_type', 'original_filename']);

        $this->assertDatabaseHas('media', [
            'job_id' => $this->job->id,
            'job_stage_id' => $this->stage->id,
            'original_filename' => 'photo.jpg',
        ]);
    }

    public function test_upload_rejected_for_locked_stage(): void
    {
        $this->stage->update(['locked_at' => now()]);

        $this->withSession(['current_garage_id' => $this->garage->id])
            ->actingAs($this->user)
            ->postJson(route('jobs.stages.media.store', ['job' => $this->job->id, 'stage' => $this->stage->id]), [
                'file' => UploadedFile::fake()->image('late.jpg'),
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('media', [
            'job_id' => $this->job->id,
            'original_filename' => 'late.jpg',
        ]);
    }

    public function test_upload_validates_mime_type(): void
    {
        $this->withSession(['current_garage_id' => $this->garage->id])
            ->actingAs($this->user)
            ->postJson(route('jobs.stages.media.store', ['job' => $this->job->id, 'stage' => $this->stage->id]), [
                'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }
}

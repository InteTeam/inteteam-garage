<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Models\Garage;
use App\Models\JobStage;
use App\Models\Mechanic;
use App\Models\RepairJob;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\JobStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class JobStageNotesTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_locale_notes_save_without_translation(): void
    {
        Http::fake([
            '*/api/v1/internal/customers/*' => Http::response(['data' => ['locale' => 'en']], 200),
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'en']]]], 200),
        ]);

        [$garage, , $mechanic] = $this->makeGarage('en', 'en');
        $stage = $this->makeStage($garage);

        /** @var JobStageService $service */
        $service = $this->app->make(JobStageService::class);
        $service->updateNotes($stage, 'Brake pads replaced.', $mechanic);

        $fresh = JobStage::withoutGlobalScopes()->find($stage->id);
        $this->assertSame('Brake pads replaced.', $fresh->notes);
        $this->assertNull($fresh->notes_translated);
        $this->assertSame('en', $fresh->notes_source_locale);
        $this->assertNull($fresh->notes_target_locale);
        $this->assertNull($fresh->notes_translated_at);
        $this->assertFalse($fresh->notesWereTranslatedByAi());
    }

    public function test_cross_locale_notes_save_with_translated_copy_and_disclaimer_flag(): void
    {
        Http::fake([
            '*/api/v1/internal/customers/*' => Http::response(['data' => ['locale' => 'en']], 200),
            'api.openai.com/*' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => 'pl']]]])
                ->push(['choices' => [['message' => ['content' => 'Brake pads replaced.']]]])
                ->whenEmpty(Http::response(['choices' => [['message' => ['content' => 'unused']]]], 200)),
        ]);

        [$garage, , $mechanic] = $this->makeGarage('en', 'pl');
        $stage = $this->makeStage($garage);

        /** @var JobStageService $service */
        $service = $this->app->make(JobStageService::class);
        $service->updateNotes($stage, 'Klocki hamulcowe wymienione.', $mechanic);

        $fresh = JobStage::withoutGlobalScopes()->find($stage->id);
        $this->assertSame('Klocki hamulcowe wymienione.', $fresh->notes);
        $this->assertSame('Brake pads replaced.', $fresh->notes_translated);
        $this->assertSame('pl', $fresh->notes_source_locale);
        $this->assertSame('en', $fresh->notes_target_locale);
        $this->assertNotNull($fresh->notes_translated_at);
        $this->assertTrue($fresh->notesWereTranslatedByAi());
    }

    public function test_blank_notes_skip_translation_entirely(): void
    {
        Http::fake();

        [$garage, , $mechanic] = $this->makeGarage('en', 'pl');
        $stage = $this->makeStage($garage);

        /** @var JobStageService $service */
        $service = $this->app->make(JobStageService::class);
        $service->updateNotes($stage, '', $mechanic);

        $fresh = JobStage::withoutGlobalScopes()->find($stage->id);
        $this->assertSame('', $fresh->notes);
        $this->assertNull($fresh->notes_translated);
        $this->assertNull($fresh->notes_source_locale);
        $this->assertNull($fresh->notes_target_locale);
        Http::assertNothingSent();
    }

    /**
     * @return array{0: Garage, 1: User, 2: Mechanic}
     */
    private function makeGarage(string $garageLocale, ?string $mechanicLocale): array
    {
        $garage = Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => $garageLocale,
        ]);

        $user = User::factory()->create();

        $mechanic = Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'user_id' => $user->id,
            'role' => Mechanic::ROLE_MECHANIC,
            'locale' => $mechanicLocale,
            'is_active' => true,
        ]);

        return [$garage, $user, $mechanic];
    }

    private function makeStage(Garage $garage): JobStage
    {
        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-' . uniqid(),
            'registration' => Str::upper(Str::random(7)),
            'make' => 'Ford',
            'model' => 'Focus',
        ]);

        $job = RepairJob::withoutGlobalScopes()->forceCreate([
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_IN_PROGRESS,
        ]);

        return JobStage::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'name' => JobStage::STAGE_REPAIR,
            'sort_order' => 1,
        ]);
    }
}

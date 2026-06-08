<?php

declare(strict_types=1);

namespace Tests\Feature\Translation;

use App\Models\Estimate;
use App\Models\Garage;
use App\Models\LineItem;
use App\Models\Mechanic;
use App\Models\RepairJob;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EstimatePreviewTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mechanic_explicit_locale_drives_from_locale(): void
    {
        Http::fake([
            '*/api/v1/internal/customers/*' => Http::response(['data' => ['locale' => 'en']], 200),
            'api.openai.com/*' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => 'pl']]]])
                ->push(['choices' => [['message' => ['content' => 'brake pads replaced']]]])
                ->whenEmpty(Http::response(['choices' => [['message' => ['content' => 'unused']]]], 200)),
        ]);

        [$garage, $user] = $this->makeGarageWithMechanic(garageLocale: 'en', mechanicLocale: 'pl');
        [$job, $estimate] = $this->makeJobWithEstimate($garage, sourceText: 'klocki hamulcowe wymienione');

        $response = $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->postJson(route('jobs.estimates.preview-translation', ['job' => $job->id, 'estimate' => $estimate->id]));

        $response->assertOk();
        $response->assertJson([
            'from_locale' => 'pl',
            'to_locale' => 'en',
            'configured_from_locale' => 'pl',
            'auto_detected_override' => false,
        ]);
        $this->assertSame('brake pads replaced', $response->json('translations.0.translated'));
        $this->assertTrue($response->json('translations.0.translated_by_ai'));
    }

    public function test_mechanic_null_locale_falls_back_to_garage_locale(): void
    {
        Http::fake([
            '*/api/v1/internal/customers/*' => Http::response(['data' => ['locale' => 'pl']], 200),
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'pl']]]], 200),
        ]);

        [$garage, $user] = $this->makeGarageWithMechanic(garageLocale: 'pl', mechanicLocale: null);
        [$job, $estimate] = $this->makeJobWithEstimate($garage, sourceText: 'klocki hamulcowe wymienione');

        $response = $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->postJson(route('jobs.estimates.preview-translation', ['job' => $job->id, 'estimate' => $estimate->id]));

        $response->assertOk();
        $response->assertJson([
            'from_locale' => 'pl',
            'to_locale' => 'pl',
            'configured_from_locale' => 'pl',
            'auto_detected_override' => false,
        ]);
        $this->assertSame('klocki hamulcowe wymienione', $response->json('translations.0.translated'));
        $this->assertFalse($response->json('translations.0.translated_by_ai'));
    }

    public function test_auto_detect_overrides_misconfigured_mechanic_locale(): void
    {
        Http::fake([
            '*/api/v1/internal/customers/*' => Http::response(['data' => ['locale' => 'en']], 200),
            'api.openai.com/*' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => 'pl']]]])
                ->push(['choices' => [['message' => ['content' => 'brake pads replaced']]]])
                ->whenEmpty(Http::response(['choices' => [['message' => ['content' => 'unused']]]], 200)),
        ]);

        [$garage, $user] = $this->makeGarageWithMechanic(garageLocale: 'en', mechanicLocale: 'en');
        [$job, $estimate] = $this->makeJobWithEstimate($garage, sourceText: 'klocki hamulcowe wymienione');

        $response = $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->postJson(route('jobs.estimates.preview-translation', ['job' => $job->id, 'estimate' => $estimate->id]));

        $response->assertOk();
        $response->assertJson([
            'from_locale' => 'pl',
            'to_locale' => 'en',
            'configured_from_locale' => 'en',
            'auto_detected_override' => true,
        ]);
    }

    public function test_to_locale_falls_back_to_garage_when_crm_unreachable(): void
    {
        Http::fake([
            '*/api/v1/internal/customers/*' => Http::response('CRM down', 503),
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'en']]]], 200),
        ]);

        [$garage, $user] = $this->makeGarageWithMechanic(garageLocale: 'pl', mechanicLocale: 'en');
        [$job, $estimate] = $this->makeJobWithEstimate($garage, sourceText: 'brake pads replaced');

        $response = $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->postJson(route('jobs.estimates.preview-translation', ['job' => $job->id, 'estimate' => $estimate->id]));

        $response->assertOk();
        $response->assertJson([
            'from_locale' => 'en',
            'to_locale' => 'pl',
        ]);
    }

    /**
     * @return array{0: Garage, 1: User}
     */
    private function makeGarageWithMechanic(string $garageLocale, ?string $mechanicLocale): array
    {
        $garage = Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => $garageLocale,
        ]);

        $user = User::factory()->create();

        Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'user_id' => $user->id,
            'role' => Mechanic::ROLE_GARAGE_ADMIN,
            'locale' => $mechanicLocale,
            'is_active' => true,
        ]);

        return [$garage, $user];
    }

    /**
     * @return array{0: RepairJob, 1: Estimate}
     */
    private function makeJobWithEstimate(Garage $garage, string $sourceText): array
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

        $estimate = Estimate::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'revision_number' => 1,
        ]);

        LineItem::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'estimate_id' => $estimate->id,
            'description' => $sourceText,
            'price' => 480.00,
            'status' => LineItem::STATUS_PENDING,
        ]);

        return [$job, $estimate];
    }
}

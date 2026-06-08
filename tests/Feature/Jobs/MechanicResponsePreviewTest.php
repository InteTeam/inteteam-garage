<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

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

final class MechanicResponsePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_locale_preview_returns_translated_text(): void
    {
        Http::fake([
            '*/api/v1/internal/customers/*' => Http::response(['data' => ['locale' => 'en']], 200),
            'api.openai.com/*' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => 'pl']]]])
                ->push(['choices' => [['message' => ['content' => 'brake pads replaced']]]])
                ->whenEmpty(Http::response(['choices' => [['message' => ['content' => 'unused']]]], 200)),
        ]);

        [$garage, $user] = $this->makeGarageWithMechanic('en', 'pl');
        [$job, $lineItem] = $this->makeJobWithLineItem($garage);

        $response = $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->postJson(route('jobs.line-items.preview-response', ['job' => $job->id, 'lineItem' => $lineItem->id]), [
                'message' => 'Klocki hamulcowe wymienione.',
            ]);

        $response->assertOk();
        $response->assertJson([
            'original' => 'Klocki hamulcowe wymienione.',
            'translated' => 'brake pads replaced',
            'from_locale' => 'pl',
            'to_locale' => 'en',
            'configured_from_locale' => 'pl',
            'auto_detected_override' => false,
            'translated_by_ai' => true,
        ]);
    }

    public function test_same_locale_preview_short_circuits_without_llm_translation(): void
    {
        Http::fake([
            '*/api/v1/internal/customers/*' => Http::response(['data' => ['locale' => 'en']], 200),
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'en']]]], 200),
        ]);

        [$garage, $user] = $this->makeGarageWithMechanic('en', 'en');
        [$job, $lineItem] = $this->makeJobWithLineItem($garage);

        $response = $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->postJson(route('jobs.line-items.preview-response', ['job' => $job->id, 'lineItem' => $lineItem->id]), [
                'message' => 'Brake pads replaced.',
            ]);

        $response->assertOk();
        $response->assertJson([
            'translated' => 'Brake pads replaced.',
            'from_locale' => 'en',
            'to_locale' => 'en',
            'translated_by_ai' => false,
        ]);
    }

    public function test_empty_message_is_rejected(): void
    {
        Http::fake();

        [$garage, $user] = $this->makeGarageWithMechanic('en', 'en');
        [$job, $lineItem] = $this->makeJobWithLineItem($garage);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->postJson(route('jobs.line-items.preview-response', ['job' => $job->id, 'lineItem' => $lineItem->id]), [])
            ->assertStatus(422);

        Http::assertNothingSent();
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
     * @return array{0: RepairJob, 1: LineItem}
     */
    private function makeJobWithLineItem(Garage $garage): array
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
            'state' => RepairJob::STATE_CUSTOMER_QUERY,
        ]);

        $estimate = Estimate::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'revision_number' => 1,
            'sent_at' => now(),
        ]);

        $lineItem = LineItem::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'estimate_id' => $estimate->id,
            'description' => 'Timing belt kit',
            'price' => 480.00,
            'status' => LineItem::STATUS_PENDING,
        ]);

        return [$job, $lineItem];
    }
}

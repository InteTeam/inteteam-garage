<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Models\ApprovalEvent;
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

final class MechanicResponsePreviewGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/api/v1/internal/notifications' => Http::response(['status' => 'ok'], 200),
            '*/api/v1/internal/customers/*' => Http::response(['data' => ['locale' => 'en']], 200),
        ]);
    }

    public function test_cross_locale_response_without_translation_is_rejected(): void
    {
        [$garage, $user] = $this->makeGarageWithMechanic('en', 'pl');
        [$job, $lineItem] = $this->makeJobWithLineItem($garage, RepairJob::STATE_CUSTOMER_QUERY);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.line-items.respond', ['job' => $job->id, 'lineItem' => $lineItem->id]), [
                'message' => 'Klocki są wymienione, proszę odebrać.',
            ])
            ->assertSessionHasErrors('translated_message');

        $this->assertDatabaseMissing('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_MECHANIC_RESPONSE,
        ]);
    }

    public function test_cross_locale_response_with_translation_is_accepted_and_audited(): void
    {
        [$garage, $user] = $this->makeGarageWithMechanic('en', 'pl');
        [$job, $lineItem] = $this->makeJobWithLineItem($garage, RepairJob::STATE_CUSTOMER_QUERY);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.line-items.respond', ['job' => $job->id, 'lineItem' => $lineItem->id]), [
                'message' => 'Klocki są wymienione, proszę odebrać.',
                'translated_message' => 'Brake pads replaced, please collect.',
            ])
            ->assertRedirect();

        $event = ApprovalEvent::withoutGlobalScopes()
            ->where('job_id', $job->id)
            ->where('event_type', ApprovalEvent::EVENT_MECHANIC_RESPONSE)
            ->firstOrFail();

        $this->assertSame('Klocki są wymienione, proszę odebrać.', $event->payload['message']);
        $this->assertSame('Brake pads replaced, please collect.', $event->payload['translated_message']);
        $this->assertSame('pl', $event->payload['from_locale']);
        $this->assertSame('en', $event->payload['to_locale']);
    }

    public function test_same_locale_response_does_not_require_translation(): void
    {
        [$garage, $user] = $this->makeGarageWithMechanic('en', 'en');
        [$job, $lineItem] = $this->makeJobWithLineItem($garage, RepairJob::STATE_CUSTOMER_QUERY);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.line-items.respond', ['job' => $job->id, 'lineItem' => $lineItem->id]), [
                'message' => 'Brake pads replaced, please collect.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_MECHANIC_RESPONSE,
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
     * @return array{0: RepairJob, 1: LineItem}
     */
    private function makeJobWithLineItem(Garage $garage, string $state): array
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
            'state' => $state,
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

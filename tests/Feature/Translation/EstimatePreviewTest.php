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

final class EstimatePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_blocks_cross_locale_estimate_without_confirmation(): void
    {
        $this->fakeHttp(customerLocale: 'pl');

        [$garage, $user, $mechanic] = $this->makeGarageWithMechanic(garageLocale: 'en', mechanicLocale: 'en');
        [$job, $estimate] = $this->makeJobWithEstimate($garage);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.estimates.send', ['job' => $job->id, 'estimate' => $estimate->id]))
            ->assertSessionHasErrors('estimate');

        $this->assertNull($estimate->fresh()->sent_at);
        $this->assertSame(RepairJob::STATE_IN_PROGRESS, $job->fresh()->state);
    }

    public function test_send_allows_same_locale_estimate_without_confirmation(): void
    {
        $this->fakeHttp(customerLocale: 'en');

        [$garage, $user, $mechanic] = $this->makeGarageWithMechanic(garageLocale: 'en', mechanicLocale: 'en');
        [$job, $estimate] = $this->makeJobWithEstimate($garage);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.estimates.send', ['job' => $job->id, 'estimate' => $estimate->id]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNotNull($estimate->fresh()->sent_at);
        $this->assertSame(RepairJob::STATE_AWAITING_APPROVAL, $job->fresh()->state);
    }

    public function test_send_allows_cross_locale_estimate_after_confirmation(): void
    {
        $this->fakeHttp(customerLocale: 'pl');

        [$garage, $user, $mechanic] = $this->makeGarageWithMechanic(garageLocale: 'en', mechanicLocale: 'en');
        [$job, $estimate, $lineItem] = $this->makeJobWithEstimate($garage);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.estimates.confirm-translation', ['job' => $job->id, 'estimate' => $estimate->id]), [
                'confirmations' => [
                    ['id' => $lineItem->id, 'translated_text' => 'klocki hamulcowe', 'llm_raw_text' => 'klocki hamulcowe'],
                ],
            ])
            ->assertRedirect();

        $this->assertNotNull($estimate->fresh()->preview_confirmed_at);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.estimates.send', ['job' => $job->id, 'estimate' => $estimate->id]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNotNull($estimate->fresh()->sent_at);
        $this->assertSame(RepairJob::STATE_AWAITING_APPROVAL, $job->fresh()->state);
    }

    public function test_confirm_marks_unedited_line_items_without_editor_attribution(): void
    {
        $this->fakeHttp(customerLocale: 'pl');

        [$garage, $user, $mechanic] = $this->makeGarageWithMechanic(garageLocale: 'en', mechanicLocale: 'en');
        [, $estimate, $lineItem] = $this->makeJobWithEstimate($garage);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.estimates.confirm-translation', ['job' => $estimate->job_id, 'estimate' => $estimate->id]), [
                'confirmations' => [
                    ['id' => $lineItem->id, 'translated_text' => 'klocki', 'llm_raw_text' => 'klocki'],
                ],
            ])
            ->assertRedirect();

        $fresh = LineItem::withoutGlobalScopes()->find($lineItem->id);
        $this->assertSame('klocki', $fresh->translation_confirmed_text);
        $this->assertSame('klocki', $fresh->translation_llm_raw);
        $this->assertNull($fresh->translation_edited_by_mechanic_id);
    }

    public function test_confirm_attributes_mechanic_when_translation_was_edited(): void
    {
        $this->fakeHttp(customerLocale: 'pl');

        [$garage, $user, $mechanic] = $this->makeGarageWithMechanic(garageLocale: 'en', mechanicLocale: 'en');
        [, $estimate, $lineItem] = $this->makeJobWithEstimate($garage);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.estimates.confirm-translation', ['job' => $estimate->job_id, 'estimate' => $estimate->id]), [
                'confirmations' => [
                    ['id' => $lineItem->id, 'translated_text' => 'klocki hamulcowe (poprawione)', 'llm_raw_text' => 'klocki hamulcowe'],
                ],
            ])
            ->assertRedirect();

        $fresh = LineItem::withoutGlobalScopes()->find($lineItem->id);
        $this->assertSame('klocki hamulcowe (poprawione)', $fresh->translation_confirmed_text);
        $this->assertSame('klocki hamulcowe', $fresh->translation_llm_raw);
        $this->assertSame($mechanic->id, $fresh->translation_edited_by_mechanic_id);
    }

    private function fakeHttp(string $customerLocale): void
    {
        Http::fake([
            '*/api/v1/internal/customers/*' => Http::response(['data' => ['locale' => $customerLocale]], 200),
            '*/api/v1/internal/notifications' => Http::response(['status' => 'ok'], 200),
        ]);
    }

    /**
     * @return array{0: Garage, 1: User, 2: Mechanic}
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

        $mechanic = Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'user_id' => $user->id,
            'role' => Mechanic::ROLE_GARAGE_ADMIN,
            'locale' => $mechanicLocale,
            'is_active' => true,
        ]);

        return [$garage, $user, $mechanic];
    }

    /**
     * @return array{0: RepairJob, 1: Estimate, 2: LineItem}
     */
    private function makeJobWithEstimate(Garage $garage): array
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

        $lineItem = LineItem::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'estimate_id' => $estimate->id,
            'description' => 'brake pads',
            'price' => 120.00,
            'status' => LineItem::STATUS_PENDING,
        ]);

        return [$job, $estimate, $lineItem];
    }
}

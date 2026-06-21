<?php

declare(strict_types=1);

namespace Tests\Feature\Estimates;

use App\Models\Estimate;
use App\Models\Garage;
use App\Models\LineItem;
use App\Models\Mechanic;
use App\Models\RepairJob;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AddLineItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $estimate = $this->makeEstimate();

        $response = $this->post(route('jobs.estimates.line-items.store', [
            'job' => $estimate->repairJob->id,
            'estimate' => $estimate->id,
        ]), ['description' => 'Wipers', 'price' => 25]);

        $response->assertRedirect('/login');
    }

    public function test_non_admin_mechanic_is_forbidden(): void
    {
        $estimate = $this->makeEstimate();
        $user = $this->makeMechanic($estimate->garage_id, Mechanic::ROLE_MECHANIC);

        $response = $this->actingAs($user)
            ->withSession(['current_garage_id' => $estimate->garage_id])
            ->post(route('jobs.estimates.line-items.store', [
                'job' => $estimate->repairJob->id,
                'estimate' => $estimate->id,
            ]), ['description' => 'Wipers', 'price' => 25]);

        $response->assertForbidden();
        $this->assertSame(0, LineItem::withoutGlobalScopes()->where('estimate_id', $estimate->id)->count());
    }

    public function test_admin_can_add_a_line_item_to_a_draft_estimate(): void
    {
        $estimate = $this->makeEstimate();
        $user = $this->makeMechanic($estimate->garage_id, Mechanic::ROLE_GARAGE_ADMIN);

        $response = $this->actingAs($user)
            ->withSession(['current_garage_id' => $estimate->garage_id])
            ->post(route('jobs.estimates.line-items.store', [
                'job' => $estimate->repairJob->id,
                'estimate' => $estimate->id,
            ]), ['description' => 'Brake pads', 'price' => 120.50]);

        $response->assertRedirect();
        $response->assertSessionHas('alert', 'The line item was added.');
        $this->assertDatabaseHas('line_items', [
            'estimate_id' => $estimate->id,
            'description' => 'Brake pads',
            'price' => '120.50',
            'status' => LineItem::STATUS_PENDING,
        ]);
    }

    public function test_missing_fields_surface_validation_errors(): void
    {
        $estimate = $this->makeEstimate();
        $user = $this->makeMechanic($estimate->garage_id, Mechanic::ROLE_GARAGE_ADMIN);

        $response = $this->actingAs($user)
            ->withSession(['current_garage_id' => $estimate->garage_id])
            ->post(route('jobs.estimates.line-items.store', [
                'job' => $estimate->repairJob->id,
                'estimate' => $estimate->id,
            ]), ['description' => '', 'price' => 0]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['description', 'price']);
    }

    public function test_estimate_from_another_garage_returns_404(): void
    {
        // B1 + F1 defence-in-depth: route-model binding alone isn't enough
        // because Eloquent's HasGarageScope returns null for cross-garage IDs,
        // but the controller's explicit garage check also covers the case
        // where the global scope is somehow bypassed.
        $foreign = $this->makeEstimate();
        $foreignJobId = $foreign->job_id;
        $mineGarage = $this->makeGarage();
        $user = $this->makeMechanic($mineGarage->id, Mechanic::ROLE_GARAGE_ADMIN);

        $response = $this->actingAs($user)
            ->withSession(['current_garage_id' => $mineGarage->id])
            ->post(route('jobs.estimates.line-items.store', [
                'job' => $foreignJobId,
                'estimate' => $foreign->id,
            ]), ['description' => 'Wipers', 'price' => 25]);

        $response->assertNotFound();
        $this->assertSame(0, LineItem::withoutGlobalScopes()->where('estimate_id', $foreign->id)->count());
    }

    public function test_estimate_not_matching_job_returns_404(): void
    {
        $estimate = $this->makeEstimate();
        $otherJob = $this->makeJob($estimate->garage_id);
        $user = $this->makeMechanic($estimate->garage_id, Mechanic::ROLE_GARAGE_ADMIN);

        $response = $this->actingAs($user)
            ->withSession(['current_garage_id' => $estimate->garage_id])
            ->post(route('jobs.estimates.line-items.store', [
                'job' => $otherJob->id,
                'estimate' => $estimate->id,
            ]), ['description' => 'Wipers', 'price' => 25]);

        $response->assertNotFound();
    }

    public function test_sent_estimate_rejects_new_line_item(): void
    {
        // B1 — once the estimate is sent the customer may be reading it; the
        // mechanic must raise a scope change for a new revision instead of
        // silently changing the goalposts.
        $estimate = $this->makeEstimate();
        $estimate->update(['sent_at' => now()]);
        $user = $this->makeMechanic($estimate->garage_id, Mechanic::ROLE_GARAGE_ADMIN);

        $response = $this->actingAs($user)
            ->withSession(['current_garage_id' => $estimate->garage_id])
            ->post(route('jobs.estimates.line-items.store', [
                'job' => $estimate->repairJob->id,
                'estimate' => $estimate->id,
            ]), ['description' => 'Wipers', 'price' => 25]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('description');
        $this->assertSame(0, LineItem::withoutGlobalScopes()->where('estimate_id', $estimate->id)->count());
    }

    public function test_estimate_with_customer_response_rejects_new_line_item(): void
    {
        $estimate = $this->makeEstimate();
        LineItem::withoutGlobalScopes()->create([
            'garage_id' => $estimate->garage_id,
            'estimate_id' => $estimate->id,
            'description' => 'Brake pads',
            'price' => 120.00,
            'status' => LineItem::STATUS_APPROVED,
        ]);
        $user = $this->makeMechanic($estimate->garage_id, Mechanic::ROLE_GARAGE_ADMIN);

        $response = $this->actingAs($user)
            ->withSession(['current_garage_id' => $estimate->garage_id])
            ->post(route('jobs.estimates.line-items.store', [
                'job' => $estimate->repairJob->id,
                'estimate' => $estimate->id,
            ]), ['description' => 'Wipers', 'price' => 25]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('description');
        $this->assertSame(1, LineItem::withoutGlobalScopes()->where('estimate_id', $estimate->id)->count());
    }

    private function makeEstimate(): Estimate
    {
        $garage = $this->makeGarage();
        $job = $this->makeJob($garage->id);

        return Estimate::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'revision_number' => 1,
        ]);
    }

    private function makeGarage(): Garage
    {
        return Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);
    }

    private function makeJob(string $garageId): RepairJob
    {
        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garageId,
            'crm_customer_id' => 'crm-' . uniqid(),
            'registration' => Str::upper(Str::random(7)),
            'make' => 'Ford',
            'model' => 'Focus',
        ]);

        return RepairJob::withoutGlobalScopes()->forceCreate([
            'garage_id' => $garageId,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_IN_PROGRESS,
        ]);
    }

    private function makeMechanic(string $garageId, string $role): User
    {
        $user = User::factory()->create();
        Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garageId,
            'user_id' => $user->id,
            'role' => $role,
            'is_active' => true,
        ]);

        return $user;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Models\Garage;
use App\Models\Mechanic;
use App\Models\RepairJob;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `EnsureGarageContext` used to write `current_garage_id` only when the key was
 * absent. Because that session value is what `GarageScope` filters on (and what
 * every policy compares `garage_id` against), a session carrying another
 * garage's id was honoured for its whole lifetime: the scope filtered to the
 * stale garage and the policies agreed with it, so nothing 403'd or 404'd. The
 * middleware now re-asserts the value from the mechanic record on every request.
 */
final class StaleGarageContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_garage_id_is_corrected_to_the_mechanics_own_garage(): void
    {
        [$ownGarage, $user] = $this->createGarageWithAdmin();
        [$otherGarage] = $this->createGarageWithAdmin();

        $this->actingAs($user);
        session(['current_garage_id' => $otherGarage->id]);

        $this->get('/dashboard')->assertOk();

        $this->assertSame($ownGarage->id, session('current_garage_id'));
    }

    /**
     * `SubstituteBindings` belongs to the `web` group and therefore runs *before*
     * the route-level `garage` middleware, so the stale session is still in place
     * when `{job}` is resolved and the other garage's job does bind. The block
     * lands one layer later, in `RepairJobPolicy::view()`, which compares against
     * the session value the middleware has by then corrected — 403, not 404.
     */
    public function test_stale_session_cannot_read_another_garages_job(): void
    {
        [, $user] = $this->createGarageWithAdmin();
        [$otherGarage] = $this->createGarageWithAdmin();

        $otherJob = $this->makeJobIn($otherGarage);

        $this->actingAs($user);
        // Hand-planted stale value — what a garage reassignment leaves behind.
        session(['current_garage_id' => $otherGarage->id]);

        $this->get("/jobs/{$otherJob->id}")->assertForbidden();
    }

    private function makeJobIn(Garage $garage): RepairJob
    {
        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-' . uniqid(),
            'registration' => 'ST41 LEE',
            'make' => 'Audi',
            'model' => 'A3',
        ]);

        return RepairJob::withoutGlobalScopes()->forceCreate([
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_CREATED,
        ]);
    }

    /**
     * @return array{0: Garage, 1: User}
     */
    private function createGarageWithAdmin(): array
    {
        $garage = Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => Garage::CHANNEL_EMAIL,
            'locale' => 'en',
        ]);

        $user = User::factory()->create();

        Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'user_id' => $user->id,
            'role' => Mechanic::ROLE_GARAGE_ADMIN,
            'is_active' => true,
        ]);

        return [$garage, $user];
    }
}

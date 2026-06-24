<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\Garage;
use App\Models\RepairJob;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_redirected_to_customer_login_not_mechanic(): void
    {
        // Assert the exact target — a regression that swaps redirectGuestsTo
        // back to mechanic route('login') would loop in production (mechanic
        // callback rejects non-mechanics → redirects to /login → loop).
        $this->get(route('customer.dashboard'))
            ->assertRedirect(route('customer.login'));
    }

    public function test_unlinked_customer_sees_banner_no_data(): void
    {
        $customer = Customer::create([
            'email' => 'orphan@example.com',
            'name' => 'Orphan',
            'crm_customer_id' => null,
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertInertia(
                fn ($p) => $p
                    ->component('Account/Dashboard')
                    ->where('linked', false)
                    ->where('vehicles', [])
                    ->where('recentJobs', []),
            );
    }

    public function test_dashboard_lists_vehicles_for_linked_customer(): void
    {
        $customer = Customer::create([
            'email' => 'jane@example.com',
            'name' => 'Jane',
            'crm_customer_id' => 'crm-jane',
        ]);

        $garage = $this->makeGarage();
        $myVehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-jane',
            'registration' => 'AB12 CDE',
            'make' => 'Ford',
            'model' => 'Focus',
        ]);

        // Decoy — someone else's vehicle in the same garage
        Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-other',
            'registration' => 'XX99 YYY',
            'make' => 'Vauxhall',
            'model' => 'Astra',
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertInertia(
                fn ($p) => $p
                    ->component('Account/Dashboard')
                    ->where('linked', true)
                    ->has('vehicles', 1)
                    ->where('vehicles.0.id', $myVehicle->id)
                    ->where('vehicles.0.registration', 'AB12 CDE'),
            );
    }

    public function test_dashboard_aggregates_jobs_across_garages(): void
    {
        $customer = Customer::create([
            'email' => 'jane@example.com',
            'name' => 'Jane',
            'crm_customer_id' => 'crm-jane',
        ]);

        $garageA = $this->makeGarage('A');
        $garageB = $this->makeGarage('B');

        $vehicleA = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garageA->id,
            'crm_customer_id' => 'crm-jane',
            'registration' => 'AAA 1', 'make' => 'Ford', 'model' => 'Focus',
        ]);
        $vehicleB = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garageB->id,
            'crm_customer_id' => 'crm-jane',
            'registration' => 'BBB 2', 'make' => 'BMW', 'model' => '3 Series',
        ]);

        RepairJob::withoutGlobalScopes()->create([
            'garage_id' => $garageA->id,
            'vehicle_id' => $vehicleA->id,
        ]);
        RepairJob::withoutGlobalScopes()->create([
            'garage_id' => $garageB->id,
            'vehicle_id' => $vehicleB->id,
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->has('recentJobs', 2));
    }

    private function makeGarage(string $suffix = ''): Garage
    {
        return Garage::create([
            'name' => 'Garage ' . $suffix . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);
    }
}

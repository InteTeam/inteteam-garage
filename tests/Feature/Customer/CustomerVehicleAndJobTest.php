<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\Garage;
use App\Models\RepairJob;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerVehicleAndJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_show_returns_own_vehicle(): void
    {
        [$customer, $vehicle] = $this->makeCustomerWithVehicle();

        $this->actingAs($customer, 'customer')
            ->get(route('customer.vehicles.show', $vehicle->id))
            ->assertOk()
            ->assertInertia(
                fn ($p) => $p
                    ->component('Account/VehicleShow')
                    ->where('vehicle.id', $vehicle->id),
            );
    }

    public function test_vehicle_show_returns_404_for_another_customers_vehicle(): void
    {
        [$customer] = $this->makeCustomerWithVehicle('crm-mine');
        [, $foreignVehicle] = $this->makeCustomerWithVehicle('crm-someone-else');

        $this->actingAs($customer, 'customer')
            ->get(route('customer.vehicles.show', $foreignVehicle->id))
            ->assertNotFound();
    }

    public function test_vehicle_show_returns_404_for_unlinked_customer(): void
    {
        $customer = Customer::create([
            'email' => 'orphan@example.com',
            'name' => 'Orphan',
            'crm_customer_id' => null,
        ]);

        [, $foreignVehicle] = $this->makeCustomerWithVehicle();

        $this->actingAs($customer, 'customer')
            ->get(route('customer.vehicles.show', $foreignVehicle->id))
            ->assertNotFound();
    }

    public function test_job_show_returns_own_job(): void
    {
        [$customer, $vehicle] = $this->makeCustomerWithVehicle();
        $job = RepairJob::withoutGlobalScopes()->create([
            'garage_id' => $vehicle->garage_id,
            'vehicle_id' => $vehicle->id,
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.jobs.show', $job->id))
            ->assertOk()
            ->assertInertia(
                fn ($p) => $p
                    ->component('Account/JobShow')
                    ->where('job.id', $job->id),
            );
    }

    public function test_job_show_returns_404_for_foreign_job(): void
    {
        [$customer] = $this->makeCustomerWithVehicle('crm-mine');
        [, $foreignVehicle] = $this->makeCustomerWithVehicle('crm-stranger');

        $foreignJob = RepairJob::withoutGlobalScopes()->create([
            'garage_id' => $foreignVehicle->garage_id,
            'vehicle_id' => $foreignVehicle->id,
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.jobs.show', $foreignJob->id))
            ->assertNotFound();
    }

    /** @return array{0: Customer, 1: Vehicle} */
    private function makeCustomerWithVehicle(string $crmId = 'crm-1'): array
    {
        $customer = Customer::create([
            'email' => $crmId . '@example.com',
            'name' => 'C-' . $crmId,
            'crm_customer_id' => $crmId,
        ]);

        $garage = Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);

        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => $crmId,
            'registration' => strtoupper(substr($crmId, -3)) . ' 123',
            'make' => 'Ford',
            'model' => 'Focus',
        ]);

        return [$customer, $vehicle];
    }
}

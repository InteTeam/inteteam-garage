<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ComplianceSource;
use App\Enums\ComplianceType;
use App\Models\ComplianceRecord;
use App\Models\Garage;
use App\Models\Mechanic;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ComplianceReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ComplianceReminderDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/api/v1/internal/notifications' => Http::response([], 202),
            '*/api/v1/internal/customers/*' => Http::response(['data' => ['locale' => 'en']], 200),
        ]);
    }

    public function test_dispatch_skips_garage_with_reminders_disabled(): void
    {
        $garage = $this->makeGarage(['compliance_reminders_enabled' => false]);
        $vehicle = $this->makeVehicle($garage);
        $this->makeComplianceRecord($vehicle, ComplianceType::MOT, now()->addDays(30));

        $metrics = app(ComplianceReminderService::class)->dispatchDue(now());

        $this->assertSame([], $metrics);
        Http::assertNothingSent();
    }

    public function test_dispatch_sends_to_customer_on_matching_window(): void
    {
        $garage = $this->makeGarage([
            'compliance_reminders_enabled' => true,
            'compliance_reminders_windows' => [30, 7],
            'compliance_reminders_types' => ['mot', 'tax', 'insurance'],
            'compliance_reminders_recipient' => 'customer',
        ]);
        $vehicle = $this->makeVehicle($garage);
        $this->makeComplianceRecord($vehicle, ComplianceType::MOT, now()->copy()->startOfDay()->addDays(30));

        $metrics = app(ComplianceReminderService::class)->dispatchDue(now());

        $this->assertSame(1, $metrics[$garage->id]['sent']);
        $this->assertSame(0, $metrics[$garage->id]['errors']);
        $this->assertDatabaseHas('compliance_reminders_sent', [
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'type' => ComplianceType::MOT->value,
            'recipient_type' => 'customer',
            'recipient_id' => $vehicle->crm_customer_id,
            'window_days' => 30,
        ]);
    }

    public function test_dispatch_does_not_send_when_window_does_not_match(): void
    {
        $garage = $this->makeGarage([
            'compliance_reminders_enabled' => true,
            'compliance_reminders_windows' => [30],
            'compliance_reminders_types' => ['mot'],
        ]);
        $vehicle = $this->makeVehicle($garage);
        $this->makeComplianceRecord($vehicle, ComplianceType::MOT, now()->copy()->startOfDay()->addDays(15));

        $metrics = app(ComplianceReminderService::class)->dispatchDue(now());

        $this->assertSame(0, $metrics[$garage->id]['sent']);
        $this->assertDatabaseCount('compliance_reminders_sent', 0);
    }

    public function test_dispatch_dedupes_repeat_runs(): void
    {
        $garage = $this->makeGarage([
            'compliance_reminders_enabled' => true,
            'compliance_reminders_windows' => [30],
            'compliance_reminders_types' => ['mot'],
        ]);
        $vehicle = $this->makeVehicle($garage);
        $this->makeComplianceRecord($vehicle, ComplianceType::MOT, now()->copy()->startOfDay()->addDays(30));

        $service = app(ComplianceReminderService::class);

        $service->dispatchDue(now());
        $service->dispatchDue(now());

        $this->assertDatabaseCount('compliance_reminders_sent', 1);
        Http::assertSentCount(1);
    }

    public function test_dispatch_only_uses_latest_record_per_type(): void
    {
        $garage = $this->makeGarage([
            'compliance_reminders_enabled' => true,
            'compliance_reminders_windows' => [30],
            'compliance_reminders_types' => ['mot'],
        ]);
        $vehicle = $this->makeVehicle($garage);

        // Older record with date NOT in window.
        $this->makeComplianceRecord($vehicle, ComplianceType::MOT, now()->copy()->startOfDay()->addDays(60));
        // Newer record with date in 30-day window.
        $this->makeComplianceRecord($vehicle, ComplianceType::MOT, now()->copy()->startOfDay()->addDays(30));

        $metrics = app(ComplianceReminderService::class)->dispatchDue(now());

        $this->assertSame(1, $metrics[$garage->id]['sent']);
    }

    public function test_dispatch_skips_types_not_in_garage_config(): void
    {
        $garage = $this->makeGarage([
            'compliance_reminders_enabled' => true,
            'compliance_reminders_windows' => [30],
            'compliance_reminders_types' => ['mot'],
        ]);
        $vehicle = $this->makeVehicle($garage);
        $this->makeComplianceRecord($vehicle, ComplianceType::INSURANCE, now()->copy()->startOfDay()->addDays(30));

        $metrics = app(ComplianceReminderService::class)->dispatchDue(now());

        $this->assertSame(0, $metrics[$garage->id]['sent']);
    }

    public function test_dispatch_sends_to_both_customer_and_mechanic_when_configured(): void
    {
        $garage = $this->makeGarage([
            'compliance_reminders_enabled' => true,
            'compliance_reminders_windows' => [30],
            'compliance_reminders_types' => ['mot'],
            'compliance_reminders_recipient' => 'customer_and_mechanic',
        ]);
        $this->makeAdminMechanic($garage, 'crm-admin-123');
        $vehicle = $this->makeVehicle($garage);
        $this->makeComplianceRecord($vehicle, ComplianceType::MOT, now()->copy()->startOfDay()->addDays(30));

        // Enable staff notifications feature flag so CRM call goes through.
        config()->set('services.garage.staff_notifications_via_crm_enabled', true);

        $metrics = app(ComplianceReminderService::class)->dispatchDue(now());

        $this->assertSame(2, $metrics[$garage->id]['sent']);
        $this->assertDatabaseHas('compliance_reminders_sent', [
            'vehicle_id' => $vehicle->id,
            'recipient_type' => 'customer',
        ]);
        $this->assertDatabaseHas('compliance_reminders_sent', [
            'vehicle_id' => $vehicle->id,
            'recipient_type' => 'mechanic',
            'recipient_id' => 'crm-admin-123',
        ]);
    }

    private function makeGarage(array $overrides = []): Garage
    {
        return Garage::create(array_merge([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ], $overrides));
    }

    private function makeVehicle(Garage $garage): Vehicle
    {
        return Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-cust-' . uniqid(),
            'registration' => 'AB12 CDE',
            'make' => 'Ford',
            'model' => 'Focus',
        ]);
    }

    private function makeComplianceRecord(Vehicle $vehicle, ComplianceType $type, Carbon $expiresOn): ComplianceRecord
    {
        return ComplianceRecord::create([
            'vehicle_id' => $vehicle->id,
            'garage_id' => $vehicle->garage_id,
            'recorded_by_user_id' => null,
            'type' => $type,
            'source' => ComplianceSource::MANUAL,
            'expires_on' => $expiresOn->toDateString(),
        ]);
    }

    private function makeAdminMechanic(Garage $garage, string $crmUserId): Mechanic
    {
        $user = User::factory()->create();
        $user->crm_user_id = $crmUserId;
        $user->save();

        return Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'user_id' => $user->id,
            'role' => Mechanic::ROLE_GARAGE_ADMIN,
            'is_active' => true,
        ]);
    }
}

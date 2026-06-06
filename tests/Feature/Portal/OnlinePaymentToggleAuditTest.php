<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\ApprovalEvent;
use App\Models\Garage;
use App\Models\RepairJob;
use App\Models\Vehicle;
use App\Services\ApprovalEventService;
use App\Services\GarageSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class OnlinePaymentToggleAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggling_online_payment_appends_event_to_active_jobs(): void
    {
        $garage = $this->makeGarage(false);
        $active = $this->makeJob($garage, RepairJob::STATE_APPROVED);
        $awaiting = $this->makeJob($garage, RepairJob::STATE_AWAITING_COLLECTION);
        $collected = $this->makeJob($garage, RepairJob::STATE_COLLECTED);

        $service = new GarageSettingsService(new ApprovalEventService);

        $service->update($garage, [
            'name' => $garage->name,
            'default_notification_channel' => Garage::CHANNEL_EMAIL,
            'online_payment_enabled' => true,
            'locale' => 'en',
        ], actorId: 'admin-user-1');

        $this->assertDatabaseHas('approval_events', [
            'job_id' => $active->id,
            'event_type' => ApprovalEvent::EVENT_PREFERENCE_CHANGED,
            'actor_type' => ApprovalEvent::ACTOR_MECHANIC,
            'actor_id' => 'admin-user-1',
        ]);
        $this->assertDatabaseHas('approval_events', [
            'job_id' => $awaiting->id,
            'event_type' => ApprovalEvent::EVENT_PREFERENCE_CHANGED,
        ]);
        $this->assertDatabaseMissing('approval_events', [
            'job_id' => $collected->id,
            'event_type' => ApprovalEvent::EVENT_PREFERENCE_CHANGED,
        ]);

        $payload = ApprovalEvent::query()
            ->where('job_id', $active->id)
            ->where('event_type', ApprovalEvent::EVENT_PREFERENCE_CHANGED)
            ->firstOrFail()
            ->payload;
        $this->assertSame('online_payment_enabled', $payload['setting']);
        $this->assertFalse($payload['from']);
        $this->assertTrue($payload['to']);
    }

    public function test_settings_save_without_toggle_change_does_not_log_event(): void
    {
        $garage = $this->makeGarage(true);
        $job = $this->makeJob($garage, RepairJob::STATE_APPROVED);

        $service = new GarageSettingsService(new ApprovalEventService);

        $service->update($garage, [
            'name' => 'New Name',
            'default_notification_channel' => Garage::CHANNEL_SMS,
            'online_payment_enabled' => true,
            'locale' => 'en',
        ], actorId: 'admin-user-1');

        $this->assertSame('New Name', $garage->fresh()->name);
        $this->assertDatabaseMissing('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_PREFERENCE_CHANGED,
        ]);
    }

    private function makeGarage(bool $paymentEnabled): Garage
    {
        return Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => $paymentEnabled,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);
    }

    private function makeJob(Garage $garage, string $state): RepairJob
    {
        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-' . uniqid(),
            'registration' => Str::upper(Str::random(7)),
            'make' => 'Ford',
            'model' => 'Focus',
        ]);

        return RepairJob::withoutGlobalScopes()->forceCreate([
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'state' => $state,
        ]);
    }
}

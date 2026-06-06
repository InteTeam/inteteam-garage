<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\ApprovalEvent;
use App\Models\Garage;
use App\Models\RepairJob;
use App\Models\Vehicle;
use App\Services\ApprovalEventService;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

final class ApprovalEventImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_record_writes_expected_fields(): void
    {
        $job = $this->makeJob();
        $service = new ApprovalEventService;

        $event = $service->record(
            job: $job,
            eventType: ApprovalEvent::EVENT_LINE_ITEM_APPROVED,
            actorType: ApprovalEvent::ACTOR_CUSTOMER,
            actorId: 'token-abc',
            payload: ['line_item_id' => 'li-1'],
        );

        $this->assertDatabaseHas('approval_events', [
            'id' => $event->id,
            'garage_id' => $job->garage_id,
            'job_id' => $job->id,
            'actor_type' => ApprovalEvent::ACTOR_CUSTOMER,
            'actor_id' => 'token-abc',
            'event_type' => ApprovalEvent::EVENT_LINE_ITEM_APPROVED,
        ]);
        $this->assertNotNull($event->occurred_at);
        $this->assertSame(['line_item_id' => 'li-1'], $event->payload);
    }

    public function test_record_by_system_uses_system_actor(): void
    {
        $job = $this->makeJob();
        $service = new ApprovalEventService;

        $event = $service->recordBySystem(
            $job,
            ApprovalEvent::EVENT_TIMEOUT_ALERT,
            ['reason' => 'no customer response in 24h'],
        );

        $this->assertSame(ApprovalEvent::ACTOR_SYSTEM, $event->actor_type);
        $this->assertSame('system', $event->actor_id);
        $this->assertSame(ApprovalEvent::EVENT_TIMEOUT_ALERT, $event->event_type);
    }

    public function test_appending_events_preserves_chronological_order(): void
    {
        $job = $this->makeJob();
        $service = new ApprovalEventService;

        $first = $service->record(
            $job,
            ApprovalEvent::EVENT_ESTIMATE_SENT,
            ApprovalEvent::ACTOR_MECHANIC,
            'mech-1',
        );
        $second = $service->record(
            $job,
            ApprovalEvent::EVENT_LINE_ITEM_APPROVED,
            ApprovalEvent::ACTOR_CUSTOMER,
            'token-1',
        );
        $third = $service->record(
            $job,
            ApprovalEvent::EVENT_LINE_ITEM_DECLINED,
            ApprovalEvent::ACTOR_CUSTOMER,
            'token-1',
        );

        $events = $job->approvalEvents()->get();

        $this->assertCount(3, $events);
        $this->assertSame($first->id, $events[0]->id);
        $this->assertSame($second->id, $events[1]->id);
        $this->assertSame($third->id, $events[2]->id);
    }

    public function test_approval_events_table_has_no_mutable_timestamps(): void
    {
        $columns = Schema::getColumnListing('approval_events');

        $this->assertNotContains('updated_at', $columns, 'updated_at would imply mutability');
        $this->assertNotContains('created_at', $columns, 'created_at is replaced by occurred_at — single timestamp');
        $this->assertNotContains('deleted_at', $columns, 'deleted_at would imply soft-delete support');
        $this->assertContains('occurred_at', $columns);
    }

    public function test_approval_event_model_has_timestamps_disabled_and_no_soft_deletes(): void
    {
        $model = new ApprovalEvent;
        $this->assertFalse($model->timestamps, 'ApprovalEvent::$timestamps must be false');

        $traits = class_uses_recursive(ApprovalEvent::class);
        $this->assertNotContains(SoftDeletes::class, $traits, 'ApprovalEvent must not use SoftDeletes');
    }

    public function test_service_exposes_only_append_operations(): void
    {
        $reflection = new ReflectionClass(ApprovalEventService::class);
        $publicMethods = array_map(
            fn ($m) => $m->getName(),
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
        );

        $writes = array_values(array_filter(
            $publicMethods,
            fn (string $name) => $name !== '__construct',
        ));

        sort($writes);
        $this->assertSame(['record', 'recordBySystem'], $writes);
    }

    private function makeJob(): RepairJob
    {
        $garage = Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);

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
            'state' => RepairJob::STATE_IN_PROGRESS,
        ]);
    }
}

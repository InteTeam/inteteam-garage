<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Garage;
use App\Models\LineItem;
use App\Models\RepairJob;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerLineItemControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_approve_own_line_item(): void
    {
        [$customer, $job, $lineItem] = $this->makeJobWithLineItem();

        $this->actingAs($customer, 'customer')
            ->post(route('customer.line-items.approve', ['job' => $job->id, 'lineItem' => $lineItem->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('line_items', [
            'id' => $lineItem->id,
            'status' => LineItem::STATUS_APPROVED,
        ]);
    }

    public function test_customer_can_decline_with_notes(): void
    {
        [$customer, $job, $lineItem] = $this->makeJobWithLineItem();

        $this->actingAs($customer, 'customer')
            ->post(route('customer.line-items.decline', ['job' => $job->id, 'lineItem' => $lineItem->id]), [
                'notes' => 'Too expensive — postponing',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('line_items', [
            'id' => $lineItem->id,
            'status' => LineItem::STATUS_DECLINED,
            'customer_notes' => 'Too expensive — postponing',
        ]);
    }

    public function test_decline_requires_notes(): void
    {
        [$customer, $job, $lineItem] = $this->makeJobWithLineItem();

        $this->actingAs($customer, 'customer')
            ->post(route('customer.line-items.decline', ['job' => $job->id, 'lineItem' => $lineItem->id]), [])
            ->assertSessionHasErrors('notes');
    }

    public function test_question_records_event_without_changing_status(): void
    {
        [$customer, $job, $lineItem] = $this->makeJobWithLineItem();

        $this->actingAs($customer, 'customer')
            ->post(route('customer.line-items.question', ['job' => $job->id, 'lineItem' => $lineItem->id]), [
                'notes' => 'Is OEM available?',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('line_items', [
            'id' => $lineItem->id,
            'status' => LineItem::STATUS_PENDING,
        ]);
    }

    public function test_foreign_customer_cannot_approve_others_line_item(): void
    {
        [, $job, $lineItem] = $this->makeJobWithLineItem(crmId: 'crm-owner');
        $stranger = Customer::create([
            'email' => 'stranger@example.com',
            'name' => 'Stranger',
            'crm_customer_id' => 'crm-stranger',
        ]);

        $this->actingAs($stranger, 'customer')
            ->post(route('customer.line-items.approve', ['job' => $job->id, 'lineItem' => $lineItem->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('line_items', [
            'id' => $lineItem->id,
            'status' => LineItem::STATUS_PENDING,
        ]);
    }

    /** @return array{0: Customer, 1: RepairJob, 2: LineItem} */
    private function makeJobWithLineItem(string $crmId = 'crm-1'): array
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
            'registration' => 'AB12 CDE', 'make' => 'Ford', 'model' => 'Focus',
        ]);

        $job = RepairJob::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
        ]);

        // currentEstimate is the latest by revision_number — minimum viable to
        // satisfy LineItemController::resolveOwned's join.
        $job->update(['state' => RepairJob::STATE_AWAITING_APPROVAL]);

        $estimate = Estimate::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'revision_number' => 1,
        ]);

        $lineItem = LineItem::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'estimate_id' => $estimate->id,
            'description' => 'Brake pads',
            'price' => 120.00,
            'status' => LineItem::STATUS_PENDING,
        ]);

        return [$customer, $job, $lineItem];
    }
}

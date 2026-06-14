<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\Estimate;
use App\Models\Garage;
use App\Models\LineItem;
use App\Models\Mechanic;
use App\Models\RepairJob;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\EstimateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class EstimateRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_customer_response_is_false_when_all_line_items_pending(): void
    {
        $estimate = $this->makeEstimateWithLineItem(LineItem::STATUS_PENDING);

        $this->assertFalse($estimate->hasCustomerResponse());
    }

    public function test_has_customer_response_is_true_after_any_approval(): void
    {
        $estimate = $this->makeEstimateWithLineItem(LineItem::STATUS_APPROVED);

        $this->assertTrue($estimate->hasCustomerResponse());
    }

    public function test_has_customer_response_is_true_after_any_decline(): void
    {
        $estimate = $this->makeEstimateWithLineItem(LineItem::STATUS_DECLINED);

        $this->assertTrue($estimate->hasCustomerResponse());
    }

    public function test_service_update_throws_after_customer_response(): void
    {
        $estimate = $this->makeEstimateWithLineItem(LineItem::STATUS_APPROVED);
        $service = new EstimateService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Cannot modify estimate after customer response/');

        $service->update($estimate, ['sent_at' => now()]);
    }

    public function test_service_update_succeeds_while_all_line_items_pending(): void
    {
        $estimate = $this->makeEstimateWithLineItem(LineItem::STATUS_PENDING);
        $service = new EstimateService;

        $sentAt = now()->startOfMinute();
        $updated = $service->update($estimate, ['sent_at' => $sentAt]);

        $this->assertEquals($sentAt->toDateTimeString(), $updated->sent_at?->toDateTimeString());
    }

    public function test_creating_new_revision_increments_revision_number(): void
    {
        $estimate = $this->makeEstimateWithLineItem(LineItem::STATUS_APPROVED, revisionNumber: 1);
        $job = $estimate->repairJob;

        $nextRevision = ($job->estimates()->max('revision_number') ?? 0) + 1;
        $revision2 = Estimate::withoutGlobalScopes()->create([
            'garage_id' => $job->garage_id,
            'job_id' => $job->id,
            'revision_number' => $nextRevision,
        ]);

        $this->assertSame(2, $revision2->revision_number);
        $this->assertSame(2, $job->estimates()->count());
        $this->assertFalse($revision2->hasCustomerResponse());

        $job->refresh();
        $this->assertSame($revision2->id, $job->currentEstimate->id);
    }

    public function test_controller_update_returns_validation_error_after_customer_response(): void
    {
        // planning.md L175 — once customer responded, mechanic cannot mutate the estimate.
        // Controller must surface this as a validation error (302 + session errors), not a 500.
        $estimate = $this->makeEstimateWithLineItem(LineItem::STATUS_APPROVED);
        $job = $estimate->repairJob;
        $garage = $job->garage;

        $user = User::factory()->create();
        Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'user_id' => $user->id,
            'role' => Mechanic::ROLE_GARAGE_ADMIN,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->put(route('jobs.estimates.update', ['job' => $job->id, 'estimate' => $estimate->id]), [
                'sent_at' => now()->toIso8601String(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('estimate');
    }

    public function test_new_revision_is_independently_editable_even_when_previous_is_frozen(): void
    {
        $frozen = $this->makeEstimateWithLineItem(LineItem::STATUS_APPROVED, revisionNumber: 1);
        $job = $frozen->repairJob;

        $revision2 = Estimate::withoutGlobalScopes()->create([
            'garage_id' => $job->garage_id,
            'job_id' => $job->id,
            'revision_number' => 2,
        ]);

        $service = new EstimateService;
        $service->update($revision2, ['sent_at' => now()]);

        $this->assertNotNull($revision2->fresh()->sent_at);
    }

    private function makeEstimateWithLineItem(string $lineItemStatus, int $revisionNumber = 1): Estimate
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

        $job = RepairJob::withoutGlobalScopes()->forceCreate([
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_AWAITING_APPROVAL,
        ]);

        $estimate = Estimate::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'revision_number' => $revisionNumber,
        ]);

        LineItem::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'estimate_id' => $estimate->id,
            'description' => 'Brake pads',
            'price' => 120.00,
            'status' => $lineItemStatus,
        ]);

        return $estimate;
    }
}

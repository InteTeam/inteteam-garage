<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\Estimate;
use App\Models\Garage;
use App\Models\LineItem;
use App\Models\RepairJob;
use App\Models\Vehicle;
use App\Services\ApprovalEventService;
use App\Services\CrmApiService;
use App\Services\CrmPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PaymentAmountTest extends TestCase
{
    use RefreshDatabase;

    public function test_amount_is_zero_when_no_estimate_exists(): void
    {
        $job = $this->makeJob();
        $service = $this->makeService();

        $this->assertSame(0.0, $service->calculateAmount($job));
    }

    public function test_amount_is_zero_when_all_line_items_pending(): void
    {
        $job = $this->makeJob();
        $this->makeLineItems($job, [
            ['price' => 100.00, 'status' => LineItem::STATUS_PENDING],
            ['price' => 50.00, 'status' => LineItem::STATUS_PENDING],
        ]);
        $service = $this->makeService();

        $this->assertSame(0.0, $service->calculateAmount($job));
    }

    public function test_amount_sums_only_approved_line_items(): void
    {
        $job = $this->makeJob();
        $this->makeLineItems($job, [
            ['price' => 120.00, 'status' => LineItem::STATUS_APPROVED],
            ['price' => 80.00, 'status' => LineItem::STATUS_PENDING],
            ['price' => 200.00, 'status' => LineItem::STATUS_DECLINED],
            ['price' => 60.50, 'status' => LineItem::STATUS_APPROVED],
        ]);
        $service = $this->makeService();

        $this->assertEqualsWithDelta(180.50, $service->calculateAmount($job), 0.0001);
    }

    public function test_declined_items_excluded_even_when_only_status_present(): void
    {
        $job = $this->makeJob();
        $this->makeLineItems($job, [
            ['price' => 999.99, 'status' => LineItem::STATUS_DECLINED],
        ]);
        $service = $this->makeService();

        $this->assertSame(0.0, $service->calculateAmount($job));
    }

    private function makeJob(): RepairJob
    {
        $garage = Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => true,
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
            'state' => RepairJob::STATE_APPROVED,
        ]);
    }

    /**
     * @param  list<array{price: float, status: string}>  $items
     */
    private function makeLineItems(RepairJob $job, array $items): void
    {
        $estimate = Estimate::withoutGlobalScopes()->create([
            'garage_id' => $job->garage_id,
            'job_id' => $job->id,
            'revision_number' => 1,
            'sent_at' => now(),
        ]);

        foreach ($items as $i => $row) {
            LineItem::withoutGlobalScopes()->create([
                'garage_id' => $job->garage_id,
                'estimate_id' => $estimate->id,
                'description' => 'Item ' . ($i + 1),
                'price' => $row['price'],
                'status' => $row['status'],
            ]);
        }
    }

    private function makeService(): CrmPaymentService
    {
        return new CrmPaymentService(
            app(CrmApiService::class),
            new ApprovalEventService,
        );
    }
}

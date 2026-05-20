<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApprovalEvent;
use App\Models\Estimate;
use App\Models\LineItem;
use App\Models\RepairJob;
use Illuminate\Database\Eloquent\Collection;

final class CrmPaymentService
{
    public function __construct(
        private readonly CrmApiService $crmApiService,
        private readonly ApprovalEventService $approvalEventService
    ) {}

    public function calculateAmount(RepairJob $job): float
    {
        return (float) ($job->currentEstimate
            ?->lineItems()
            ->where('status', LineItem::STATUS_APPROVED)
            ->sum('price') ?? 0);
    }

    public function requestPayment(RepairJob $job): string
    {
        /** @var Estimate $estimate */
        $estimate = $job->currentEstimate;

        /** @var Collection<int, LineItem> $approvedItems */
        $approvedItems = $estimate->lineItems()
            ->where('status', LineItem::STATUS_APPROVED)
            ->get();

        $total = $approvedItems->sum('price');

        $reference = $this->crmApiService->createPaymentRequest(
            jobId: $job->id,
            lineItems: $approvedItems->map(fn (LineItem $item) => [
                'description' => $item->description,
                'price' => $item->price,
            ])->toArray(),
            total: $total,
            crmCustomerId: $job->vehicle->crm_customer_id,
        );

        $job->update(['payment_reference' => $reference]);

        $this->approvalEventService->recordBySystem($job, ApprovalEvent::EVENT_PAYMENT_REQUESTED, [
            'reference' => $reference,
            'total' => $total,
            'item_count' => $approvedItems->count(),
        ]);

        return $reference;
    }

    public function confirmPayment(RepairJob $job, string $reference): void
    {
        $job->update(['payment_confirmed_at' => now()]);

        $this->approvalEventService->recordBySystem($job, ApprovalEvent::EVENT_PAYMENT_CONFIRMED, [
            'reference' => $reference,
        ]);
    }
}

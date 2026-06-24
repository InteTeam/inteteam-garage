<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApprovalEvent;
use App\Models\Estimate;
use App\Models\LineItem;
use App\Models\RepairJob;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

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

    /**
     * Payment history for the customer portal — thin pass-through to the CRM
     * service. Lives here (not directly on the controller) so future
     * formatting/normalisation has a single landing spot.
     *
     * @return list<array<string, mixed>>
     */
    public function historyForCustomer(string $crmCustomerId): array
    {
        return $this->crmApiService->listPaymentsForCustomer($crmCustomerId);
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

    /**
     * Returns true when this call actually confirmed the payment; false when
     * the job was already confirmed (idempotent no-op for CRM webhook retries).
     *
     * The append-only audit log is the product thesis — duplicate
     * `EVENT_PAYMENT_CONFIRMED` rows (or duplicate staff notifications) on a
     * retried webhook would break that contract.
     */
    public function confirmPayment(RepairJob $job, string $reference): bool
    {
        if ($job->payment_confirmed_at !== null) {
            Log::info('payment_confirmed webhook ignored — already confirmed', [
                'job_id' => $job->id,
                'reference' => $reference,
                'confirmed_at' => $job->payment_confirmed_at->toIso8601String(),
            ]);

            return false;
        }

        $job->update(['payment_confirmed_at' => now()]);

        $this->approvalEventService->recordBySystem($job, ApprovalEvent::EVENT_PAYMENT_CONFIRMED, [
            'reference' => $reference,
        ]);

        return true;
    }
}

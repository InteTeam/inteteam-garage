<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LineItem\StoreLineItemRequest;
use App\Models\Estimate;
use App\Models\LineItem;
use App\Models\RepairJob;
use App\Services\EstimateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class LineItemController extends Controller
{
    public function __construct(
        private readonly EstimateService $estimateService,
    ) {}

    public function store(StoreLineItemRequest $request, RepairJob $job, Estimate $estimate): RedirectResponse
    {
        $this->authorize('create', LineItem::class);
        $this->ensureEstimateBelongsToJob($estimate, $job);
        abort_unless($estimate->garage_id === session('current_garage_id'), 404);

        /** @var array{description: string, price: float|string} $data */
        $data = $request->validated();

        try {
            $this->estimateService->addLineItem($estimate, $data['description'], (float) $data['price']);
        } catch (RuntimeException $e) {
            // Surface the seal-on-send / customer-response rejection as a
            // form-field validation error so AddLineItemForm only has one
            // error source (form.errors), matching EstimateController::update.
            throw ValidationException::withMessages(['description' => $e->getMessage()]);
        }

        return back()->with(['alert' => 'The line item was added.', 'type' => 'success']);
    }

    private function ensureEstimateBelongsToJob(Estimate $estimate, RepairJob $job): void
    {
        abort_if($estimate->job_id !== $job->id, 404, 'Estimate does not belong to this job.');
    }
}

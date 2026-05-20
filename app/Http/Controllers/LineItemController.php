<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LineItem\StoreLineItemRequest;
use App\Http\Requests\LineItem\UpdateLineItemRequest;
use App\Models\LineItem;
use App\Services\LineItemService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class LineItemController extends Controller
{
    public function __construct(
        private readonly LineItemService $lineItemService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', LineItem::class);

        return Inertia::render('LineItems/Index', [
            'lineItems' => $this->lineItemService->getAll(),
        ]);
    }

    public function store(StoreLineItemRequest $request): RedirectResponse
    {
        $this->authorize('create', LineItem::class);

        $this->lineItemService->create($request->validated());

        return redirect()->route('line-items.index')
            ->with(['alert' => 'LineItem created.', 'type' => 'success']);
    }

    public function show(LineItem $lineItem): Response
    {
        $this->authorize('view', $lineItem);

        return Inertia::render('LineItems/Show', [
            'lineItem' => $lineItem,
        ]);
    }

    public function update(UpdateLineItemRequest $request, LineItem $lineItem): RedirectResponse
    {
        $this->authorize('update', $lineItem);

        $this->lineItemService->update($lineItem, $request->validated());

        return redirect()->route('line-items.index')
            ->with(['alert' => 'LineItem updated.', 'type' => 'success']);
    }

    public function destroy(LineItem $lineItem): RedirectResponse
    {
        $this->authorize('delete', $lineItem);

        $this->lineItemService->delete($lineItem);

        return redirect()->route('line-items.index')
            ->with(['alert' => 'LineItem deleted.', 'type' => 'success']);
    }
}

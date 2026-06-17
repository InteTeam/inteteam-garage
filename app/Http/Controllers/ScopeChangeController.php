<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ScopeChange\StoreScopeChangeRequest;
use App\Models\RepairJob;
use App\Services\CrmNotificationService;
use App\Services\ScopeChangeService;
use Illuminate\Http\RedirectResponse;

final class ScopeChangeController extends Controller
{
    public function __construct(
        private readonly ScopeChangeService $scopeChangeService,
        private readonly CrmNotificationService $notifications,
    ) {}

    public function store(StoreScopeChangeRequest $request, RepairJob $job): RedirectResponse
    {
        $this->authorize('update', $job);

        /** @var array{line_items: list<array{description: string, price: numeric}>} $validated */
        $validated = $request->validated();

        $this->scopeChangeService->create(
            $job,
            $validated['line_items'],
            (string) $request->user()->id,
        );

        $this->notifications->notifyScopeChange($job->fresh());

        return back()->with(['alert' => 'The scope change was sent to the customer for approval.', 'type' => 'success']);
    }
}

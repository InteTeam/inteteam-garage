<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RepairJob;
use App\Services\CrmNotificationService;
use App\Services\ScopeChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ScopeChangeController extends Controller
{
    public function __construct(
        private readonly ScopeChangeService $scopeChangeService,
        private readonly CrmNotificationService $notifications,
    ) {}

    public function store(Request $request, RepairJob $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate([
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $this->scopeChangeService->create(
            $job,
            $validated['line_items'],
            (string) $request->user()->id,
        );

        $this->notifications->notifyScopeChange($job->fresh());

        return back()->with(['alert' => 'The scope change was sent to the customer for approval.', 'type' => 'success']);
    }
}

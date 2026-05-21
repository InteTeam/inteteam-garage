<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\RepairJob;
use App\Services\CrmPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PortalPaymentController extends Controller
{
    public function __construct(
        private readonly CrmPaymentService $paymentService,
    ) {}

    public function show(Request $request, string $token): Response
    {
        /** @var RepairJob $job */
        $job = $request->attributes->get('portal_job');

        $job->load(['currentEstimate.lineItems', 'garage']);

        $amount = $this->paymentService->calculateAmount($job);

        return Inertia::render('Portal/Payment', [
            'job' => $job,
            'token' => $token,
            'amount' => $amount,
            'paymentConfirmed' => $job->payment_confirmed_at !== null,
        ]);
    }

    public function request(Request $request, string $token): RedirectResponse
    {
        /** @var RepairJob $job */
        $job = $request->attributes->get('portal_job');

        if (! $job->garage->online_payment_enabled) {
            abort(422, 'Online payment is not enabled for this garage.');
        }

        if ($job->payment_confirmed_at !== null) {
            return back()->withErrors(['payment' => 'Payment has already been confirmed.']);
        }

        $this->paymentService->requestPayment($job);

        return back()->with(['alert' => 'Payment request initiated.', 'type' => 'success']);
    }
}

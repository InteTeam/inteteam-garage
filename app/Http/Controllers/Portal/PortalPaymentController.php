<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\RepairJob;
use App\Services\CrmPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

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

        // Unlike notifications, a failed payment request must be visible: the
        // customer is waiting on a payment link. But it must surface as a flash
        // error they can act on, not the raw 500 the uncaught RuntimeException
        // from `CrmApiService::assertSuccess` used to produce.
        try {
            $this->paymentService->requestPayment($job);
        } catch (Throwable $e) {
            Log::error('CRM payment request failed', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'payment' => 'The payment request could not be created. Please try again shortly or contact the garage.',
            ]);
        }

        return back()->with(['alert' => 'The payment request was initiated.', 'type' => 'success']);
    }
}

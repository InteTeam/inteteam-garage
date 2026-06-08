<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\RepairJob;
use App\Services\CrmPaymentService;
use App\Services\CrmStaffNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly CrmPaymentService $paymentService,
        private readonly CrmStaffNotificationService $staffNotifications,
    ) {}

    public function handle(Request $request): Response
    {
        $secret = config('services.garage.internal_secret');

        if ($request->header('X-Internal-Secret') !== $secret) {
            abort(401, 'Unauthorized webhook call.');
        }

        $validated = $request->validate([
            'job_id' => ['required', 'string'],
            'payment_reference' => ['required', 'string'],
        ]);

        $job = RepairJob::withoutGlobalScopes()->findOrFail($validated['job_id']);

        $this->paymentService->confirmPayment($job, $validated['payment_reference']);

        $job->load('mechanics.user');
        foreach ($job->mechanics as $mechanic) {
            $this->staffNotifications->notifyPaymentConfirmedToMechanic($job, $mechanic);
        }

        return response()->noContent();
    }
}

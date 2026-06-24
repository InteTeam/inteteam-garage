<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\RepairJob;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class JobController extends Controller
{
    public function show(string $jobId): Response
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $job = $this->locateOwnedJob($customer, $jobId);

        // Customer guard has no current_garage_id session; every scoped
        // relation must drop the global scope or it returns null/empty.
        $unscope = fn ($q) => $q->withoutGlobalScopes();
        $job->load([
            'vehicle' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'registration', 'make', 'model', 'year', 'colour'),
            'garage' => fn ($q) => $q->select('id', 'name', 'online_payment_enabled'),
            'currentEstimate' => $unscope,
            'currentEstimate.lineItems' => $unscope,
            'stateTransitions' => $unscope,
            'approvalEvents' => $unscope,
            'stages' => $unscope,
            'stages.media' => $unscope,
            'notificationPreference' => $unscope,
        ]);

        return Inertia::render('Account/JobShow', [
            'job' => $job,
        ]);
    }

    /**
     * Resolve a job that belongs to the authenticated customer or 404. The
     * lookup deliberately bypasses tenant scopes (customer jobs can span
     * garages) but enforces ownership via vehicle.crm_customer_id.
     */
    private function locateOwnedJob(Customer $customer, string $jobId): RepairJob
    {
        if (! $customer->isLinkedToCrm()) {
            abort(404);
        }

        // Resolve the job unscoped, then verify ownership via the vehicle row
        // directly. whereHas + withoutGlobalScopes inside the closure is
        // unreliable when the related model carries its own global scopes.
        $job = RepairJob::withoutGlobalScopes()
            ->where('id', $jobId)
            ->firstOrFail();

        $ownsVehicle = Vehicle::withoutGlobalScopes()
            ->where('id', $job->vehicle_id)
            ->where('crm_customer_id', $customer->crm_customer_id)
            ->exists();

        abort_unless($ownsVehicle, 404);

        return $job;
    }
}

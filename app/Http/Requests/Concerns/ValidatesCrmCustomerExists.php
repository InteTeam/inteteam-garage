<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Services\CrmApiService;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

trait ValidatesCrmCustomerExists
{
    /**
     * Validate that the CRM customer exists. 404 fails the rule.
     * Network/server errors are logged and allowed through so a CRM outage
     * does not block vehicle write operations (graceful degradation —
     * see playbook Lesson #21).
     *
     * Gated on `CrmApiService::canLookUpCustomerById()`. The rule reads a 404 as
     * "no such customer", but Laravel also answers 404 for a route that does not
     * exist — and `GET /api/v1/internal/customers/{id}` is not deployed on the CRM
     * (see docs/features/crm-integration/contract.md). So with `CRM_API_URL` set,
     * this rule rejected *every* customer id, including correct ones, and vehicle
     * creation was impossible: "The customer was not found in CRM." on every
     * submit. Skipping the check when the capability is off keeps the form usable
     * and loses nothing — the id is still required and still recorded.
     */
    protected function crmCustomerExistsRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            $crm = app(CrmApiService::class);

            if (! $crm->canLookUpCustomerById()) {
                Log::debug('CRM customer existence check skipped — lookup-by-id capability disabled', [
                    'crm_customer_id' => $value,
                ]);

                return;
            }

            try {
                $crm->getCustomer($value);
            } catch (Throwable $e) {
                if (str_contains($e->getMessage(), 'CRM API error [404]')) {
                    $fail('The customer was not found in CRM.');

                    return;
                }

                Log::warning('CRM customer lookup failed during vehicle validation — allowing through', [
                    'crm_customer_id' => $value,
                    'error' => $e->getMessage(),
                ]);
            }
        };
    }
}

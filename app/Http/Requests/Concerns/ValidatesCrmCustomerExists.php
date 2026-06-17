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
     */
    protected function crmCustomerExistsRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            try {
                app(CrmApiService::class)->getCustomer($value);
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

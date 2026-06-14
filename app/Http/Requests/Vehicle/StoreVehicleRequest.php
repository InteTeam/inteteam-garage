<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicle;

use App\Services\CrmApiService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Throwable;

final class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $nextYear = (int) date('Y') + 1;

        return [
            'crm_customer_id' => [
                'required',
                'string',
                'max:255',
                $this->crmCustomerExistsRule(),
            ],
            'registration' => ['required', 'string', 'max:255'],
            'make' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', "between:1900,{$nextYear}"],
            'colour' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Validate the customer exists in CRM. 404 fails the rule.
     * Network/server errors are logged and allowed through so a CRM outage
     * does not block vehicle creation (graceful degradation).
     */
    private function crmCustomerExistsRule(): Closure
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

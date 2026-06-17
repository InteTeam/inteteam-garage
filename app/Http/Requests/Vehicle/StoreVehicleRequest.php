<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicle;

use App\Http\Requests\Concerns\ValidatesCrmCustomerExists;
use Illuminate\Foundation\Http\FormRequest;

final class StoreVehicleRequest extends FormRequest
{
    use ValidatesCrmCustomerExists;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
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
}

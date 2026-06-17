<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicle;

use App\Http\Requests\Concerns\ValidatesCrmCustomerExists;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateVehicleRequest extends FormRequest
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
                'sometimes',
                'string',
                'max:255',
                $this->crmCustomerExistsRule(),
            ],
            'registration' => ['sometimes', 'string', 'max:255'],
            'make' => ['sometimes', 'string', 'max:255'],
            'model' => ['sometimes', 'string', 'max:255'],
            'year' => ['sometimes', 'integer', "between:1900,{$nextYear}"],
            'colour' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}

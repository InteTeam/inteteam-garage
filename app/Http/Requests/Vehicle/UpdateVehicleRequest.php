<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicle;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'crm_customer_id' => ['sometimes', 'string', 'max:255'],
            'registration' => ['sometimes', 'string', 'max:255'],
            'make' => ['sometimes', 'string', 'max:255'],
            'model' => ['sometimes', 'string', 'max:255'],
            'year' => ['sometimes', 'integer'],
            'colour' => ['sometimes', 'string', 'max:255'],
        ];
    }
}

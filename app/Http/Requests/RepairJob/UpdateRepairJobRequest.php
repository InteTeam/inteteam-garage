<?php

declare(strict_types=1);

namespace App\Http\Requests\RepairJob;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateRepairJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'garage_id' => ['sometimes', 'ulid'],
            'vehicle_id' => ['sometimes', 'ulid'],
            'state' => ['sometimes', 'string', 'max:255'],
            'payment_reference' => ['sometimes', 'string', 'max:255'],
            'payment_confirmed_at' => ['sometimes', 'datetime'],
        ];
    }
}

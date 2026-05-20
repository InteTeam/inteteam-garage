<?php

declare(strict_types=1);

namespace App\Http\Requests\RepairJob;

use Illuminate\Foundation\Http\FormRequest;

final class StoreRepairJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'garage_id' => ['required', 'ulid'],
            'vehicle_id' => ['required', 'ulid'],
            'state' => ['required', 'string', 'max:255'],
            'payment_reference' => ['required', 'string', 'max:255'],
            'payment_confirmed_at' => ['required', 'datetime'],
        ];
    }
}

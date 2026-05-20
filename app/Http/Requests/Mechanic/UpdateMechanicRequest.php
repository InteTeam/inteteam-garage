<?php

declare(strict_types=1);

namespace App\Http\Requests\Mechanic;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateMechanicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'garage_id' => ['sometimes', 'ulid'],
            'user_id' => ['sometimes', 'ulid'],
            'role' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

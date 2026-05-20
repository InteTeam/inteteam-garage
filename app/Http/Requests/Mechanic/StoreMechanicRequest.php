<?php

declare(strict_types=1);

namespace App\Http\Requests\Mechanic;

use Illuminate\Foundation\Http\FormRequest;

final class StoreMechanicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'garage_id' => ['required', 'ulid'],
            'user_id' => ['required', 'ulid'],
            'role' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}

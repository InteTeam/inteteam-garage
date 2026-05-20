<?php

declare(strict_types=1);

namespace App\Http\Requests\LineItem;

use Illuminate\Foundation\Http\FormRequest;

final class StoreLineItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'garage_id' => ['required', 'ulid'],
            'estimate_id' => ['required', 'ulid'],
            'description' => ['required', 'string', 'max:255'],
            'price' => ['required', 'decimal'],
            'status' => ['required', 'string', 'max:255'],
        ];
    }
}

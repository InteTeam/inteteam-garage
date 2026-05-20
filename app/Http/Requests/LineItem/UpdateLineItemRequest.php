<?php

declare(strict_types=1);

namespace App\Http\Requests\LineItem;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateLineItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'garage_id' => ['sometimes', 'ulid'],
            'estimate_id' => ['sometimes', 'ulid'],
            'description' => ['sometimes', 'string', 'max:255'],
            'price' => ['sometimes', 'decimal'],
            'status' => ['sometimes', 'string', 'max:255'],
        ];
    }
}

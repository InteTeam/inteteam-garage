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

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:1', 'max:500'],
            'price' => ['required', 'numeric', 'gt:0', 'max:99999.99'],
        ];
    }
}

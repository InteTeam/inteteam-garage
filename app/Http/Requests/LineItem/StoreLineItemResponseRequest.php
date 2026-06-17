<?php

declare(strict_types=1);

namespace App\Http\Requests\LineItem;

use Illuminate\Foundation\Http\FormRequest;

final class StoreLineItemResponseRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:2000'],
            'translated_message' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}

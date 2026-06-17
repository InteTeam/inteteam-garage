<?php

declare(strict_types=1);

namespace App\Http\Requests\ScopeChange;

use Illuminate\Foundation\Http\FormRequest;

final class StoreScopeChangeRequest extends FormRequest
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
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitHandoverRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.line_item_id' => ['required', 'ulid'],
            'items.*.accepted' => ['required', 'boolean'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

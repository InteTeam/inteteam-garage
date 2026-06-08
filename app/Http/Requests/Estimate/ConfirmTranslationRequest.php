<?php

declare(strict_types=1);

namespace App\Http\Requests\Estimate;

use Illuminate\Foundation\Http\FormRequest;

final class ConfirmTranslationRequest extends FormRequest
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
            'confirmations' => ['required', 'array', 'min:1'],
            'confirmations.*.id' => ['required', 'string'],
            'confirmations.*.translated_text' => ['required', 'string'],
            'confirmations.*.llm_raw_text' => ['required', 'string'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Mechanic;

use App\Services\TranslationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateMechanicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('locale') === '') {
            $this->merge(['locale' => null]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'role' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', 'nullable', Rule::in(TranslationService::SUPPORTED_LOCALES)],
            'channel_toggle_allowed' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}

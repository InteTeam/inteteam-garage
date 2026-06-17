<?php

declare(strict_types=1);

namespace App\Http\Requests\Mechanic;

use App\Models\Mechanic;
use App\Services\TranslationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreMechanicRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', Rule::in(Mechanic::ROLES)],
            'is_active' => ['required', 'boolean'],
            'locale' => ['nullable', Rule::in(TranslationService::SUPPORTED_LOCALES)],
            'channel_toggle_allowed' => ['nullable', 'boolean'],
        ];
    }
}

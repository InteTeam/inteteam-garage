<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicle;

use App\Enums\ComplianceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreComplianceRecordRequest extends FormRequest
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
            'type' => ['required', Rule::enum(ComplianceType::class)],
            'expires_on' => ['required', 'date', 'after_or_equal:1990-01-01'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}

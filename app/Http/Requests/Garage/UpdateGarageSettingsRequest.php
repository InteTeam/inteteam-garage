<?php

declare(strict_types=1);

namespace App\Http\Requests\Garage;

use App\Enums\ComplianceRecipient;
use App\Enums\ComplianceType;
use App\Models\Garage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateGarageSettingsRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'default_notification_channel' => ['required', Rule::in(Garage::CHANNELS)],
            'online_payment_enabled' => ['boolean'],
            'locale' => ['required', 'string', 'in:en,pl'],
            'staff_channel_toggle_default' => ['sometimes', 'boolean'],
            'timeout_reminder_policy' => ['sometimes', Rule::in(Garage::TIMEOUT_POLICIES)],
            'working_hours' => ['nullable', 'array'],
            'working_hours.*.open' => ['required_with:working_hours.*.close', 'string', 'date_format:H:i'],
            'working_hours.*.close' => ['required_with:working_hours.*.open', 'string', 'date_format:H:i'],
            'compliance_reminders_enabled' => ['sometimes', 'boolean'],
            // Channel null = inherit garage default channel.
            'compliance_reminders_channel' => ['sometimes', 'nullable', Rule::in(Garage::CHANNELS)],
            // Poka-Yoke: enabling reminders requires ≥1 window AND ≥1 type.
            // `sometimes` would short-circuit the entire chain when the field is absent,
            // so `Rule::requiredIf` would never fire — use requiredIf directly instead.
            'compliance_reminders_windows' => [
                Rule::requiredIf(fn () => (bool) $this->boolean('compliance_reminders_enabled')),
                'array',
                'min:1',
            ],
            'compliance_reminders_windows.*' => ['integer', 'between:1,90'],
            'compliance_reminders_recipient' => ['sometimes', Rule::enum(ComplianceRecipient::class)],
            'compliance_reminders_types' => [
                Rule::requiredIf(fn () => (bool) $this->boolean('compliance_reminders_enabled')),
                'array',
                'min:1',
            ],
            'compliance_reminders_types.*' => [Rule::enum(ComplianceType::class)],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\NotificationPreference;

use Illuminate\Foundation\Http\FormRequest;

final class StoreNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'garage_id' => ['required', 'ulid'],
            'job_id' => ['required', 'ulid'],
            'channel' => ['required', 'string', 'max:255'],
            'set_by' => ['required', 'string', 'max:255'],
        ];
    }
}

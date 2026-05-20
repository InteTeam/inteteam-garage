<?php

declare(strict_types=1);

namespace App\Http\Requests\Garage;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateGarageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'online_payment_enabled' => ['sometimes', 'boolean'],
            'default_notification_channel' => ['sometimes', 'string', 'max:255'],
            'locale' => ['sometimes', 'string', 'max:255'],
        ];
    }
}

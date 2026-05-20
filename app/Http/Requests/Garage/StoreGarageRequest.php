<?php

declare(strict_types=1);

namespace App\Http\Requests\Garage;

use Illuminate\Foundation\Http\FormRequest;

final class StoreGarageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'online_payment_enabled' => ['required', 'boolean'],
            'default_notification_channel' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string', 'max:255'],
        ];
    }
}

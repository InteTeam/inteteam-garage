<?php

declare(strict_types=1);

namespace App\Http\Requests\JobStage;

use Illuminate\Foundation\Http\FormRequest;

final class StoreJobStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer'],
            'locked_at' => ['nullable', 'date'],
        ];
    }
}

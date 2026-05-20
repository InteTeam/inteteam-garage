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
            'garage_id' => ['required', 'ulid'],
            'job_id' => ['required', 'ulid'],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer'],
            'locked_at' => ['required', 'datetime'],
        ];
    }
}

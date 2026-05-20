<?php

declare(strict_types=1);

namespace App\Http\Requests\JobStage;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateJobStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'garage_id' => ['sometimes', 'ulid'],
            'job_id' => ['sometimes', 'ulid'],
            'name' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer'],
            'locked_at' => ['sometimes', 'datetime'],
        ];
    }
}

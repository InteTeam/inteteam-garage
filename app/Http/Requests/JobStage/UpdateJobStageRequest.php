<?php

declare(strict_types=1);

namespace App\Http\Requests\JobStage;

use App\Models\JobStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateJobStageRequest extends FormRequest
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
            'name' => ['sometimes', Rule::in(JobStage::STAGES)],
            'sort_order' => ['sometimes', 'integer'],
            'locked_at' => ['nullable', 'date'],
        ];
    }
}

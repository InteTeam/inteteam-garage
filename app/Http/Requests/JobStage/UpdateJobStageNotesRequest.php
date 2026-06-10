<?php

declare(strict_types=1);

namespace App\Http\Requests\JobStage;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateJobStageNotesRequest extends FormRequest
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
            'notes' => ['present', 'nullable', 'string', 'max:5000'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\JobStage;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateJobStageNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced in the controller via RepairJobPolicy::update
        // and the explicit Mechanic-presence guard in JobStageController::updateNotes.
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

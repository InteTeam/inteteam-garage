<?php

declare(strict_types=1);

namespace App\Http\Requests\RepairJob;

use App\Models\RepairJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransitionJobRequest extends FormRequest
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
            'state' => ['required', Rule::in(RepairJob::STATES)],
        ];
    }
}

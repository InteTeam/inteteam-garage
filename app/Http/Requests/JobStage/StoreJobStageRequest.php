<?php

declare(strict_types=1);

namespace App\Http\Requests\JobStage;

use App\Models\JobStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreJobStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * sort_order is intentionally NOT accepted from the client — it is
     * derived server-side from JobStage::STAGES so the canonical order
     * cannot be tampered with by a hand-crafted POST.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', Rule::in(JobStage::STAGES)],
            'locked_at' => ['nullable', 'date'],
        ];
    }
}

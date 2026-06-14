<?php

declare(strict_types=1);

namespace App\Http\Requests\RepairJob;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $garageId = session('current_garage_id');

        return [
            'vehicle_id' => [
                'required',
                'string',
                Rule::exists('vehicles', 'id')->where('garage_id', $garageId),
            ],
            // Poka-Yoke (planning.md:55) — every job must have at least one mechanic
            // assigned at creation time to prevent orphaned jobs that no one owns.
            'mechanic_ids' => ['required', 'array', 'min:1'],
            'mechanic_ids.*' => [
                'string',
                Rule::exists('mechanics', 'id')
                    ->where('garage_id', $garageId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}

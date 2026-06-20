<?php

declare(strict_types=1);

namespace App\Http\Requests\RepairJob;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SyncJobMechanicsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $garageId = session('current_garage_id');

        return [
            'mechanic_ids' => ['present', 'array'],
            'mechanic_ids.*' => [
                'string',
                Rule::exists('mechanics', 'id')
                    ->where('garage_id', $garageId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}

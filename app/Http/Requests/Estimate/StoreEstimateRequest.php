<?php

declare(strict_types=1);

namespace App\Http\Requests\Estimate;

use Illuminate\Foundation\Http\FormRequest;

final class StoreEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}

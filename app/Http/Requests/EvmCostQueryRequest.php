<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EvmCostQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Preferred query param for historical "as-of" computations.
            'as_of' => ['nullable', 'date_format:Y-m-d'],
            // Backward-compatible alias (deprecated).
            'date' => ['nullable', 'date_format:Y-m-d'],
            'baseline_id' => ['nullable', 'integer', 'exists:project_baselines,id'],
        ];
    }
}

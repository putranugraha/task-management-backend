<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectBaselineStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => 'required|exists:projects,id',
            'baseline_name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('project_baselines', 'baseline_name')->where(fn ($q) => $q->where('project_id', $this->input('project_id'))),
            ],
            'taken_at' => 'required|date',
            'note' => 'sometimes|nullable|string',
            'start_planned_base' => ['nullable', 'date'],
            'end_planned_base' => ['nullable', 'date', 'after_or_equal:start_planned_base'],
            'value_amount_base' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}


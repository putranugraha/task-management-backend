<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskBaselineStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'baseline_id' => 'required|exists:project_baselines,id',
            'task_id' => [
                'required',
                'exists:tasks,id',
                Rule::unique('task_baselines', 'task_id')->where(fn ($q) => $q->where('baseline_id', $this->input('baseline_id'))),
            ],
            'start_planned_base' => 'nullable|date',
            'end_planned_base' => 'nullable|date|after_or_equal:start_planned_base',
            'duration_planned_base' => 'nullable|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
        ];
    }
}


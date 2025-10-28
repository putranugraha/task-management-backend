<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskBaselineStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced via route middleware (auth + permission)
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'baseline_id' => ['nullable', 'integer', 'exists:project_baselines,id'],
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'start_planned_base' => ['nullable', 'date'],
            'end_planned_base' => ['nullable', 'date', 'after_or_equal:start_planned_base'],
            'duration_planned_base' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'planned_effort_hours' => ['nullable', 'numeric', 'min:0'],
        ];

        // Enforce uniqueness only when baseline_id is provided
        if ($this->filled('baseline_id')) {
            $rules['task_id'][] = Rule::unique('task_baselines', 'task_id')
                ->where(fn ($q) => $q->where('baseline_id', $this->input('baseline_id')));
        }

        return $rules;
    }
}


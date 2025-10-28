<?php

namespace App\Http\Requests;

use App\Models\TaskBaseline;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskBaselineUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('task_baseline');
        $baselineId = $this->input('baseline_id') ?? $this->resolveBaselineId($id);

        return [
            'baseline_id' => 'sometimes|required|exists:project_baselines,id',
            'task_id' => [
                'sometimes',
                'required',
                'exists:tasks,id',
                Rule::unique('task_baselines', 'task_id')
                    ->ignore($id)
                    ->where(fn ($q) => $baselineId ? $q->where('baseline_id', $baselineId) : $q),
            ],
            'start_planned_base' => 'sometimes|nullable|date',
            'end_planned_base' => 'sometimes|nullable|date|after_or_equal:start_planned_base',
            'duration_planned_base' => 'sometimes|nullable|integer|min:0',
            'weight' => 'sometimes|nullable|numeric|min:0',
            'planned_effort_hours' => 'sometimes|nullable|numeric|min:0',
        ];
    }

    protected function resolveBaselineId(?int $id): ?int
    {
        if (!$id) {
            return null;
        }

        $baseline = TaskBaseline::find($id);
        return $baseline?->baseline_id;
    }
}


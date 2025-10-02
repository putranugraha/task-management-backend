<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KpiSnapshotUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('kpi_snapshot');
        $projectId = $this->input('project_id');
        $periodId = $this->input('period_id');

        return [
            'project_id' => ['sometimes', 'required', 'exists:projects,id'],
            'period_id' => [
                'sometimes',
                'required',
                'exists:reporting_periods,id',
                Rule::unique('kpi_snapshots', 'period_id')
                    ->ignore($id)
                    ->where(function ($q) use ($projectId) {
                        return $projectId ? $q->where('project_id', $projectId) : $q;
                    }),
            ],
            'tasks_total' => ['sometimes', 'required', 'integer', 'min:0'],
            'tasks_done' => ['sometimes', 'required', 'integer', 'min:0', 'lte:tasks_total'],
            'overdue_count' => ['sometimes', 'required', 'integer', 'min:0'],
            'avg_cycle_time_days' => ['sometimes', 'required', 'numeric', 'min:0'],
        ];
    }
}


<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KpiSnapshotStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'exists:projects,id'],
            'period_id' => [
                'required',
                'exists:reporting_periods,id',
                Rule::unique('kpi_snapshots', 'period_id')->where(fn ($q) => $q->where('project_id', $this->input('project_id'))),
            ],
            'tasks_total' => ['required', 'integer', 'min:0'],
            'tasks_done' => ['required', 'integer', 'min:0', 'lte:tasks_total'],
            'overdue_count' => ['required', 'integer', 'min:0'],
            'avg_cycle_time_days' => ['required', 'numeric', 'min:0'],
        ];
    }
}


<?php

namespace App\Http\Requests;

use App\Models\ReportingPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportingPeriodUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('reporting_period');
        $projectId = $this->input('project_id') ?? $this->resolveProjectId($id);

        return [
            'project_id' => 'sometimes|required|exists:projects,id',
            'period_date' => [
                'sometimes',
                'required',
                'date',
                Rule::unique('reporting_periods', 'period_date')
                    ->ignore($id)
                    ->where(fn ($q) => $projectId ? $q->where('project_id', $projectId) : $q),
            ],
            'note' => 'sometimes|nullable|string',
        ];
    }

    protected function resolveProjectId(?int $id): ?int
    {
        if (!$id) {
            return null;
        }

        $period = ReportingPeriod::find($id);
        return $period?->project_id;
    }
}


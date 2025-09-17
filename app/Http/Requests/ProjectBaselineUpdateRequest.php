<?php

namespace App\Http\Requests;

use App\Models\ProjectBaseline;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectBaselineUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $baselineId = $this->route('project_baseline') ?? $this->route('baseline');
        $projectId = $this->input('project_id', $this->resolveProjectId($baselineId));

        return [
            'project_id' => 'sometimes|required|exists:projects,id',
            'baseline_name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('project_baselines', 'baseline_name')
                    ->ignore($baselineId)
                    ->where(fn ($q) => $projectId ? $q->where('project_id', $projectId) : $q),
            ],
            'taken_at' => 'sometimes|required|date',
            'note' => 'sometimes|nullable|string',
        ];
    }

    protected function resolveProjectId($baselineId): ?int
    {
        if (!$baselineId) {
            return null;
        }

        $baseline = ProjectBaseline::find($baselineId);
        return $baseline?->project_id;
    }
}


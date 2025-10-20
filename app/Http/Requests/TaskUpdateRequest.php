<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Task;

class TaskUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $taskId = $this->route('task');
        $existingProjectId = null;
        if ($taskId) {
            $existingProjectId = Task::query()->whereKey($taskId)->value('project_id');
        }

        $projectId = $this->input('project_id', $existingProjectId);

        return [
            'project_id' => 'sometimes|required|exists:projects,id',
            'milestone_id' => [
                'sometimes', 'nullable', 'integer',
                $projectId
                    ? Rule::exists('milestones', 'id')->where(fn ($q) => $q->where('project_id', $projectId))
                    : 'nullable',
            ],
            'title' => 'sometimes|required|string|max:200',
            'description' => 'sometimes|nullable|string',
            'priority' => 'sometimes|required|in:Low,Medium,High,Critical',
            'status' => 'sometimes|required|in:To Do,In Progress,Done,On Hold,Cancelled',
            'start_planned' => 'sometimes|nullable|date',
            'end_planned' => 'sometimes|nullable|date|after_or_equal:start_planned',
            'duration_planned' => 'sometimes|nullable|integer|min:0',
            'start_actual' => 'sometimes|nullable|date',
            'end_actual' => 'sometimes|nullable|date|after_or_equal:start_actual',
            'duration_actual' => 'sometimes|nullable|integer|min:0',
            'percent_complete' => 'sometimes|nullable|integer|min:0|max:100',
            // Optional task assignments payload to sync if provided
            'assignments' => 'sometimes|array',
            'assignments.*.user_id' => 'required|integer|exists:users,id',
            'assignments.*.role_on_task' => 'nullable|string|exists:roles,name',
            'assignments.*.estimated_effort_hours' => 'nullable|integer|min:0|max:10000',
            // Optional task dependencies payload to sync if provided
            'dependencies' => 'sometimes|array',
            'dependencies.*.depends_on_task_id' => 'required|integer|exists:tasks,id|different:task_id',
            'dependencies.*.type' => 'nullable|in:FS,SS,FF,SF',
            'dependencies.*.lag_days' => 'nullable|integer|min:-365|max:365',
        ];
    }
}

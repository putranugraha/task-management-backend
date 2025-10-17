<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => 'required|exists:projects,id',
            'milestone_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('milestones', 'id')->where(fn ($q) => $q->where('project_id', $this->input('project_id'))),
            ],
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'priority' => 'required|in:Low,Medium,High,Critical',
            'status' => 'required|in:To Do,In Progress,Done,On Hold,Cancelled',
            'start_planned' => 'nullable|date',
            'end_planned' => 'nullable|date|after_or_equal:start_planned',
            'duration_planned' => 'nullable|integer|min:0',
            'start_actual' => 'nullable|date',
            'end_actual' => 'nullable|date|after_or_equal:start_actual',
            'duration_actual' => 'nullable|integer|min:0',
            'percent_complete' => 'nullable|integer|min:0|max:100',
            // Optional task assignments payload
            'assignments' => 'sometimes|array',
            'assignments.*.user_id' => 'required|integer|exists:users,id',
            'assignments.*.role_on_task' => 'nullable|string|exists:roles,name',
            'assignments.*.estimated_effort_hours' => 'nullable|integer|min:0|max:10000',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaskAssignmentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_id' => 'sometimes|required|integer|exists:tasks,id',
            'user_id' => 'sometimes|required|integer|exists:users,id',
            'role_on_task' => 'sometimes|required|string|exists:roles,name',
            'estimated_effort_hours' => 'sometimes|nullable|integer|min:0|max:10000',
            'assigned_at' => 'sometimes|nullable|date',
        ];
    }
}


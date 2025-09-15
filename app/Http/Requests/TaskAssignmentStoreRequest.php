<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaskAssignmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_id' => 'required|integer|exists:tasks,id',
            'user_id' => 'required|integer|exists:users,id',
            'role_on_task' => 'required|string|exists:roles,name',
            'estimated_effort_hours' => 'nullable|integer|min:0|max:10000',
            'assigned_at' => 'nullable|date',
        ];
    }
}


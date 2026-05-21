<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaskDependencyStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_id' => 'required|integer|exists:tasks,id',
            'depends_on_task_id' => 'required|integer|exists:tasks,id|different:task_id',
            'type' => 'nullable|in:FS,SS,FF,SF',
            'lag_days' => 'nullable|integer|min:-365|max:365',
        ];
    }
}


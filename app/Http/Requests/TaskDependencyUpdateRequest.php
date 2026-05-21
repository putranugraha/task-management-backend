<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaskDependencyUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_id' => 'sometimes|required|integer|exists:tasks,id',
            'depends_on_task_id' => 'sometimes|required|integer|exists:tasks,id|different:task_id',
            'type' => 'sometimes|nullable|in:FS,SS,FF,SF',
            'lag_days' => 'sometimes|nullable|integer|min:-365|max:365',
        ];
    }
}


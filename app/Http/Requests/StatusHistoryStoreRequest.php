<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StatusHistoryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Mendukung salah satu: task_id atau pasangan entity_type=Task + entity_id
            'task_id' => 'required_without:entity_type|nullable|integer|exists:tasks,id',
            'entity_type' => 'nullable|required_without:task_id|in:Task',
            'entity_id' => 'nullable|required_with:entity_type|integer|exists:tasks,id',

            'from_status' => 'nullable|in:To Do,In Progress,Done,On Hold,Cancelled',
            'to_status' => 'required|in:To Do,In Progress,Done,On Hold,Cancelled',
            'changed_by' => 'nullable|integer|exists:users,id',
            'note' => 'nullable|string',
        ];
    }
}


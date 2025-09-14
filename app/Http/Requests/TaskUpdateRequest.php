<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaskUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => 'sometimes|required|exists:projects,id',
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
        ];
    }
}


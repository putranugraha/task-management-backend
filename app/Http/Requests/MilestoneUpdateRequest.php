<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MilestoneUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => 'sometimes|required|exists:projects,id',
            'name' => 'sometimes|required|string|max:150',
            'due_planned' => 'sometimes|nullable|date',
            'due_actual' => 'sometimes|nullable|date',
            'status' => 'sometimes|required|in:Planned,In Progress,Completed,Overdue,On Hold',
        ];
    }
}


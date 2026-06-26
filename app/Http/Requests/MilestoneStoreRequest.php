<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MilestoneStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => 'required|exists:projects,id',
            'name' => 'required|string|max:150',
            'due_planned' => 'required|date',
            'status' => 'required|in:Planned,In Progress,Completed,Overdue,On Hold',
        ];
    }
}

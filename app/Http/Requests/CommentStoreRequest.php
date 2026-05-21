<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CommentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('entity_type');
        $entityRule = match ($type) {
            'Task' => 'required|integer|exists:tasks,id',
            'Project' => 'required|integer|exists:projects,id',
            'Milestone' => 'required|integer|exists:milestones,id',
            default => 'required|integer',
        };

        return [
            'entity_type' => 'required|in:Task,Project,Milestone',
            'entity_id' => $entityRule,
            'user_id' => 'required|integer|exists:users,id',
            'content' => 'required|string',
        ];
    }
}


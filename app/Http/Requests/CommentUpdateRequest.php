<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CommentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('entity_type');
        $entityRule = match ($type) {
            'Task' => 'sometimes|required|integer|exists:tasks,id',
            'Project' => 'sometimes|required|integer|exists:projects,id',
            'Milestone' => 'sometimes|required|integer|exists:milestones,id',
            default => 'sometimes|required|integer',
        };

        return [
            'entity_type' => 'sometimes|required|in:Task,Project,Milestone',
            'entity_id' => $entityRule,
            'user_id' => 'sometimes|required|integer|exists:users,id',
            'content' => 'sometimes|required|string',
        ];
    }
}


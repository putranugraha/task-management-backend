<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachmentUpdateRequest extends FormRequest
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
            'uploaded_by' => 'sometimes|nullable|integer|exists:users,id',
            'filename' => 'sometimes|required|string',
            'mime' => 'sometimes|nullable|string|max:150',
            'storage_path' => 'sometimes|required|string',
            'size' => 'sometimes|required|integer|min:0',
            'uploaded_at' => 'sometimes|nullable|date',
        ];
    }
}


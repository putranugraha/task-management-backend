<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachmentStoreRequest extends FormRequest
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
            'uploaded_by' => 'nullable|integer|exists:users,id',
            'filename' => 'required|string',
            'mime' => 'nullable|string|max:150',
            'storage_path' => 'required|string',
            'size' => 'required|integer|min:0',
            'uploaded_at' => 'nullable|date',
        ];
    }
}


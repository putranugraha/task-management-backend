<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TimeEntryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_id' => 'sometimes|required|integer|exists:tasks,id',
            'user_id' => 'sometimes|required|integer|exists:users,id',
            'date' => 'sometimes|required|date',
            'hours' => 'sometimes|required|numeric|min:0|max:24',
            'note' => 'sometimes|nullable|string',
        ];
    }
}


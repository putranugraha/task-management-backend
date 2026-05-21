<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TimeEntryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_id' => 'required|integer|exists:tasks,id',
            'user_id' => 'required|integer|exists:users,id',
            'date' => 'required|date',
            'hours' => 'required|numeric|min:0|max:24',
            'note' => 'nullable|string',
            // Optional: capture progress to be embedded into note
            'progress' => 'sometimes|integer|min:0|max:100',
            'percent' => 'sometimes|integer|min:0|max:100',
            'percent_complete' => 'sometimes|integer|min:0|max:100',
        ];
    }
}

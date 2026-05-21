<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaskTimeEntryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'hours' => 'required|numeric|min:0|max:24',
            'note' => 'nullable|string',
            // Optional: allow progress fields similar to TimeEntryStoreRequest
            'progress' => 'sometimes|integer|min:0|max:100',
            'percent' => 'sometimes|integer|min:0|max:100',
            'percent_complete' => 'sometimes|integer|min:0|max:100',
        ];
    }
}


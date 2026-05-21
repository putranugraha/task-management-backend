<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProjectStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'client_name' => 'required|string|max:150',
            'value_amount' => 'nullable|numeric|min:0',
            'scope' => 'nullable|string',
            'objective' => 'nullable|string',
            'division_owner_id' => 'nullable|exists:users,id',
            'start_planned' => 'nullable|date',
            'end_planned' => 'nullable|date|after_or_equal:start_planned',
            'status' => 'required|in:Planned,In Progress,Completed,On Hold,Cancelled',
        ];
    }
}


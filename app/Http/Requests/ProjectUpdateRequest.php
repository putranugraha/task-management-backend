<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProjectUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:150',
            'client_name' => 'sometimes|required|string|max:150',
            'value_amount' => 'sometimes|nullable|numeric|min:0',
            'scope' => 'sometimes|nullable|string',
            'objective' => 'sometimes|nullable|string',
            'division_owner_id' => 'sometimes|nullable|exists:users,id',
            'start_planned' => 'sometimes|nullable|date',
            'end_planned' => 'sometimes|nullable|date|after_or_equal:start_planned',
            'status' => 'sometimes|required|in:Planned,In Progress,Completed,On Hold,Cancelled',
        ];
    }
}


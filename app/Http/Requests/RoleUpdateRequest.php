<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
                'min:3',
                Rule::unique('roles', 'name')->ignore($this->route('role')),
            ],
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name',
            'status' => 'required|in:Aktif,Non Aktif',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150|min:3',
            'email' => 'required|string|email|max:150|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required_with:password|string|min:8|same:password',
            'role' => 'required|string|exists:roles,name',
            'division_id' => 'nullable|exists:divisions,id',
            'job_title' => 'nullable|string|max:150',
            'is_active' => 'sometimes|boolean',
            'status' => 'sometimes|in:Aktif,Non Aktif',
        ];
    }
}


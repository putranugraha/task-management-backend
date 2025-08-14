<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @method \App\Models\User|null route(string $param = null)
 */
class UserUpdateRequest extends FormRequest
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
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . ($this->route('user')?->id ?? ''),
            'password' => 'nullable|string|min:8|confirmed',
            'status' => 'sometimes|required|in:Aktif,Non Aktif',
        ];
    }
}
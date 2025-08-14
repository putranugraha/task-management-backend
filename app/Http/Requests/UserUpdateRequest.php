<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $userId = $this->route('user');
        $user = User::with('roles')->find($userId);

        return [
            'name' => 'sometimes|required|string|max:50|min:3',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:50',
                Rule::unique('users')->ignore($userId),
            ],
            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
            'password_confirmation' => [
                'sometimes',
                'required_with:password',
                'string',
                'min:8',
                'same:password',
            ],
            'role' => [
                'sometimes',
                'required',
                'string',
                Rule::exists('roles', 'name'),
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in(['Aktif', 'Non Aktif']),
                function ($attribute, $value, $fail) use ($user) {
                    if ($user && $user->hasRole('Super Admin') && $value === 'Non Aktif') {
                        $fail('User dengan role Super Admin tidak dapat di-nonaktifkan.');
                    }
                },
            ],
        ];
    }
}
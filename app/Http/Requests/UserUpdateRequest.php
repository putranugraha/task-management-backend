<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user');
        $user = User::with('roles')->find($userId);

        return [
            'name' => 'sometimes|required|string|max:150|min:3',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:150',
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
            'division_id' => 'sometimes|nullable|exists:divisions,id',
            'job_title' => 'sometimes|nullable|string|max:150',
            'is_active' => 'sometimes|boolean',
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



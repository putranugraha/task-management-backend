<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DivisionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Code is auto-generated/uniquified server-side when empty or colliding.
            'code' => 'nullable|string|max:50',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
        ];
    }
}


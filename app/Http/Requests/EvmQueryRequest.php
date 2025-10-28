<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EvmQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'baseline_id' => ['nullable', 'integer', 'exists:project_baselines,id'],
        ];
    }
}


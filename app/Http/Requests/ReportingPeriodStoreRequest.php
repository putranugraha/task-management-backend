<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportingPeriodStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => 'required|exists:projects,id',
            'period_date' => [
                'required',
                'date',
                Rule::unique('reporting_periods', 'period_date')->where(fn ($q) => $q->where('project_id', $this->input('project_id'))),
            ],
            'note' => 'sometimes|nullable|string',
        ];
    }
}


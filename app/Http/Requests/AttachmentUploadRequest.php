<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachmentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // File to be uploaded and attached to an entity (Task in step 1).
            'file' => 'required|file|max:20480', // max ~20MB, adjust as needed
        ];
    }
}


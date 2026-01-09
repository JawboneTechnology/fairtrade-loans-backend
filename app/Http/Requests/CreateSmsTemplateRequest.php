<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSmsTemplateRequest extends FormRequest
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
            'type' => 'required|string|unique:sms_templates,type',
            'name' => 'required|string|max:255',
            'message' => 'required|string',
            'available_variables' => 'sometimes|array',
            'available_variables.*' => 'string',
            'description' => 'sometimes|string|nullable',
            'is_active' => 'sometimes|boolean',
        ];
    }
}

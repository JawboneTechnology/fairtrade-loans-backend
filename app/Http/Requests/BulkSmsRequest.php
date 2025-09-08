<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkSmsRequest extends FormRequest
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
            'message' => 'required|string|max:160',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
            'send_to_all' => 'required|boolean',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'The message field is required.',
            'message.string' => 'The message field must be a string.',
            'message.max' => 'The message field must not exceed 160 characters.',
            'user_ids.array' => 'The user_ids field must be an array.',
            'user_ids.*.integer' => 'The user_ids field must contain only integers.',
            'user_ids.*.exists' => 'The selected user_ids is invalid.',
            'send_to_all.required' => 'The send_to_all field is required.',
            'send_to_all.boolean' => 'The send_to_all field must be a boolean.',
        ];
    }
}

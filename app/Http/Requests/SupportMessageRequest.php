<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupportMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Allow all users to submit support messages
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:500',
            'message' => 'required|string|max:2000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Your name is required.',
            'name.max' => 'Name cannot exceed 255 characters.',
            'email.required' => 'Your email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'subject.required' => 'Subject is required.',
            'subject.max' => 'Subject cannot exceed 500 characters.',
            'message.required' => 'Message content is required.',
            'message.max' => 'Message cannot exceed 2000 characters.',
        ];
    }
}
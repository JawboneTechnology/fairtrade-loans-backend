<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
        $userId = auth()->id();

        return [
            'first_name' => [
                'sometimes',
                'required',
                'string',
                'max:255'
            ],
            'middle_name' => [
                'nullable',
                'string',
                'max:255'
            ],
            'last_name' => [
                'sometimes',
                'required',
                'string',
                'max:255'
            ],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId)
            ],
            'phone_number' => [
                'sometimes',
                'required',
                'string',
                'regex:/^(?:\+254|254|0)[17]\d{8}$/',
                Rule::unique('users', 'phone_number')->ignore($userId)
            ],
            'address' => [
                'nullable',
                'string',
                'max:500'
            ],
            'dob' => [
                'nullable',
                'date',
                'before:today',
                'after:1900-01-01'
            ],
            'gender' => [
                'nullable',
                'string',
                Rule::in(['male', 'female', 'other'])
            ],
            'national_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'national_id')->ignore($userId)
            ],
            'passport_image' => [
                'nullable',
                'string',
                'max:500'
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'first_name.max' => 'First name must not exceed 255 characters.',
            'last_name.required' => 'Last name is required.',
            'last_name.max' => 'Last name must not exceed 255 characters.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already taken.',
            'phone_number.required' => 'Phone number is required.',
            'phone_number.regex' => 'Please provide a valid Kenyan phone number.',
            'phone_number.unique' => 'This phone number is already taken.',
            'address.max' => 'Address must not exceed 500 characters.',
            'dob.date' => 'Please provide a valid date of birth.',
            'dob.before' => 'Date of birth must be in the past.',
            'dob.after' => 'Date of birth must be after 1900-01-01.',
            'gender.in' => 'Gender must be male, female, or other.',
            'national_id.unique' => 'This national ID is already taken.',
            'passport_image.max' => 'Passport image path must not exceed 500 characters.',
        ];
    }
}


<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeProfileRequest extends FormRequest
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
        $employeeId = $this->route('id');

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
                Rule::unique('users', 'email')->ignore($employeeId)
            ],
            'phone_number' => [
                'sometimes',
                'required',
                'string',
                'regex:/^(?:\+254|254|0)[17]\d{8}$/',
                Rule::unique('users', 'phone_number')->ignore($employeeId)
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
                'max:20',
                Rule::unique('users', 'national_id')->ignore($employeeId)
            ],
            'passport_image' => [
                'nullable',
                'string',
                'url'
            ],
            'years_of_employment' => [
                'nullable',
                'integer',
                'min:0',
                'max:100'
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required',
            'first_name.max' => 'First name cannot exceed 255 characters',
            'last_name.required' => 'Last name is required',
            'last_name.max' => 'Last name cannot exceed 255 characters',
            'email.required' => 'Email is required',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email is already taken',
            'phone_number.required' => 'Phone number is required',
            'phone_number.regex' => 'Please provide a valid Kenyan phone number',
            'phone_number.unique' => 'This phone number is already registered',
            'dob.date' => 'Please provide a valid date of birth',
            'dob.before' => 'Date of birth must be in the past',
            'dob.after' => 'Please provide a valid date of birth',
            'gender.in' => 'Gender must be male, female, or other',
            'national_id.unique' => 'This national ID is already registered',
            'passport_image.url' => 'Passport image must be a valid URL',
            'years_of_employment.integer' => 'Years of employment must be a number',
            'years_of_employment.min' => 'Years of employment cannot be negative',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'first name',
            'middle_name' => 'middle name',
            'last_name' => 'last name',
            'phone_number' => 'phone number',
            'dob' => 'date of birth',
            'national_id' => 'national ID',
            'passport_image' => 'passport image',
            'years_of_employment' => 'years of employment',
        ];
    }
}


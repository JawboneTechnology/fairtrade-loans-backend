<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserProfileRequest extends FormRequest
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
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'dob' => 'required|date|before:today',
            'passport_image' => 'nullable|string',
            'gender' => 'required|string|in:male,female,other',
            'years_of_employment' => 'nullable|integer|min:0',
            'salary' => 'required|numeric|min:0',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'The first name field is required.',
            'last_name.required' => 'The last name field is required.',
            'address.required' => 'The address field is required.',
            'dob.required' => 'The date of birth field is required.',
            'dob.before' => 'The date of birth must be a date before today.',
            'gender.required' => 'The gender field is required.',
            'gender.in' => 'The gender must be one of: male, female, other.',
            'salary.required' => 'The salary field is required.',
            'salary.numeric' => 'The salary must be a number.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExternalRegisterRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'phone_number' => ['required', 'string', 'max:255', 'unique:users,phone_number'],
            'address' => ['required', 'string', 'max:255'],
            'dob' => ['required', 'date'],
            'gender' => ['required', 'string'],
            'passport_image' => ['nullable', 'string'],
            'years_of_employment' => ['required', 'integer', 'min:1'],
            'salary' => ['required', 'integer', 'min:1'],
            'national_id' => ['required', 'string', 'max:255', 'unique:users,national_id'],
            'old_employee_id' => ['required', 'string', 'max:255', 'unique:users,old_employee_id'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminRegisterRequest extends FormRequest
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
            'first_name'   => 'required|string',
            'last_name'    => 'required|string',
            'phone_number' => 'required|string|unique:users',
            'address'      => 'required|string',
            'dob'          => 'required|date_format:Y-m-d',
            'gender'       => 'required|string|in:male,female',
            'email'        => 'required|email|unique:users',
            'password'     => 'required|string|min:6',
            'role'         => 'required|string|in:employer,super-admin,employee'
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateLoanTypeRequest extends FormRequest
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
            'name' => 'required|string',
            'requires_guarantors' => 'required|boolean',
            'required_guarantors_count' => 'required|integer',
            'guarantor_qualifications' => 'required|array',
            'payment_type' => 'required|string',
            'interest_rate' => 'required|integer',
            'approval_type' => 'required|string',
            'type' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ];
    }
}

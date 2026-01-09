<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoanTypeRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'interest_rate' => 'sometimes|numeric|min:0|max:100',
            'approval_type' => 'sometimes|string|in:manual,automatic',
            'requires_guarantors' => 'sometimes|boolean',
            'required_guarantors_count' => 'sometimes|integer|min:0',
            'guarantor_qualifications' => 'sometimes|array',
            'guarantor_qualifications.min_credit_score' => 'sometimes|integer|min:0|max:100',
            'guarantor_qualifications.min_employment_years' => 'sometimes|integer|min:0',
            'type' => 'sometimes|string|nullable',
            'payment_type' => 'sometimes|string',
            'is_active' => 'sometimes|boolean',
        ];
    }
}


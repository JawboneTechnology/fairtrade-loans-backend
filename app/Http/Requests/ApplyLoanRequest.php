<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyLoanRequest extends FormRequest
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
            // Validate fields
            'employee_id' => 'required|exists:users,id',
            'loan_type_id' => 'required|exists:loan_types,id',
            'amount' => 'required|numeric|min:1000',
            'interest_rate' => 'required|numeric|min:0',
            'tenure_months' => 'required|numeric|min:1',
            'guarantors' => 'array',
            'guarantors.*' => 'exists:users,id',
        ];
    }

    // Custom validation messages
    public function messages(): array
    {
        return [
            'guarantors.*.exists' => 'Guarantors must be employees within the organization',
            'employee_id.required' => 'Employee ID is required',
            'employee_id.exists' => 'Employee MUST be a valid employee registered by the organization',
            'loan_type_id.required' => 'Loan Type is required',
            'loan_type_id.exists' => 'Loan Type must be a valid Loan Type set by the organization',
            'amount.required' => 'Amount is required',
            'amount.numeric' => 'Amount must be a number',
            'amount.min' => 'Amount must be a number not less than 1000',
            'interest_rate.required' => 'An Interest Rate is required',
            'interest_rate.numeric' => 'Interest Rate must be a number',
            'interest_rate.min' => 'Interest Rate must be a number not less than 0',
            'tenure_months.required' => 'Tenure Months is required',
            'tenure_months.numeric' => 'Tenure Months must be a number',
            'tenure_months.min' => 'Tenure Months must be a number not less than 1',
        ];
    }
}

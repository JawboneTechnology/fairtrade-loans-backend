<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessDeductionRequest extends FormRequest
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
            'loan_id' => [
                'required',
                'uuid',
                'exists:loans,id'
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999999.99'
            ],
            'deduction_type' => [
                'required',
                'string',
                Rule::in([
                    'Manual',
                    'Automatic',
                    'Bank_Transfer',
                    'Mobile_Money',
                    'Online_Payment',
                    'Cheque',
                    'Cash',
                    'Partial_Payments',
                    'Early_Repayments',
                    'Penalty_Payments',
                    'Refunds'
                ])
            ],
            'employee_id' => [
                'required',
                'uuid',
                'exists:users,id'
            ],
            'loan_number' => [
                'required',
                'string',
                'exists:loans,loan_number'
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'loan_id.required' => 'Loan ID is required',
            'loan_id.uuid' => 'Loan ID must be a valid UUID',
            'loan_id.exists' => 'Loan not found',
            'amount.required' => 'Deduction amount is required',
            'amount.numeric' => 'Amount must be a number',
            'amount.min' => 'Amount must be greater than 0',
            'deduction_type.required' => 'Deduction type is required',
            'deduction_type.in' => 'Invalid deduction type',
            'employee_id.required' => 'Employee ID is required',
            'employee_id.uuid' => 'Employee ID must be a valid UUID',
            'employee_id.exists' => 'Employee not found',
            'loan_number.required' => 'Loan number is required',
            'loan_number.exists' => 'Loan number not found',
        ];
    }
}


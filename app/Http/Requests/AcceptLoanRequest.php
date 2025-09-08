<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptLoanRequest extends FormRequest
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
            'employee_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_type' => 'required|string|in:credit,Manual,Automatic,Bank_Transfer,Mobile_Money,Online_Payment,Cheque,Cash,Partial_Payments,Early_Repayments,Penalty_Payments,Refunds',
        ];
    }
}

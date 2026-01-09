<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoanLimitCalculationRequest extends FormRequest
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
            'calculation_method' => 'sometimes|string|in:salary_percentage,fixed_amount',
            'max_installment_percentage' => 'sometimes|numeric|min:1|max:100',
            'default_tenure_months' => 'sometimes|integer|min:1|max:120',
            'consider_existing_loans' => 'sometimes|boolean',
            'include_pending_loans' => 'sometimes|boolean',
            'min_loan_limit' => 'sometimes|numeric|min:0',
            'max_loan_limit' => 'sometimes|numeric|min:0',
            'salary_multiplier' => 'sometimes|numeric|min:1|max:120',
            'custom_rules' => 'sometimes|array',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->has('min_loan_limit') && $this->has('max_loan_limit')) {
                if ($this->input('min_loan_limit') > $this->input('max_loan_limit')) {
                    $validator->errors()->add('min_loan_limit', 'Min loan limit cannot be greater than max loan limit.');
                }
            }
        });
    }
}


<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentSettingsRequest extends FormRequest
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
            'auto_deduction_enabled' => 'sometimes|boolean',
            'deduction_day_of_month' => 'sometimes|integer|min:1|max:31',
            'allowed_payment_methods' => 'sometimes|array',
            'allowed_payment_methods.*' => 'string|in:salary_deduction,mobile_money,bank_transfer,cash',
            'default_payment_method' => 'sometimes|string|in:salary_deduction,mobile_money,bank_transfer,cash',
            'enable_partial_payments' => 'sometimes|boolean',
            'min_payment_amount' => 'sometimes|numeric|min:0',
            'enable_early_payment' => 'sometimes|boolean',
            'early_payment_discount_percentage' => 'sometimes|numeric|min:0|max:100',
            'late_payment_penalty_rate' => 'sometimes|numeric|min:0|max:100',
            'grace_period_days' => 'sometimes|integer|min:0',
        ];
    }
}


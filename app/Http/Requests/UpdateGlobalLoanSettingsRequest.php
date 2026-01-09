<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGlobalLoanSettingsRequest extends FormRequest
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
            'max_installment_percentage' => 'sometimes|numeric|min:1|max:100',
            'default_tenure_months' => 'sometimes|integer|min:1|max:120',
            'max_tenure_months' => 'sometimes|integer|min:1|max:120',
            'min_tenure_months' => 'sometimes|integer|min:1|max:120',
            'daily_application_limit' => 'sometimes|integer|min:1',
            'enable_automatic_approval' => 'sometimes|boolean',
            'default_interest_rate' => 'sometimes|numeric|min:0|max:100',
            'min_loan_amount' => 'sometimes|numeric|min:0',
            'max_loan_amount' => 'sometimes|numeric|min:0',
            'loan_limit_calculation_method' => 'sometimes|string|in:salary_percentage,fixed_amount',
            'enable_guarantor_system' => 'sometimes|boolean',
            'max_active_loans_per_employee' => 'sometimes|integer|min:1',
            'overdue_penalty_rate' => 'sometimes|numeric|min:0|max:100',
            'grace_period_days' => 'sometimes|integer|min:0',
            'auto_deduction_enabled' => 'sometimes|boolean',
            'deduction_day_of_month' => 'sometimes|integer|min:1|max:31',
            'notification_settings' => 'sometimes|array',
            'notification_settings.send_installment_reminders' => 'sometimes|boolean',
            'notification_settings.reminder_days_before_due' => 'sometimes|array',
            'notification_settings.reminder_days_before_due.*' => 'integer|min:0',
            'notification_settings.send_overdue_notifications' => 'sometimes|boolean',
            'notification_settings.overdue_notification_frequency' => 'sometimes|string|in:daily,weekly,monthly',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'max_installment_percentage.max' => 'The max installment percentage cannot exceed 100%.',
            'max_tenure_months.max' => 'The max tenure months cannot exceed 120.',
            'min_tenure_months.min' => 'The min tenure months must be at least 1.',
        ];
    }
}


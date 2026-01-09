<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserNotificationPreferencesRequest extends FormRequest
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
            'enabled' => 'sometimes|boolean',
            'channels' => 'sometimes|array',
            'channels.database' => 'sometimes|boolean',
            'channels.email' => 'sometimes|boolean',
            'channels.sms' => 'sometimes|boolean',
            'channels.push' => 'sometimes|boolean',
            'notification_types' => 'sometimes|array',
            'notification_types.loan_application_submitted' => 'sometimes|boolean',
            'notification_types.loan_approved' => 'sometimes|boolean',
            'notification_types.loan_rejected' => 'sometimes|boolean',
            'notification_types.loan_paid' => 'sometimes|boolean',
            'notification_types.loan_canceled' => 'sometimes|boolean',
            'notification_types.payment_received' => 'sometimes|boolean',
            'notification_types.deduction_processed' => 'sometimes|boolean',
            'notification_types.guarantor_request' => 'sometimes|boolean',
            'notification_types.guarantor_acceptance' => 'sometimes|boolean',
            'notification_types.guarantor_rejection' => 'sometimes|boolean',
            'notification_types.grant_approved' => 'sometimes|boolean',
            'notification_types.grant_rejected' => 'sometimes|boolean',
            'loan_notifications' => 'sometimes|array',
            'loan_notifications.installment_reminders' => 'sometimes|boolean',
            'loan_notifications.overdue_notifications' => 'sometimes|boolean',
            'loan_notifications.approval_notifications' => 'sometimes|boolean',
            'loan_notifications.rejection_notifications' => 'sometimes|boolean',
            'loan_notifications.payment_confirmation' => 'sometimes|boolean',
            'quiet_hours' => 'sometimes|array',
            'quiet_hours.enabled' => 'sometimes|boolean',
            'quiet_hours.start_time' => 'sometimes|string|regex:/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/',
            'quiet_hours.end_time' => 'sometimes|string|regex:/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/',
        ];
    }
}


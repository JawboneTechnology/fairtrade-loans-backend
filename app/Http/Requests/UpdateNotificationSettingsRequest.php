<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationSettingsRequest extends FormRequest
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
            'send_installment_reminders' => 'sometimes|boolean',
            'reminder_days_before_due' => 'sometimes|array',
            'reminder_days_before_due.*' => 'integer|min:0',
            'send_overdue_notifications' => 'sometimes|boolean',
            'overdue_notification_frequency' => 'sometimes|string|in:daily,weekly,monthly',
            'send_approval_notifications' => 'sometimes|boolean',
            'send_rejection_notifications' => 'sometimes|boolean',
            'send_payment_confirmation' => 'sometimes|boolean',
            'send_guarantor_requests' => 'sometimes|boolean',
            'notification_channels' => 'sometimes|array',
            'notification_channels.*' => 'string|in:sms,email,push',
            'default_notification_channel' => 'sometimes|string|in:sms,email,push',
        ];
    }
}


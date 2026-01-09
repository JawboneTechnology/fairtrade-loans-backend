<?php

namespace App\Services;

use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;

class UserNotificationPreferencesService
{
    /**
     * Get default notification preferences for a user
     */
    public function getDefaultPreferences(): array
    {
        // Get system defaults from loan settings
        $systemSettings = SystemSetting::getJsonValue('loan_settings_notifications', []);
        
        return [
            'enabled' => true,
            'channels' => [
                'database' => true,
                'email' => true,
                'sms' => true,
                'push' => true,
            ],
            'notification_types' => [
                'loan_application_submitted' => true,
                'loan_approved' => true,
                'loan_rejected' => true,
                'loan_paid' => true,
                'loan_canceled' => true,
                'payment_received' => true,
                'deduction_processed' => true,
                'guarantor_request' => true,
                'guarantor_acceptance' => true,
                'guarantor_rejection' => true,
                'grant_approved' => true,
                'grant_rejected' => true,
            ],
            'loan_notifications' => [
                'installment_reminders' => $systemSettings['send_installment_reminders'] ?? true,
                'overdue_notifications' => $systemSettings['send_overdue_notifications'] ?? true,
                'approval_notifications' => $systemSettings['send_approval_notifications'] ?? true,
                'rejection_notifications' => $systemSettings['send_rejection_notifications'] ?? true,
                'payment_confirmation' => $systemSettings['send_payment_confirmation'] ?? true,
            ],
            'quiet_hours' => [
                'enabled' => false,
                'start_time' => '22:00',
                'end_time' => '08:00',
            ],
        ];
    }

    /**
     * Get user notification preferences
     */
    public function getUserPreferences(User $user): array
    {
        $preferences = $user->notification_preferences;
        
        if (empty($preferences)) {
            $preferences = $this->getDefaultPreferences();
            $this->updateUserPreferences($user, $preferences);
        }

        return $preferences;
    }

    /**
     * Update user notification preferences
     */
    public function updateUserPreferences(User $user, array $preferences): User
    {
        $defaults = $this->getDefaultPreferences();
        $merged = array_merge($defaults, $preferences);

        // Validate preferences
        $this->validatePreferences($merged);

        $user->notification_preferences = $merged;
        $user->save();

        return $user->fresh();
    }

    /**
     * Check if user wants to receive a specific notification type
     */
    public function shouldSendNotification(User $user, string $notificationType, string $channel = 'database'): bool
    {
        $preferences = $this->getUserPreferences($user);

        // Check if notifications are enabled
        if (!($preferences['enabled'] ?? true)) {
            return false;
        }

        // Check if channel is enabled
        if (!($preferences['channels'][$channel] ?? true)) {
            return false;
        }

        // Check if notification type is enabled
        if (isset($preferences['notification_types'][$notificationType])) {
            return $preferences['notification_types'][$notificationType] === true;
        }

        // Check loan-specific notifications
        if (isset($preferences['loan_notifications'][$notificationType])) {
            return $preferences['loan_notifications'][$notificationType] === true;
        }

        // Default to true if not explicitly disabled
        return true;
    }

    /**
     * Validate preferences
     */
    protected function validatePreferences(array $preferences): void
    {
        // Validate channels
        if (isset($preferences['channels']) && !is_array($preferences['channels'])) {
            throw new \InvalidArgumentException('Channels must be an array');
        }

        // Validate notification types
        if (isset($preferences['notification_types']) && !is_array($preferences['notification_types'])) {
            throw new \InvalidArgumentException('Notification types must be an array');
        }

        // Validate quiet hours
        if (isset($preferences['quiet_hours'])) {
            if (!is_array($preferences['quiet_hours'])) {
                throw new \InvalidArgumentException('Quiet hours must be an array');
            }
            
            if (isset($preferences['quiet_hours']['enabled']) && $preferences['quiet_hours']['enabled']) {
                if (!isset($preferences['quiet_hours']['start_time']) || !isset($preferences['quiet_hours']['end_time'])) {
                    throw new \InvalidArgumentException('Quiet hours start_time and end_time are required when enabled');
                }
            }
        }
    }
}


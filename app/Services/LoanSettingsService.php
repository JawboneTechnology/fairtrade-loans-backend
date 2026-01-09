<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\LoanType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class LoanSettingsService
{
    // Setting keys
    const KEY_GLOBAL_SETTINGS = 'loan_settings_global';
    const KEY_LIMIT_CALCULATION = 'loan_settings_limit_calculation';
    const KEY_PAYMENT_SETTINGS = 'loan_settings_payments';
    const KEY_APPROVAL_WORKFLOW = 'loan_settings_approval_workflow';
    const KEY_NOTIFICATION_SETTINGS = 'loan_settings_notifications';

    /**
     * Get default global loan settings
     */
    public function getDefaultGlobalSettings(): array
    {
        return [
            'max_installment_percentage' => 30,
            'default_tenure_months' => 12,
            'max_tenure_months' => 60,
            'min_tenure_months' => 1,
            'daily_application_limit' => 1,
            'enable_automatic_approval' => false,
            'default_interest_rate' => 5,
            'min_loan_amount' => 1000,
            'max_loan_amount' => 1000000,
            'loan_limit_calculation_method' => 'salary_percentage',
            'enable_guarantor_system' => true,
            'max_active_loans_per_employee' => 3,
            'overdue_penalty_rate' => 2,
            'grace_period_days' => 7,
            'auto_deduction_enabled' => true,
            'deduction_day_of_month' => 25,
            'notification_settings' => [
                'send_installment_reminders' => true,
                'reminder_days_before_due' => [7, 1],
                'send_overdue_notifications' => true,
                'overdue_notification_frequency' => 'daily',
            ],
        ];
    }

    /**
     * Get default limit calculation settings
     */
    public function getDefaultLimitCalculationSettings(): array
    {
        return [
            'calculation_method' => 'salary_percentage',
            'max_installment_percentage' => 30,
            'default_tenure_months' => 12,
            'consider_existing_loans' => true,
            'include_pending_loans' => true,
            'min_loan_limit' => 1000,
            'max_loan_limit' => 1000000,
            'salary_multiplier' => 12,
            'custom_rules' => [],
        ];
    }

    /**
     * Get default payment settings
     */
    public function getDefaultPaymentSettings(): array
    {
        return [
            'auto_deduction_enabled' => true,
            'deduction_day_of_month' => 25,
            'allowed_payment_methods' => ['salary_deduction', 'mobile_money', 'bank_transfer', 'cash'],
            'default_payment_method' => 'salary_deduction',
            'enable_partial_payments' => true,
            'min_payment_amount' => 100,
            'enable_early_payment' => true,
            'early_payment_discount_percentage' => 0,
            'late_payment_penalty_rate' => 2,
            'grace_period_days' => 7,
        ];
    }

    /**
     * Get default approval workflow settings
     */
    public function getDefaultApprovalWorkflowSettings(): array
    {
        return [
            'default_approval_type' => 'manual',
            'auto_approval_threshold' => 50000,
            'require_multiple_approvers' => false,
            'required_approvers_count' => 1,
            'approval_roles' => ['super-admin', 'employer'],
            'enable_escalation' => false,
            'escalation_days' => 3,
            'enable_auto_rejection' => false,
            'auto_rejection_days' => 30,
            'require_guarantor_approval' => true,
            'guarantor_approval_deadline_days' => 7,
        ];
    }

    /**
     * Get default notification settings
     */
    public function getDefaultNotificationSettings(): array
    {
        return [
            'send_installment_reminders' => true,
            'reminder_days_before_due' => [7, 1],
            'send_overdue_notifications' => true,
            'overdue_notification_frequency' => 'daily',
            'send_approval_notifications' => true,
            'send_rejection_notifications' => true,
            'send_payment_confirmation' => true,
            'send_guarantor_requests' => true,
            'notification_channels' => ['sms', 'email', 'push'],
            'default_notification_channel' => 'sms',
        ];
    }

    /**
     * Get global loan settings
     */
    public function getGlobalSettings(): array
    {
        $settings = SystemSetting::getJsonValue(self::KEY_GLOBAL_SETTINGS, []);
        
        if (empty($settings)) {
            $settings = $this->getDefaultGlobalSettings();
            $this->updateGlobalSettings($settings);
        }

        return $settings;
    }

    /**
     * Update global loan settings
     */
    public function updateGlobalSettings(array $settings): SystemSetting
    {
        // Merge with defaults to ensure all keys exist
        $defaults = $this->getDefaultGlobalSettings();
        $merged = array_merge($defaults, $settings);

        // Validate settings
        $this->validateGlobalSettings($merged);

        return SystemSetting::setJsonValue(
            self::KEY_GLOBAL_SETTINGS,
            $merged,
            'Global loan system settings'
        );
    }

    /**
     * Get limit calculation settings
     */
    public function getLimitCalculationSettings(): array
    {
        $settings = SystemSetting::getJsonValue(self::KEY_LIMIT_CALCULATION, []);
        
        if (empty($settings)) {
            $settings = $this->getDefaultLimitCalculationSettings();
            $this->updateLimitCalculationSettings($settings);
        }

        return $settings;
    }

    /**
     * Update limit calculation settings
     */
    public function updateLimitCalculationSettings(array $settings): SystemSetting
    {
        $defaults = $this->getDefaultLimitCalculationSettings();
        $merged = array_merge($defaults, $settings);

        $this->validateLimitCalculationSettings($merged);

        return SystemSetting::setJsonValue(
            self::KEY_LIMIT_CALCULATION,
            $merged,
            'Loan limit calculation settings'
        );
    }

    /**
     * Get payment settings
     */
    public function getPaymentSettings(): array
    {
        $settings = SystemSetting::getJsonValue(self::KEY_PAYMENT_SETTINGS, []);
        
        if (empty($settings)) {
            $settings = $this->getDefaultPaymentSettings();
            $this->updatePaymentSettings($settings);
        }

        return $settings;
    }

    /**
     * Update payment settings
     */
    public function updatePaymentSettings(array $settings): SystemSetting
    {
        $defaults = $this->getDefaultPaymentSettings();
        $merged = array_merge($defaults, $settings);

        $this->validatePaymentSettings($merged);

        return SystemSetting::setJsonValue(
            self::KEY_PAYMENT_SETTINGS,
            $merged,
            'Payment and deduction settings'
        );
    }

    /**
     * Get approval workflow settings
     */
    public function getApprovalWorkflowSettings(): array
    {
        $settings = SystemSetting::getJsonValue(self::KEY_APPROVAL_WORKFLOW, []);
        
        if (empty($settings)) {
            $settings = $this->getDefaultApprovalWorkflowSettings();
            $this->updateApprovalWorkflowSettings($settings);
        }

        return $settings;
    }

    /**
     * Update approval workflow settings
     */
    public function updateApprovalWorkflowSettings(array $settings): SystemSetting
    {
        $defaults = $this->getDefaultApprovalWorkflowSettings();
        $merged = array_merge($defaults, $settings);

        $this->validateApprovalWorkflowSettings($merged);

        return SystemSetting::setJsonValue(
            self::KEY_APPROVAL_WORKFLOW,
            $merged,
            'Loan approval workflow settings'
        );
    }

    /**
     * Get notification settings
     */
    public function getNotificationSettings(): array
    {
        $settings = SystemSetting::getJsonValue(self::KEY_NOTIFICATION_SETTINGS, []);
        
        if (empty($settings)) {
            $settings = $this->getDefaultNotificationSettings();
            $this->updateNotificationSettings($settings);
        }

        return $settings;
    }

    /**
     * Update notification settings
     */
    public function updateNotificationSettings(array $settings): SystemSetting
    {
        $defaults = $this->getDefaultNotificationSettings();
        $merged = array_merge($defaults, $settings);

        $this->validateNotificationSettings($merged);

        return SystemSetting::setJsonValue(
            self::KEY_NOTIFICATION_SETTINGS,
            $merged,
            'Loan notification settings'
        );
    }

    /**
     * Validate global settings
     */
    protected function validateGlobalSettings(array $settings): void
    {
        if (isset($settings['max_installment_percentage']) && 
            ($settings['max_installment_percentage'] < 1 || $settings['max_installment_percentage'] > 100)) {
            throw new \InvalidArgumentException('Max installment percentage must be between 1 and 100');
        }

        if (isset($settings['default_tenure_months']) && 
            ($settings['default_tenure_months'] < 1 || $settings['default_tenure_months'] > 120)) {
            throw new \InvalidArgumentException('Default tenure months must be between 1 and 120');
        }

        if (isset($settings['min_tenure_months']) && isset($settings['max_tenure_months']) &&
            $settings['min_tenure_months'] > $settings['max_tenure_months']) {
            throw new \InvalidArgumentException('Min tenure months cannot be greater than max tenure months');
        }
    }

    /**
     * Validate limit calculation settings
     */
    protected function validateLimitCalculationSettings(array $settings): void
    {
        if (isset($settings['max_installment_percentage']) && 
            ($settings['max_installment_percentage'] < 1 || $settings['max_installment_percentage'] > 100)) {
            throw new \InvalidArgumentException('Max installment percentage must be between 1 and 100');
        }

        if (isset($settings['min_loan_limit']) && isset($settings['max_loan_limit']) &&
            $settings['min_loan_limit'] > $settings['max_loan_limit']) {
            throw new \InvalidArgumentException('Min loan limit cannot be greater than max loan limit');
        }
    }

    /**
     * Validate payment settings
     */
    protected function validatePaymentSettings(array $settings): void
    {
        if (isset($settings['deduction_day_of_month']) && 
            ($settings['deduction_day_of_month'] < 1 || $settings['deduction_day_of_month'] > 31)) {
            throw new \InvalidArgumentException('Deduction day of month must be between 1 and 31');
        }

        if (isset($settings['min_payment_amount']) && $settings['min_payment_amount'] < 0) {
            throw new \InvalidArgumentException('Min payment amount cannot be negative');
        }
    }

    /**
     * Validate approval workflow settings
     */
    protected function validateApprovalWorkflowSettings(array $settings): void
    {
        if (isset($settings['required_approvers_count']) && $settings['required_approvers_count'] < 1) {
            throw new \InvalidArgumentException('Required approvers count must be at least 1');
        }

        if (isset($settings['auto_approval_threshold']) && $settings['auto_approval_threshold'] < 0) {
            throw new \InvalidArgumentException('Auto approval threshold cannot be negative');
        }
    }

    /**
     * Validate notification settings
     */
    protected function validateNotificationSettings(array $settings): void
    {
        if (isset($settings['reminder_days_before_due']) && !is_array($settings['reminder_days_before_due'])) {
            throw new \InvalidArgumentException('Reminder days before due must be an array');
        }
    }

    /**
     * Get loan types with pagination and filters
     */
    public function getLoanTypes(array $filters = []): array
    {
        $query = LoanType::query();

        // Search by name
        if (isset($filters['search']) && !empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        // Filter by active status
        if (isset($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($filters['status'] === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $perPage = $filters['per_page'] ?? 15;
        $loanTypes = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return [
            'current_page' => $loanTypes->currentPage(),
            'data' => $loanTypes->items(),
            'total' => $loanTypes->total(),
            'per_page' => $loanTypes->perPage(),
            'last_page' => $loanTypes->lastPage(),
        ];
    }

    /**
     * Get single loan type
     */
    public function getLoanType(string $id): LoanType
    {
        return LoanType::findOrFail($id);
    }

    /**
     * Create loan type
     */
    public function createLoanType(array $data): LoanType
    {
        return LoanType::create($data);
    }

    /**
     * Update loan type
     */
    public function updateLoanType(string $id, array $data): LoanType
    {
        $loanType = LoanType::findOrFail($id);
        $loanType->update($data);
        return $loanType->fresh();
    }

    /**
     * Delete loan type
     */
    public function deleteLoanType(string $id): bool
    {
        $loanType = LoanType::findOrFail($id);
        
        // Check if loan type has active loans
        if ($loanType->loans()->where('loan_status', '!=', 'completed')->exists()) {
            throw new \Exception('Cannot delete loan type with active loans. Please deactivate it instead.');
        }

        return $loanType->delete();
    }
}


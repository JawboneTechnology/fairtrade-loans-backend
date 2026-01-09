<?php

namespace App\Services;

use App\Models\SmsTemplate;
use Illuminate\Support\Facades\Log;

class SmsTemplateService
{
    /**
     * Get all SMS templates with pagination and filters
     */
    public function getTemplates(array $filters = []): array
    {
        $query = SmsTemplate::query();

        // Search by type or name
        if (isset($filters['search']) && !empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('type', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('name', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Filter by active status
        if (isset($filters['is_active']) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        $perPage = $filters['per_page'] ?? 15;
        $templates = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return [
            'current_page' => $templates->currentPage(),
            'data' => $templates->items(),
            'total' => $templates->total(),
            'per_page' => $templates->perPage(),
            'last_page' => $templates->lastPage(),
        ];
    }

    /**
     * Get single SMS template
     */
    public function getTemplate(string $id): SmsTemplate
    {
        return SmsTemplate::findOrFail($id);
    }

    /**
     * Get template by type
     */
    public function getTemplateByType(string $type): ?SmsTemplate
    {
        return SmsTemplate::getByType($type);
    }

    /**
     * Create SMS template
     */
    public function createTemplate(array $data): SmsTemplate
    {
        // Ensure available_variables is JSON encoded if it's an array
        if (isset($data['available_variables']) && is_array($data['available_variables'])) {
            $data['available_variables'] = json_encode($data['available_variables']);
        }

        return SmsTemplate::create($data);
    }

    /**
     * Update SMS template
     */
    public function updateTemplate(string $id, array $data): SmsTemplate
    {
        $template = SmsTemplate::findOrFail($id);

        // Ensure available_variables is JSON encoded if it's an array
        if (isset($data['available_variables']) && is_array($data['available_variables'])) {
            $data['available_variables'] = json_encode($data['available_variables']);
        }

        $template->update($data);
        return $template->fresh();
    }

    /**
     * Delete SMS template
     */
    public function deleteTemplate(string $id): bool
    {
        $template = SmsTemplate::findOrFail($id);
        return $template->delete();
    }

    /**
     * Preview template with sample data
     */
    public function previewTemplate(string $id, array $sampleData): array
    {
        $template = SmsTemplate::findOrFail($id);
        
        $parsedMessage = $template->parseMessage($sampleData);
        $messageLength = strlen($parsedMessage);
        
        // Estimate cost (assuming 1 SMS = 160 characters, cost per SMS = 0.10)
        $estimatedCost = ceil($messageLength / 160) * 0.10;
        
        return [
            'original_message' => $template->message,
            'parsed_message' => $parsedMessage,
            'message_length' => $messageLength,
            'estimated_cost' => round($estimatedCost, 2),
        ];
    }

    /**
     * Get available template types with descriptions
     */
    public function getAvailableTypes(): array
    {
        return [
            [
                'type' => 'loan_application_submitted',
                'name' => 'Loan Application Submitted',
                'description' => 'Sent when a loan application is received',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_type']
            ],
            [
                'type' => 'loan_approved',
                'name' => 'Loan Approved',
                'description' => 'Sent when a loan application is approved',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'approved_amount', 'remarks']
            ],
            [
                'type' => 'loan_rejected',
                'name' => 'Loan Rejected',
                'description' => 'Sent when a loan application is rejected',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'remarks']
            ],
            [
                'type' => 'installment_reminder_early',
                'name' => 'Installment Reminder (Early)',
                'description' => 'Sent 7 days before installment due date',
                'available_variables' => ['user_name', 'loan_number', 'monthly_installment', 'due_date', 'days_until_due']
            ],
            [
                'type' => 'installment_reminder_late',
                'name' => 'Installment Reminder (Late)',
                'description' => 'Sent 1 day before installment due date',
                'available_variables' => ['user_name', 'loan_number', 'monthly_installment', 'due_date']
            ],
            [
                'type' => 'loan_overdue',
                'name' => 'Loan Overdue',
                'description' => 'Sent when a loan installment is overdue',
                'available_variables' => ['user_name', 'loan_number', 'monthly_installment', 'days_overdue', 'loan_balance']
            ],
            [
                'type' => 'payment_received',
                'name' => 'Payment Received',
                'description' => 'Sent when a payment is received',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'receipt_number', 'payment_date', 'payment_method', 'loan_balance']
            ],
            [
                'type' => 'deduction_manual',
                'name' => 'Manual Deduction',
                'description' => 'Sent when a manual deduction is processed',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance']
            ],
            [
                'type' => 'deduction_automatic',
                'name' => 'Automatic Deduction',
                'description' => 'Sent when an automatic deduction is processed',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance']
            ],
            [
                'type' => 'deduction_bank_transfer',
                'name' => 'Bank Transfer Payment',
                'description' => 'Sent when a bank transfer payment is received',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance']
            ],
            [
                'type' => 'deduction_mobile_money',
                'name' => 'Mobile Money Payment',
                'description' => 'Sent when a mobile money payment is received',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance']
            ],
            [
                'type' => 'deduction_online_payment',
                'name' => 'Online Payment',
                'description' => 'Sent when an online payment is confirmed',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance']
            ],
            [
                'type' => 'deduction_cheque',
                'name' => 'Cheque Payment',
                'description' => 'Sent when a cheque payment is cleared',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance']
            ],
            [
                'type' => 'deduction_cash',
                'name' => 'Cash Payment',
                'description' => 'Sent when a cash payment is received',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance']
            ],
            [
                'type' => 'deduction_partial',
                'name' => 'Partial Payment',
                'description' => 'Sent when a partial payment is received',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance']
            ],
            [
                'type' => 'deduction_early_repayment',
                'name' => 'Early Repayment',
                'description' => 'Sent when an early repayment is processed',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance']
            ],
            [
                'type' => 'deduction_penalty',
                'name' => 'Penalty Payment',
                'description' => 'Sent when a penalty payment is received',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance']
            ],
            [
                'type' => 'deduction_refund',
                'name' => 'Refund Processed',
                'description' => 'Sent when a refund is processed',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance']
            ],
            [
                'type' => 'grant_application_submitted',
                'name' => 'Grant Application Submitted',
                'description' => 'Sent when a grant application is received',
                'available_variables' => ['user_name', 'grant_type', 'amount']
            ],
            [
                'type' => 'grant_approved',
                'name' => 'Grant Approved',
                'description' => 'Sent when a grant application is approved',
                'available_variables' => ['user_name', 'grant_type', 'amount', 'remarks']
            ],
            [
                'type' => 'grant_rejected',
                'name' => 'Grant Rejected',
                'description' => 'Sent when a grant application is rejected',
                'available_variables' => ['user_name', 'grant_type', 'amount', 'remarks']
            ],
            [
                'type' => 'otp_code',
                'name' => 'OTP Code',
                'description' => 'Sent when an OTP code is generated',
                'available_variables' => ['user_name', 'otp_code', 'app_name']
            ],
            [
                'type' => 'admin_new_loan',
                'name' => 'Admin: New Loan Application',
                'description' => 'Sent to admin when a new loan application is submitted',
                'available_variables' => ['applicant_name', 'loan_number', 'amount', 'admin_dashboard_url']
            ],
            [
                'type' => 'admin_new_grant',
                'name' => 'Admin: New Grant Application',
                'description' => 'Sent to admin when a new grant application is submitted',
                'available_variables' => ['applicant_name', 'grant_type', 'amount']
            ],
            [
                'type' => 'guarantor_accepted',
                'name' => 'Guarantor Accepted',
                'description' => 'Sent when a guarantor accepts a loan guarantee request',
                'available_variables' => ['user_name', 'guarantor_name', 'loan_number', 'amount']
            ],
        ];
    }
}


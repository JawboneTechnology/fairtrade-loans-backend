<?php

namespace Database\Seeders;

use App\Models\SmsTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SmsTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'type' => 'loan_application_submitted',
                'name' => 'Loan Application Submitted',
                'message' => 'Dear {{user_name}}, your loan application for KES {{amount}} has been received. Loan number: {{loan_number}}.',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_type'],
                'description' => 'Sent when a loan application is received',
            ],
            [
                'type' => 'loan_approved',
                'name' => 'Loan Approved',
                'message' => 'Dear {{user_name}}, your loan application (Loan No: {{loan_number}}) has been approved for KES {{amount}}.{{#remarks}} Remarks: {{remarks}}{{/remarks}} You will receive a notification once the money has been sent to your M-Pesa number.',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'approved_amount', 'remarks'],
                'description' => 'Sent when a loan application is approved',
            ],
            [
                'type' => 'loan_rejected',
                'name' => 'Loan Rejected',
                'message' => 'Dear {{user_name}}, your loan application (Loan No: {{loan_number}}) has been rejected.{{#remarks}} Remarks: {{remarks}}{{/remarks}}{{^remarks}} Please contact support for more information.{{/remarks}}',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'remarks'],
                'description' => 'Sent when a loan application is rejected',
            ],
            [
                'type' => 'installment_reminder_early',
                'name' => 'Installment Reminder (Early)',
                'message' => 'Dear {{user_name}}, your loan installment of KES {{monthly_installment}} for loan {{loan_number}} is due in {{days_until_due}} days on {{due_date}}. Please prepare for payment.',
                'available_variables' => ['user_name', 'loan_number', 'monthly_installment', 'due_date', 'days_until_due'],
                'description' => 'Sent 7 days before installment due date',
            ],
            [
                'type' => 'installment_reminder_late',
                'name' => 'Installment Reminder (Late)',
                'message' => 'Dear {{user_name}}, REMINDER: Your loan installment of KES {{monthly_installment}} for loan {{loan_number}} is due tomorrow ({{due_date}}). Please ensure timely payment.',
                'available_variables' => ['user_name', 'loan_number', 'monthly_installment', 'due_date'],
                'description' => 'Sent 1 day before installment due date',
            ],
            [
                'type' => 'loan_overdue',
                'name' => 'Loan Overdue',
                'message' => 'Dear {{user_name}}, your loan installment of KES {{monthly_installment}} for loan {{loan_number}} is now {{days_overdue}} days OVERDUE. Outstanding balance: KES {{loan_balance}}. Please pay immediately to avoid penalties.',
                'available_variables' => ['user_name', 'loan_number', 'monthly_installment', 'days_overdue', 'loan_balance'],
                'description' => 'Sent when a loan installment is overdue',
            ],
            [
                'type' => 'payment_received',
                'name' => 'Payment Received',
                'message' => 'Dear {{user_name}}, your payment of KES {{amount}} for loan {{loan_number}} has been received successfully. Receipt: {{receipt_number}}. New loan balance: KES {{loan_balance}}. Payment processed on {{payment_date}} via {{payment_method}}. Thank you!',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'receipt_number', 'payment_date', 'payment_method', 'loan_balance'],
                'description' => 'Sent when a payment is received',
            ],
            [
                'type' => 'deduction_manual',
                'name' => 'Manual Deduction',
                'message' => 'Dear {{user_name}}, Manual deduction of KES {{amount}} has been processed for loan {{loan_number}}. New balance: KES {{loan_balance}}. Thank you!',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance'],
                'description' => 'Sent when a manual deduction is processed',
            ],
            [
                'type' => 'deduction_automatic',
                'name' => 'Automatic Deduction',
                'message' => 'Dear {{user_name}}, Automatic deduction of KES {{amount}} has been processed for loan {{loan_number}}. New balance: KES {{loan_balance}}. Thank you!',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance'],
                'description' => 'Sent when an automatic deduction is processed',
            ],
            [
                'type' => 'deduction_bank_transfer',
                'name' => 'Bank Transfer Payment',
                'message' => 'Dear {{user_name}}, Your bank transfer of KES {{amount}} for loan {{loan_number}} has been received. New balance: KES {{loan_balance}}. Thank you!',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance'],
                'description' => 'Sent when a bank transfer payment is received',
            ],
            [
                'type' => 'deduction_mobile_money',
                'name' => 'Mobile Money Payment',
                'message' => 'Dear {{user_name}}, Your mobile money payment of KES {{amount}} for loan {{loan_number}} has been received. New balance: KES {{loan_balance}}. Thank you!',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance'],
                'description' => 'Sent when a mobile money payment is received',
            ],
            [
                'type' => 'deduction_online_payment',
                'name' => 'Online Payment',
                'message' => 'Dear {{user_name}}, Your online payment of KES {{amount}} for loan {{loan_number}} has been confirmed. New balance: KES {{loan_balance}}. Thank you!',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance'],
                'description' => 'Sent when an online payment is confirmed',
            ],
            [
                'type' => 'deduction_cheque',
                'name' => 'Cheque Payment',
                'message' => 'Dear {{user_name}}, Your cheque payment of KES {{amount}} for loan {{loan_number}} has been cleared. New balance: KES {{loan_balance}}. Thank you!',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance'],
                'description' => 'Sent when a cheque payment is cleared',
            ],
            [
                'type' => 'deduction_cash',
                'name' => 'Cash Payment',
                'message' => 'Dear {{user_name}}, Cash payment of KES {{amount}} for loan {{loan_number}} has been received. New balance: KES {{loan_balance}}. Thank you!',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance'],
                'description' => 'Sent when a cash payment is received',
            ],
            [
                'type' => 'deduction_partial',
                'name' => 'Partial Payment',
                'message' => 'Dear {{user_name}}, Partial payment of KES {{amount}} for loan {{loan_number}} has been received. New balance: KES {{loan_balance}}. Thank you!',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance'],
                'description' => 'Sent when a partial payment is received',
            ],
            [
                'type' => 'deduction_early_repayment',
                'name' => 'Early Repayment',
                'message' => 'Dear {{user_name}}, Early repayment of KES {{amount}} for loan {{loan_number}} has been processed. New balance: KES {{loan_balance}}. Thank you for paying ahead!',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance'],
                'description' => 'Sent when an early repayment is processed',
            ],
            [
                'type' => 'deduction_penalty',
                'name' => 'Penalty Payment',
                'message' => 'Dear {{user_name}}, Penalty payment of KES {{amount}} for loan {{loan_number}} has been received. New balance: KES {{loan_balance}}. Thank you!',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance'],
                'description' => 'Sent when a penalty payment is received',
            ],
            [
                'type' => 'deduction_refund',
                'name' => 'Refund Processed',
                'message' => 'Dear {{user_name}}, Refund of KES {{amount}} has been processed for loan {{loan_number}}. New balance: KES {{loan_balance}}. Thank you!',
                'available_variables' => ['user_name', 'loan_number', 'amount', 'loan_balance'],
                'description' => 'Sent when a refund is processed',
            ],
            [
                'type' => 'grant_application_submitted',
                'name' => 'Grant Application Submitted',
                'message' => 'Dear {{user_name}}, your grant application for {{grant_type}} of KES {{amount}} has been received and is under review.',
                'available_variables' => ['user_name', 'grant_type', 'amount'],
                'description' => 'Sent when a grant application is received',
            ],
            [
                'type' => 'grant_approved',
                'name' => 'Grant Approved',
                'message' => 'Dear {{user_name}}, your grant application for {{grant_type}} of KES {{amount}} has been approved.{{#remarks}} Remarks: {{remarks}}{{/remarks}} You will receive disbursement details soon.',
                'available_variables' => ['user_name', 'grant_type', 'amount', 'remarks'],
                'description' => 'Sent when a grant application is approved',
            ],
            [
                'type' => 'grant_rejected',
                'name' => 'Grant Rejected',
                'message' => 'Dear {{user_name}}, your grant application for {{grant_type}} of KES {{amount}} has been rejected.{{#remarks}} Remarks: {{remarks}}{{/remarks}}{{^remarks}} Please contact support for more information.{{/remarks}}',
                'available_variables' => ['user_name', 'grant_type', 'amount', 'remarks'],
                'description' => 'Sent when a grant application is rejected',
            ],
            [
                'type' => 'otp_code',
                'name' => 'OTP Code',
                'message' => 'Hello {{user_name}}, your {{app_name}} OTP code is: {{otp_code}}. This code expires in 30 minutes. Do not share this code.',
                'available_variables' => ['user_name', 'otp_code', 'app_name'],
                'description' => 'Sent when an OTP code is generated',
            ],
            [
                'type' => 'admin_new_loan',
                'name' => 'Admin: New Loan Application',
                'message' => 'New loan application from {{applicant_name}} for KES {{amount}}, Loan: {{loan_number}}. View: {{admin_dashboard_url}}',
                'available_variables' => ['applicant_name', 'loan_number', 'amount', 'admin_dashboard_url'],
                'description' => 'Sent to admin when a new loan application is submitted',
            ],
            [
                'type' => 'admin_new_grant',
                'name' => 'Admin: New Grant Application',
                'message' => 'New grant application from {{applicant_name}} for {{grant_type}} of KES {{amount}}. Please review.',
                'available_variables' => ['applicant_name', 'grant_type', 'amount'],
                'description' => 'Sent to admin when a new grant application is submitted',
            ],
            [
                'type' => 'guarantor_accepted',
                'name' => 'Guarantor Accepted',
                'message' => '{{guarantor_name}} has accepted your loan guarantee request for loan #{{loan_number}} (KES {{amount}}).',
                'available_variables' => ['user_name', 'guarantor_name', 'loan_number', 'amount'],
                'description' => 'Sent when a guarantor accepts a loan guarantee request',
            ],
        ];

        foreach ($templates as $template) {
            SmsTemplate::updateOrCreate(
                ['type' => $template['type']],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $template['name'],
                    'message' => $template['message'],
                    'available_variables' => $template['available_variables'],
                    'description' => $template['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}

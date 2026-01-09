<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $notificationTemplates = [
            // Existing guarantor notifications
            [
                'type' => 'guarantor_request',
                'title' => 'Loan Guarantee Request',
                'message' => 'You have been requested to guarantee a loan of {{amount}} for {{applicant_name}} (Loan #{{loan_number}}).',
                'channels' => json_encode(['database', 'email'])
            ],
            [
                'type' => 'guarantor_acceptance',
                'title' => 'Loan Guarantee Accepted',
                'message' => 'All guarantors have accepted your loan #{{loan_number}} for {{amount}}. Your loan is now being processed.',
                'channels' => json_encode(['database', 'email'])
            ],
            [
                'type' => 'guarantor_rejection',
                'title' => 'Loan Guarantee Rejected',
                'message' => 'Your loan #{{loan_number}} for {{amount}} was rejected by one or more guarantors.',
                'channels' => json_encode(['database', 'email'])
            ],
            // Loan notifications
            [
                'type' => 'loan_application_submitted',
                'title' => 'Loan Application Submitted',
                'message' => 'Your loan application #{{loan_number}} for KES {{amount}} has been submitted and is under review.',
                'channels' => json_encode(['database', 'email'])
            ],
            [
                'type' => 'loan_approved',
                'title' => 'Loan Approved',
                'message' => 'Your loan application #{{loan_number}} for KES {{amount}} has been approved. Approved amount: KES {{approved_amount}}. Please wait 24 hours for funds to be disbursed.',
                'channels' => json_encode(['database', 'email'])
            ],
            [
                'type' => 'loan_rejected',
                'title' => 'Loan Rejected',
                'message' => 'Your loan application #{{loan_number}} has been rejected. {{remarks}}',
                'channels' => json_encode(['database', 'email'])
            ],
            [
                'type' => 'loan_paid',
                'title' => 'Loan Fully Paid',
                'message' => 'Congratulations! Your loan #{{loan_number}} has been fully paid.',
                'channels' => json_encode(['database', 'email'])
            ],
            [
                'type' => 'loan_canceled',
                'title' => 'Loan Canceled',
                'message' => 'Your loan application #{{loan_number}} has been canceled.',
                'channels' => json_encode(['database', 'email'])
            ],
            // Grant notifications
            [
                'type' => 'grant_approved',
                'title' => 'Grant Approved',
                'message' => 'Your grant application #{{grant_number}} for KES {{amount}} has been approved.',
                'channels' => json_encode(['database', 'email'])
            ],
            [
                'type' => 'grant_rejected',
                'title' => 'Grant Rejected',
                'message' => 'Your grant application #{{grant_number}} has been rejected. {{remarks}}',
                'channels' => json_encode(['database', 'email'])
            ],
            // Payment and deduction notifications
            [
                'type' => 'payment_received',
                'title' => 'Payment Received',
                'message' => 'Payment of KES {{amount}} received for loan #{{loan_number}}. New balance: KES {{new_balance}}',
                'channels' => json_encode(['database', 'email'])
            ],
            [
                'type' => 'deduction_processed',
                'title' => 'Deduction Processed',
                'message' => 'Deduction of KES {{amount}} processed for loan #{{loan_number}}. New balance: KES {{new_balance}}',
                'channels' => json_encode(['database', 'email'])
            ],
            // Admin notifications
            [
                'type' => 'new_loan_application',
                'title' => 'New Loan Application',
                'message' => 'New loan application #{{loan_number}} for KES {{amount}} from {{applicant_name}}. Please review.',
                'channels' => json_encode(['database', 'email'])
            ],
            [
                'type' => 'new_grant_application',
                'title' => 'New Grant Application',
                'message' => 'New grant application #{{grant_number}} for KES {{amount}} from {{applicant_name}}. Please review.',
                'channels' => json_encode(['database', 'email'])
            ],
            [
                'type' => 'loan_ready_for_approval',
                'title' => 'Loan Ready for Approval',
                'message' => 'Loan #{{loan_number}} for KES {{amount}} from {{applicant_name}} is ready for approval. All guarantors have accepted.',
                'channels' => json_encode(['database', 'email'])
            ],
            [
                'type' => 'guarantor_rejected_loan',
                'title' => 'Guarantor Rejected Loan',
                'message' => 'Guarantor {{guarantor_name}} has rejected the guarantee request for loan #{{loan_number}} from {{applicant_name}}.',
                'channels' => json_encode(['database', 'email'])
            ],
        ];

        // Use firstOrCreate to avoid duplicates if running multiple times
        foreach ($notificationTemplates as $template) {
            NotificationTemplate::firstOrCreate(
                ['type' => $template['type']], // Check if type exists
                $template // Create if not exists
            );
        }
    }
}

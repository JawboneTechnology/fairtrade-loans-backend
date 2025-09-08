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
            ]
        ];

        // Use insertOrIgnore to avoid duplicates if running multiple times
        foreach ($notificationTemplates as $template) {
            NotificationTemplate::firstOrCreate(
                ['type' => $template['type']], // Check if type exists
                $template // Create if not exists
            );
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Services\LoanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyLoanInstallments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:notify-installments {--overdue : Include overdue loan notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send SMS notifications to users about monthly loan installments and overdue payments';

    protected LoanService $loanService;

    /**
     * Create a new command instance.
     */
    public function __construct(LoanService $loanService)
    {
        parent::__construct();
        $this->loanService = $loanService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = now();
        $totalSuccessCount = 0;
        $totalFailureCount = 0;
        $errors = [];
        
        // Log system activity
        $activityType = $this->option('overdue') ? 'overdue_notification_command' : 'installment_reminder_command';
        $description = $this->option('overdue') ? 'Sending overdue loan notifications' : 'Sending monthly installment reminders';
        
        $systemActivity = \App\Models\SystemActivity::logActivity(
            $activityType,
            $description,
            'command',
            'loans:notify-installments'
        );

        $this->info('Starting loan installment notifications...');
        
        try {
            // Send regular installment reminders
            $regularResults = $this->loanService->sendInstallmentReminders();
            $this->info("Regular installment reminders sent: {$regularResults['sent']} successful, {$regularResults['failed']} failed");
            
            $totalSuccessCount += $regularResults['sent'];
            $totalFailureCount += $regularResults['failed'];
            if (!empty($regularResults['errors'])) {
                $errors = array_merge($errors, $regularResults['errors']);
            }

            // Send overdue notifications if option is provided or if it's the appropriate time
            if ($this->option('overdue') || $this->shouldSendOverdueNotifications()) {
                $overdueResults = $this->loanService->sendOverdueNotifications();
                $this->info("Overdue loan notifications sent: {$overdueResults['sent']} successful, {$overdueResults['failed']} failed");
                
                $totalSuccessCount += $overdueResults['sent'];
                $totalFailureCount += $overdueResults['failed'];
                if (!empty($overdueResults['errors'])) {
                    $errors = array_merge($errors, $overdueResults['errors']);
                }
            }

            // Update activity counts
            $systemActivity->updateCounts($totalSuccessCount, $totalFailureCount);

            // Mark as completed
            $summary = [
                'notifications_sent' => $totalSuccessCount,
                'notifications_failed' => $totalFailureCount,
                'total_processed' => $totalSuccessCount + $totalFailureCount,
                'execution_time_seconds' => $startTime->diffInSeconds(now()),
                'overdue_mode' => $this->option('overdue'),
            ];

            if (empty($errors)) {
                $systemActivity->markAsCompleted($summary);
                $this->info('Loan installment notifications completed successfully');
                return Command::SUCCESS;
            } else {
                $systemActivity->markAsFailed(json_encode($errors));
                $this->error('Some notifications failed to send. Check logs for details.');
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $errorMessage = 'Error sending loan notifications: ' . $e->getMessage();
            $this->error($errorMessage);
            Log::error('Loan installment notification command failed: ' . $e->getMessage());
            
            $systemActivity->markAsFailed($errorMessage);
            return Command::FAILURE;
        }
    }

    /**
     * Determine if overdue notifications should be sent
     * This could be based on time of day, day of week, etc.
     */
    private function shouldSendOverdueNotifications(): bool
    {
        // Send overdue notifications on specific days or times
        // For example, send every Monday and Thursday
        $dayOfWeek = now()->dayOfWeek;
        return in_array($dayOfWeek, [1, 4]); // Monday = 1, Thursday = 4
    }
}
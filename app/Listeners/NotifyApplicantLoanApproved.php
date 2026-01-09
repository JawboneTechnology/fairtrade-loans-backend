<?php

namespace App\Listeners;

use App\Events\LoanApproved;
use App\Jobs\NotifyApplicantLoanStatusSMS;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class NotifyApplicantLoanApproved implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationService $notificationService;

    /**
     * Create the event listener.
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(LoanApproved $event): void
    {
        try {
            Log::info('LoanApproved event received, dispatching SMS notification job', [
                'loan_id' => $event->loan->id,
                'loan_number' => $event->loan->loan_number
            ]);

            // Dispatch the SMS notification job on the sms queue
            NotifyApplicantLoanStatusSMS::dispatch(
                $event->loan,
                'approved',
                $event->approvedAmount,
                $event->remarks
            )->onQueue('sms');

            // Create database notification for the employee
            $employee = $event->loan->employee;
            if ($employee) {
                $formattedAmount = number_format($event->loan->loan_amount, 2);
                $formattedApprovedAmount = number_format($event->approvedAmount, 2);
                
                $this->notificationService->create($employee, 'loan_approved', [
                    'loan_id' => $event->loan->id,
                    'loan_number' => $event->loan->loan_number,
                    'amount' => $formattedAmount,
                    'approved_amount' => $formattedApprovedAmount,
                    'remarks' => $event->remarks,
                    'action_url' => config('app.url') . '/loans/' . $event->loan->id,
                    'message' => "Your loan application #{$event->loan->loan_number} for KES {$formattedAmount} has been approved. Approved amount: KES {$formattedApprovedAmount}. Please wait 24 hours for funds to be disbursed.",
                ]);

                Log::info('Loan approval notification created for employee', [
                    'employee_id' => $employee->id,
                    'loan_id' => $event->loan->id
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error in NotifyApplicantLoanApproved listener', [
                'loan_id' => $event->loan->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}


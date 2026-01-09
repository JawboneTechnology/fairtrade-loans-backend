<?php

namespace App\Listeners;

use App\Events\LoanRejected;
use App\Jobs\NotifyApplicantLoanStatusSMS;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class NotifyApplicantLoanRejected implements ShouldQueue
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
    public function handle(LoanRejected $event): void
    {
        try {
            Log::info('LoanRejected event received, dispatching SMS notification job', [
                'loan_id' => $event->loan->id,
                'loan_number' => $event->loan->loan_number
            ]);

            // Dispatch the SMS notification job on the sms queue
            NotifyApplicantLoanStatusSMS::dispatch(
                $event->loan,
                'rejected',
                null,
                $event->remarks
            )->onQueue('sms');

            // Create database notification for the employee
            $employee = $event->loan->employee;
            if ($employee) {
                $this->notificationService->create($employee, 'loan_rejected', [
                    'loan_id' => $event->loan->id,
                    'loan_number' => $event->loan->loan_number,
                    'amount' => number_format($event->loan->loan_amount, 2),
                    'remarks' => $event->remarks ?? 'Please contact support for more information.',
                    'action_url' => config('app.url') . '/loans/' . $event->loan->id,
                ]);

                Log::info('Loan rejection notification created for employee', [
                    'employee_id' => $employee->id,
                    'loan_id' => $event->loan->id
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error in NotifyApplicantLoanRejected listener', [
                'loan_id' => $event->loan->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}


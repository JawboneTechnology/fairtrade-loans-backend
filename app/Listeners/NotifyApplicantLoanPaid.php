<?php

namespace App\Listeners;

use App\Events\LoanPaid;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class NotifyApplicantLoanPaid
{
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
    public function handle(LoanPaid $event): void
    {
        // Dispatch Job
        \App\Jobs\NotifyApplicantLoanPaid::dispatch($event->loan, $event->deduction, $event->transaction);

        // Create database notification for the employee
        $employee = $event->loan->employee;
        if ($employee) {
            $this->notificationService->create($employee, 'loan_paid', [
                'loan_id' => $event->loan->id,
                'loan_number' => $event->loan->loan_number,
                'amount' => number_format($event->loan->loan_amount, 2),
                'action_url' => config('app.url') . '/loans/' . $event->loan->id,
            ]);

            Log::info('Loan paid notification created for employee', [
                'employee_id' => $employee->id,
                'loan_id' => $event->loan->id
            ]);
        }
    }
}

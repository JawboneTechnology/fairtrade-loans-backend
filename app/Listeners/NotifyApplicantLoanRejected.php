<?php

namespace App\Listeners;

use App\Events\LoanRejected;
use App\Jobs\NotifyApplicantLoanStatusSMS;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class NotifyApplicantLoanRejected implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
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

        } catch (\Exception $e) {
            Log::error('Error in NotifyApplicantLoanRejected listener', [
                'loan_id' => $event->loan->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}


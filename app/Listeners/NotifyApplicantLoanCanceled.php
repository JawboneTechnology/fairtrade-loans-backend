<?php

namespace App\Listeners;

use App\Events\LoanCanceled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyApplicantLoanCanceled
{
    /**
     * Handle the event.
     */
    public function handle(LoanCanceled $event): void
    {
        // Notify Applicant Loan Canceled
        \App\Jobs\NotifyApplicantLoanCanceled::dispatch($event->loan);
    }
}

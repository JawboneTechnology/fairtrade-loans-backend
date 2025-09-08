<?php

namespace App\Listeners;

use App\Events\LoanPaid;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyApplicantLoanPaid
{
    /**
     * Handle the event.
     */
    public function handle(LoanPaid $event): void
    {
        // Dispatch Job
        \App\Jobs\NotifyApplicantLoanPaid::dispatch($event->loan, $event->deduction, $event->transaction);
    }
}

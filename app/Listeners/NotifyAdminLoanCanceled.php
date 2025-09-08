<?php

namespace App\Listeners;

use App\Events\LoanCanceled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyAdminLoanCanceled
{
    /**
     * Handle the event.
     */
    public function handle(LoanCanceled $event): void
    {
        // Notify Admin Loan Canceled
        \App\Jobs\NotifyAdminLoanCanceled::dispatch($event->loan);
    }
}

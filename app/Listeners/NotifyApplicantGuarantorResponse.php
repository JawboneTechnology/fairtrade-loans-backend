<?php

namespace App\Listeners;

use App\Events\LoanApplicantNotified;
use App\Models\Loan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyApplicantGuarantorResponse
{
    /**
     * Handle the event.
     */
    public function handle(LoanApplicantNotified $event): void
    {
        // Notify Applicant of the guarantors response
        \App\Jobs\NotifyApplicantGuarantorResponse::dispatch($event->loan, $event->response, $event->guarantorId);
    }
}

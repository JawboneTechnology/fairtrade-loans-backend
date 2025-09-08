<?php

namespace App\Listeners;

use App\Events\LoanApplicantNotified;
use App\Jobs\UpdateLoanStatusOnGuarantorResponse;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateApplicantLoanStatus
{
    /**
     * Handle the event.
     */
    public function handle(LoanApplicantNotified $event): void
    {
        // Check if all guarantors have accepted then change statue
        UpdateLoanStatusOnGuarantorResponse::dispatch($event->loan, $event->response, $event->guarantorId);
    }
}

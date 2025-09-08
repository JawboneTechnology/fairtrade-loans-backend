<?php

namespace App\Jobs;

use App\Models\Loan;
use App\Models\LoanType;
use App\Models\User;
use App\Notifications\GuarantorResponseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyUserGuaranterDecline implements ShouldQueue
{
    use Queueable;

    public $guarantorId;
    public $guaranteed;
    public $loan;

    /**
     * Create a new job instance.
     */
    public function __construct(User $guaranteed, Loan $loan, $guarantorId)
    {
        $this->guarantorId = $guarantorId;
        $this->guaranteed = $guaranteed;
        $this->loan = $loan;
    }

    /**
     * Notify the employee who requested the loan that the guarantor they selected have declined their request.
     */
    public function handle(): void
    {
        $guarantor = User::findOrFail($this->guarantorId);
        $guarantorName = $guarantor->first_name . ' ' . $guarantor->last_name;
        $guaranteeName = $this->guaranteed->first_name . ' ' . $this->guaranteed->last_name;
        $loanType = LoanType::findOrFail($this->loan->loan_type_id)->name;

        $guarantor->notify(new GuarantorResponseNotification($guarantorName, $guaranteeName, $loanType));
    }
}

<?php

namespace App\Jobs;

use App\Models\Loan;
use App\Models\LoanType;
use App\Models\User;
use App\Notifications\NotifyApplicantLoanCanceledNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class NotifyApplicantLoanCanceled implements ShouldQueue
{
    use Queueable;

    public $loan;

    /**
     * Create a new job instance.
     */
    public function __construct(Loan $loan)
    {
        $this->loan = $loan;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get applicant details
            $applicant = User::findOrFail($this->loan->employee_id);
            $applicantName = $applicant->first_name . ' ' . $applicant->last_name;
            $loanType = LoanType::findOrFail($this->loan->loan_type_id)->name;

            if (!$applicant) {
                Log::warning('Applicant not found');
                return;
            }

            $applicant->notify(new NotifyApplicantLoanCanceledNotification($applicantName, $this->loan, $loanType));

        } catch (ModelNotFoundException $e) {
            Log::error("Error in NotifyApplicantLoanCanceled job: " . $e->getMessage() . " Line: " . $e->getLine());
        }
    }
}

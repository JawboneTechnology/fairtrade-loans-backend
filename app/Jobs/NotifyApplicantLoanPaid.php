<?php

namespace App\Jobs;

use App\Models\Loan;
use App\Models\User;
use App\Models\Transaction;
use App\Models\LoanDeduction;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\NotifyApplicantLoanPaidNotification;

class NotifyApplicantLoanPaid implements ShouldQueue
{
    use Queueable;

    public $loan;
    public $deduction;
    public $transaction;

    /**
     * Create a new job instance.
     */
    public function __construct(Loan $loan, LoanDeduction $deduction, Transaction $transaction)
    {
        $this->loan = $loan;
        $this->deduction = $deduction;
        $this->transaction = $transaction;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $applicant = User::where('id', $this->loan->employee_id)->first();

            if (!$applicant) {
                Log::warning('User not found for loan number: ' . $this->loan->loan_number);
                return;
            }

            $applicantName = $applicant->first_name . ' ' . $applicant->last_name;

            $applicant->notify(new NotifyApplicantLoanPaidNotification($applicantName, $this->loan, $this->deduction, $this->transaction));
        } catch (\Throwable $th) {
            Log::error('Error sending email to applicant' . $th->getMessage() . PHP_EOL . $th->getTraceAsString());
        }
    }
}

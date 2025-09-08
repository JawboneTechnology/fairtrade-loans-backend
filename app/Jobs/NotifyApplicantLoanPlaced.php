<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\Loan;
use App\Models\User;
use App\Models\LoanType;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Notifications\NotifyApplicantLoanPlacedNotification;

class NotifyApplicantLoanPlaced implements ShouldQueue
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
            $applicant = User::findOrFail($this->loan->employee_id);

            // Fetch applicant and loan details
            $applicant = User::findOrFail($this->loan->employee_id);
            $applicantName = $applicant->first_name . ' ' . $applicant->last_name;
            $loanType = LoanType::findOrFail($this->loan->loan_type_id)->name;

            // Get guarantors' names as a comma-separated string
            $guarantors = User::whereIn('id', $this->loan->guarantors)->get()
                ->map(function ($guarantor) {
                    return $guarantor->first_name . ' ' . $guarantor->last_name;
                })
                ->implode(', ');

            // Applicant dashboard URL
            $applicantDashboardUrl = ""; // Update this route as needed

            // Notify the applicant
            $applicant->notify(new NotifyApplicantLoanPlacedNotification(
                $applicantName,
                $this->loan,
                $loanType,
                $guarantors,
                $applicantDashboardUrl
            ));
        } catch (\Throwable $exception) {
            Log::error('Error in NotifyApplicantLoanPlacedNotification job: ' . $exception->getMessage(), [
                'loan_id'     => $this->loan->id,
                'employee_id' => $this->loan->employee_id,
                'timestamp'   => Carbon::now()->toDateTimeString(),
                'exception'   => $exception,
            ]);
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\Loan;
use App\Models\LoanType;
use App\Models\User;
use App\Notifications\NotifyAdminLoanCanceledNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class NotifyAdminLoanCanceled implements ShouldQueue
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
            // Get admin email from environment
            $adminEmail = env('APP_SYSTEM_ADMIN');

            // Get Loan type
            $loanType = LoanType::findOrFail($this->loan->loan_type_id)->name;

            // Get Applicant Name
            $applicant = User::findOrFail($this->loan->employee_id);
            $applicantName = $applicant->first_name . ' ' . $applicant->last_name;

            // Get Guarantors names
            $guarantors = User::whereIn('id', $this->loan->guarantors)->get()
                ->map(function ($guarantor) {
                    return $guarantor->first_name . ' ' . $guarantor->last_name;
                })->implode(', ');

            // Get Admin Details
            $administrator = User::where('email', $adminEmail)->first();

            if (!$administrator) {
                Log::error("Admin with email {$adminEmail} not found.");
                return;
            }

            $adminName = $administrator->first_name . ' ' . $administrator->last_name;

            // Admin dashboard URL
            $adminDashboardUrl = route('admin.dashboard'); // Update with the correct endpoint

            // Notify admin of new loan application
            $administrator->notify(new NotifyAdminLoanCanceledNotification(
                $adminName,
                $applicantName,
                $guarantors,
                $this->loan,
                $loanType,
                $adminDashboardUrl
            ));
        } catch (\Exception $e) {
            Log::error("Error in NotifyAdminLoanCanceled job: " . $e->getMessage() . " Line: " . $e->getLine());
        }
    }
}

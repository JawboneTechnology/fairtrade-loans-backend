<?php

namespace App\Jobs;

use App\Models\Loan;
use App\Models\User;
use App\Models\LoanType;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\NotifyAdministratorNewLoanApplied;

class NotifyAdminNewLoanApplied implements ShouldQueue
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
            $adminPhone = env('APP_SYSTEM_ADMIN_PHONE');

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
            $administrator->notify(new NotifyAdministratorNewLoanApplied(
                $adminName,
                $applicantName,
                $guarantors,
                $this->loan,
                $loanType,
                $adminDashboardUrl
            ));

            // Send SMS to admin as well (queued)
                try {
                    if (!empty($administrator->phone_number)) {
                        $smsMessage = "New loan application from {$applicantName} for KES " . number_format($this->loan->loan_amount, 2) . ", Loan: {$this->loan->loan_number}. View: {$adminDashboardUrl}";

                        Log::info('Dispatching SendSMSJob for admin', ['phone' => $administrator->phone_number, 'loan_id' => $this->loan->id]);
                        SendSMSJob::dispatch($administrator->phone_number, $smsMessage)->onQueue('sms');

                        // Optional synchronous fallback for debugging (set FORCE_SEND_SMS_SYNC=true in .env)
                        if (env('FORCE_SEND_SMS_SYNC', false)) {
                            try {
                                app(SMSService::class)->sendSMS($administrator->phone_number, $smsMessage);
                                Log::info('Synchronous admin SMS sent (FORCE_SEND_SMS_SYNC enabled)', ['phone' => $administrator->phone_number]);
                            } catch (\Throwable $ex) {
                                Log::error('Synchronous admin SMS failed', ['error' => $ex->getMessage()]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to dispatch admin SMS for new loan application: ' . $e->getMessage());
                }
        } catch (\Exception $e) {
            Log::error("Error in NotifyAdminNewLoanApplied job: " . $e->getMessage() . " Line: " . $e->getLine());
        }
    }
}

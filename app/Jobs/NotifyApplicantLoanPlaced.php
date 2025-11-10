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
use App\Jobs\SendSMSJob;
use App\Services\SMSService;

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

            // Send SMS to applicant as well (queued)
            try {
                if (!empty($applicant->phone_number)) {
                    $smsMessage = "Dear {$applicantName}, your loan application for KES " . number_format($this->loan->loan_amount, 2) . " has been received. Loan number: {$this->loan->loan_number}.";

                    Log::info('Dispatching SendSMSJob for applicant', ['phone' => $applicant->phone_number, 'loan_id' => $this->loan->id]);
                    SendSMSJob::dispatch($applicant->phone_number, $smsMessage, $this->loan->employee_id)->onQueue('sms');

                    // Optional synchronous fallback for debugging (set FORCE_SEND_SMS_SYNC=true in .env)
                    if (env('FORCE_SEND_SMS_SYNC', false)) {
                        try {
                            app(SMSService::class)->sendSMS($applicant->phone_number, $smsMessage);
                            Log::info('Synchronous applicant SMS sent (FORCE_SEND_SMS_SYNC enabled)', ['phone' => $applicant->phone_number]);
                        } catch (\Throwable $ex) {
                            Log::error('Synchronous applicant SMS failed', ['error' => $ex->getMessage()]);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to dispatch applicant SMS for loan placed: ' . $e->getMessage());
            }
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

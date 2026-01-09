<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\Loan;
use App\Models\User;
use App\Models\LoanType;
use App\Services\NotificationService;
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
    public function handle(NotificationService $notificationService): void
    {
        try {
            $applicant = User::findOrFail($this->loan->employee_id);

            // Fetch applicant and loan details
            $applicantName = $applicant->first_name . ' ' . $applicant->last_name;
            $loanType = LoanType::findOrFail($this->loan->loan_type_id)->name;

            // Get guarantors' names as a comma-separated string
            $guarantors = User::whereIn('id', $this->loan->guarantors ?? [])->get()
                ->map(function ($guarantor) {
                    return $guarantor->first_name . ' ' . $guarantor->last_name;
                })
                ->implode(', ');

            // Applicant dashboard URL
            $applicantDashboardUrl = ""; // Update this route as needed

            // Notify the applicant via email
            $applicant->notify(new NotifyApplicantLoanPlacedNotification(
                $applicantName,
                $this->loan,
                $loanType,
                $guarantors,
                $applicantDashboardUrl
            ));

            // Create database notification for the employee
            $notificationService->create($applicant, 'loan_application_submitted', [
                'loan_id' => $this->loan->id,
                'loan_number' => $this->loan->loan_number,
                'amount' => number_format($this->loan->loan_amount, 2),
                'loan_type' => $loanType,
                'guarantors' => $guarantors,
                'action_url' => config('app.url') . '/loans/' . $this->loan->id,
            ]);

            Log::info('Loan application submitted notification created for employee', [
                'employee_id' => $applicant->id,
                'loan_id' => $this->loan->id
            ]);

            // Send SMS to applicant as well (queued)
            try {
                if (!empty($applicant->phone_number)) {
                    $smsService = app(\App\Services\SMSService::class);
                    
                    $templateData = [
                        'user_name' => $applicantName,
                        'loan_number' => $this->loan->loan_number,
                        'amount' => number_format($this->loan->loan_amount, 2),
                        'loan_type' => $loanType,
                    ];

                    Log::info('Sending SMS from template for applicant', ['phone' => $applicant->phone_number, 'loan_id' => $this->loan->id]);
                    $smsService->sendSMSFromTemplate($applicant->phone_number, 'loan_application_submitted', $templateData, $this->loan->employee_id);

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

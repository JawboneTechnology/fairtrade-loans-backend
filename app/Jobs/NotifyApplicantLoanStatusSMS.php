<?php

namespace App\Jobs;

use App\Models\Loan;
use App\Models\User;
use App\Services\SMSService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyApplicantLoanStatusSMS implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $loan;
    public $status; // 'approved' or 'rejected'
    public $approvedAmount;
    public $remarks;

    /**
     * Create a new job instance.
     */
    public function __construct(Loan $loan, string $status, ?float $approvedAmount = null, ?string $remarks = null)
    {
        $this->loan = $loan;
        $this->status = $status;
        $this->approvedAmount = $approvedAmount;
        $this->remarks = $remarks;
    }

    /**
     * Execute the job.
     */
    public function handle(SMSService $smsService): void
    {
        try {
            $employee = User::find($this->loan->employee_id);

            if (!$employee) {
                Log::warning('Employee not found for loan status SMS notification', [
                    'loan_id' => $this->loan->id,
                    'employee_id' => $this->loan->employee_id
                ]);
                return;
            }

            $recipientPhone = $employee->phone_number ?? null;

            if (empty($recipientPhone)) {
                Log::warning('Loan status SMS not sent: no phone number for employee', [
                    'loan_id' => $this->loan->id,
                    'employee_id' => $employee->id
                ]);
                return;
            }

            // Use template-based SMS
            $templateType = $this->status === 'approved' ? 'loan_approved' : 'loan_rejected';
            $amount = $this->approvedAmount ? number_format($this->approvedAmount, 2) : number_format($this->loan->loan_amount, 2);
            
            $templateData = [
                'user_name' => $employee->first_name,
                'loan_number' => $this->loan->loan_number,
                'amount' => number_format($this->loan->loan_amount, 2),
                'approved_amount' => $amount,
                'remarks' => $this->remarks ?? '',
            ];

            Log::info('Sending loan status SMS notification from template', [
                'phone' => $recipientPhone,
                'loan_id' => $this->loan->id,
                'status' => $this->status,
                'template_type' => $templateType
            ]);

            // Send SMS via SMS service using template
            $smsService->sendSMSFromTemplate($recipientPhone, $templateType, $templateData, $employee->id);

            Log::info('Loan status SMS notification sent successfully', [
                'phone' => $recipientPhone,
                'loan_id' => $this->loan->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send loan status SMS notification', [
                'loan_id' => $this->loan->id,
                'status' => $this->status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Build the SMS message based on loan status
     */
    private function buildMessage(User $employee): string
    {
        // This method is kept for backward compatibility but is no longer used
        // Template-based messaging is now used in handle()
        $loanNumber = $this->loan->loan_number;
        $employeeName = $employee->first_name;

        if ($this->status === 'approved') {
            $amount = $this->approvedAmount ? number_format($this->approvedAmount, 2) : number_format($this->loan->loan_amount, 2);
            $message = "Dear {$employeeName}, your loan application (Loan No: {$loanNumber}) has been approved for KES {$amount}.";
            
            if ($this->remarks) {
                $message .= " Remarks: {$this->remarks}";
            }
            
            $message .= " You will receive a notification once the money has been sent to your M-Pesa number.";
        } else {
            $message = "Dear {$employeeName}, your loan application (Loan No: {$loanNumber}) has been rejected.";
            
            if ($this->remarks) {
                $message .= " Remarks: {$this->remarks}";
            } else {
                $message .= " Please contact support for more information.";
            }
        }

        return $message;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('NotifyApplicantLoanStatusSMS job failed', [
            'loan_id' => $this->loan->id ?? null,
            'status' => $this->status ?? null,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}


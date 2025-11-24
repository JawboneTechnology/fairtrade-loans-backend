<?php

namespace App\Jobs;

use App\Models\LoanDeduction;
use App\Models\Loan;
use App\Models\User;
use App\Services\SMSService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendDeductionNotificationJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public string $deductionId;
    public string $loanId;
    public string $userId;
    public float $newLoanBalance;
    public string $deductionType;
    public string $sendVia;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $deductionId,
        string $loanId,
        string $userId,
        float $newLoanBalance,
        string $deductionType,
        string $sendVia = 'both'
    ) {
        $this->deductionId      = $deductionId;
        $this->loanId           = $loanId;
        $this->userId           = $userId;
        $this->newLoanBalance   = $newLoanBalance;
        $this->deductionType    = $deductionType;
        $this->sendVia          = $sendVia;
    }

    /**
     * Execute the job.
     */
    public function handle(SMSService $smsService): void
    {
        try {
            // Fetch the models
            $deduction = LoanDeduction::find($this->deductionId);
            $loan = Loan::find($this->loanId);
            $user = User::find($this->userId);

            if (!$deduction || !$loan || !$user) {
                Log::warning('=== MISSING DATA FOR DEDUCTION NOTIFICATION JOB ===');
                Log::warning(PHP_EOL . json_encode([
                    'deduction_found' => $deduction ? true : false,
                    'loan_found'      => $loan ? true : false,
                    'user_found'      => $user ? true : false,
                    'deduction_id'    => $this->deductionId
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return;
            }

            $userName = $user->first_name . ' ' . $user->last_name;

            // Send via email
            if (in_array($this->sendVia, ['email', 'both'])) {
                $this->sendDeductionNotificationViaEmail($user, $userName, $deduction, $loan);
            }

            // Send via SMS
            if (in_array($this->sendVia, ['sms', 'both'])) {
                $this->sendDeductionNotificationViaSms($user, $userName, $deduction, $loan, $smsService);
            }

        } catch (\Exception $e) {
            Log::error('=== DEDUCTION NOTIFICATION JOB FAILED ===');
            Log::error(PHP_EOL . json_encode([
                'deduction_id' => $this->deductionId,
                'user_id'      => $this->userId,
                'error'        => $e->getMessage(),
                'attempt'      => $this->attempts()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Send deduction notification via email
     */
    private function sendDeductionNotificationViaEmail(
        User $user,
        string $userName,
        LoanDeduction $deduction,
        Loan $loan
    ): void {
        try {
            $emailData = [
                'applicantName'   => $userName,
                'deduction'       => $deduction,
                'loan'            => $loan,
                'newLoanBalance'  => $this->newLoanBalance,
                'deductionType'   => $this->deductionType,
            ];

            Mail::send('emails.deduction-processed', $emailData, function ($message) use ($user, $deduction) {
                $message->to($user->email, $user->first_name . ' ' . $user->last_name)
                    ->subject('Loan Deduction Processed - ' . $deduction->loan_number);
            });

        } catch (\Exception $e) {
            Log::error('=== FAILED TO SEND DEDUCTION EMAIL ===');
            Log::error(PHP_EOL . json_encode([
                'user_id'      => $user->id,
                'email'        => $user->email,
                'deduction_id' => $deduction->id,
                'error'        => $e->getMessage()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            throw $e;
        }
    }

    /**
     * Send deduction notification via SMS
     */
    private function sendDeductionNotificationViaSms(
        User $user,
        string $userName,
        LoanDeduction $deduction,
        Loan $loan,
        SMSService $smsService
    ): void {
        try {
            $message = $this->getSmsMessageByDeductionType(
                $this->deductionType,
                $userName,
                $deduction->deduction_amount,
                $loan->loan_number,
                $this->newLoanBalance
            );

            $smsService->sendSMS(
                $user->phone_number,
                $message,
                $user->id,
                'Deduction Notification'
            );

        } catch (\Exception $e) {
            Log::error('=== FAILED TO SEND DEDUCTION SMS ===');
            Log::error(PHP_EOL . json_encode([
                'user_id'      => $user->id,
                'phone'        => $user->phone_number,
                'deduction_id' => $deduction->id,
                'error'        => $e->getMessage()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            throw $e;
        }
    }

    /**
     * Get SMS message based on deduction type
     */
    private function getSmsMessageByDeductionType(
        string $deductionType,
        string $userName,
        float $amount,
        string $loanNumber,
        float $newBalance
    ): string {
        $formattedAmount = number_format($amount, 2);
        $formattedBalance = number_format($newBalance, 2);

        return match ($deductionType) {
            'Manual' => "Dear {$userName}, Manual deduction of KES {$formattedAmount} has been processed for loan {$loanNumber}. New balance: KES {$formattedBalance}. Thank you!",
            
            'Automatic' => "Dear {$userName}, Automatic deduction of KES {$formattedAmount} has been processed for loan {$loanNumber}. New balance: KES {$formattedBalance}. Thank you!",
            
            'Bank_Transfer' => "Dear {$userName}, Your bank transfer of KES {$formattedAmount} for loan {$loanNumber} has been received. New balance: KES {$formattedBalance}. Thank you!",
            
            'Mobile_Money' => "Dear {$userName}, Your mobile money payment of KES {$formattedAmount} for loan {$loanNumber} has been received. New balance: KES {$formattedBalance}. Thank you!",
            
            'Online_Payment' => "Dear {$userName}, Your online payment of KES {$formattedAmount} for loan {$loanNumber} has been confirmed. New balance: KES {$formattedBalance}. Thank you!",
            
            'Cheque' => "Dear {$userName}, Your cheque payment of KES {$formattedAmount} for loan {$loanNumber} has been cleared. New balance: KES {$formattedBalance}. Thank you!",
            
            'Cash' => "Dear {$userName}, Cash payment of KES {$formattedAmount} for loan {$loanNumber} has been received. New balance: KES {$formattedBalance}. Thank you!",
            
            'Partial_Payments' => "Dear {$userName}, Partial payment of KES {$formattedAmount} for loan {$loanNumber} has been received. New balance: KES {$formattedBalance}. Thank you!",
            
            'Early_Repayments' => "Dear {$userName}, Early repayment of KES {$formattedAmount} for loan {$loanNumber} has been processed. New balance: KES {$formattedBalance}. Thank you for paying ahead!",
            
            'Penalty_Payments' => "Dear {$userName}, Penalty payment of KES {$formattedAmount} for loan {$loanNumber} has been received. New balance: KES {$formattedBalance}. Thank you!",
            
            'Refunds' => "Dear {$userName}, Refund of KES {$formattedAmount} has been processed for loan {$loanNumber}. New balance: KES {$formattedBalance}. Thank you!",
            
            default => "Dear {$userName}, Payment of KES {$formattedAmount} for loan {$loanNumber} has been received. New balance: KES {$formattedBalance}. Thank you!",
        };
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('=== DEDUCTION NOTIFICATION JOB FAILED PERMANENTLY ===');
        Log::error(PHP_EOL . json_encode([
            'deduction_id' => $this->deductionId,
            'user_id'      => $this->userId,
            'error'        => $exception->getMessage(),
            'max_attempts' => $this->tries
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}


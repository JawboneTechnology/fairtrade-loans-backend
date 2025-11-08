<?php

namespace App\Jobs;

use App\Models\MpesaTransaction;
use App\Models\Loan;
use App\Models\User;
use App\Services\SMSService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPaymentSuccessfulSMSJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * Job data
     */
    public string $transactionId;
    public string $loanId;
    public string $userId;
    public float $newLoanBalance;
    public string $paymentMethod;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $transactionId,
        string $loanId,
        string $userId,
        float $newLoanBalance,
        string $paymentMethod = 'M-Pesa'
    ) {
        $this->transactionId    = $transactionId;
        $this->loanId           = $loanId;
        $this->userId           = $userId;
        $this->newLoanBalance   = $newLoanBalance;
        $this->paymentMethod    = $paymentMethod;
    }

    /**
     * Execute the job.
     */
    public function handle(SMSService $smsService): void
    {
        try {
            // Fetch the models
            $transaction = MpesaTransaction::where('transaction_id', $this->transactionId)->first();
            $loan = Loan::find($this->loanId);
            $user = User::find($this->userId);

            if (!$transaction || !$loan || !$user) {
                Log::warning('Missing data for SMS job', [
                    'transaction_found' => $transaction ? true : false,
                    'loan_found'        => $loan ? true : false,
                    'user_found'        => $user ? true : false,
                    'transaction_id'    => $this->transactionId
                ]);
                return;
            }

            // Format phone number
            $phoneNumber = $user->phone_number;
            if (!str_starts_with($phoneNumber, '254')) {
                $phoneNumber = '254' . ltrim($phoneNumber, '0');
            }

            // Build message
            $message = $this->buildSMSMessage($transaction, $loan, $user);

            // Send SMS
            $smsService->sendSMS($phoneNumber, $message);

            Log::info('Payment success SMS sent via job', [
                'user_id'        => $user->id,
                'transaction_id' => $transaction->transaction_id,
                'phone_number'   => $phoneNumber,
                'loan_number'    => $loan->loan_number
            ]);

        } catch (\Exception $e) {
            Log::error('SMS job failed', [
                'transaction_id' => $this->transactionId,
                'user_id'        => $this->userId,
                'error'          => $e->getMessage(),
                'attempt'        => $this->attempts()
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Build SMS message
     */
    private function buildSMSMessage(MpesaTransaction $transaction, Loan $loan, User $user): string
    {
        $userName       = $user->first_name . ' ' . $user->last_name;
        $amount         = number_format($transaction->amount, 2);
        $newBalance     = number_format($this->newLoanBalance, 2);
        $loanNumber     = $loan->loan_number;
        $receiptNumber  = $transaction->mpesa_receipt_number ?? $transaction->transaction_id;
        $paymentDate    = $transaction->transaction_date ? 
            $transaction->transaction_date->format('d/m/Y H:i') : 
            now()->format('d/m/Y H:i');

        return "Dear {$userName}, your payment of KES {$amount} for loan {$loanNumber} has been received successfully. "
             . "Receipt: {$receiptNumber}. New loan balance: KES {$newBalance}. "
             . "Payment processed on {$paymentDate} via {$this->paymentMethod}. Thank you for choosing our services!";
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SMS job failed permanently', [
            'transaction_id' => $this->transactionId,
            'user_id'        => $this->userId,
            'loan_id'        => $this->loanId,
            'error'          => $exception->getMessage(),
            'attempts'       => $this->attempts()
        ]);
    }
}

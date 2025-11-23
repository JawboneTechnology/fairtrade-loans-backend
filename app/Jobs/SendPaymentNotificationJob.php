<?php

namespace App\Jobs;

use App\Models\MpesaTransaction;
use App\Models\Loan;
use App\Models\User;
use App\Services\SMSService;
use App\Notifications\PaymentReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPaymentNotificationJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public string $transactionId;
    public string $loanId;
    public string $userId;
    public float $newLoanBalance;
    public string $paymentMethod;
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
        string $transactionId,
        string $loanId,
        string $userId,
        float $newLoanBalance,
        string $paymentMethod = 'M-Pesa',
        string $sendVia = 'both'
    ) {
        $this->transactionId    = $transactionId;
        $this->loanId           = $loanId;
        $this->userId           = $userId;
        $this->newLoanBalance   = $newLoanBalance;
        $this->paymentMethod    = $paymentMethod;
        $this->sendVia          = $sendVia;
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
                Log::warning('=== MISSING DATA FOR PAYMENT NOTIFICATION JOB ===');
                Log::warning(PHP_EOL . json_encode([
                    'transaction_found' => $transaction ? true : false,
                    'loan_found'        => $loan ? true : false,
                    'user_found'        => $user ? true : false,
                    'transaction_id'    => $this->transactionId
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return;
            }

            $userName = $user->first_name . ' ' . $user->last_name;

            // Send via email
            if (in_array($this->sendVia, ['email', 'both'])) {
                $this->sendPaymentNotificationViaEmail($user, $userName, $transaction, $loan);
            }

            // Send via SMS
            if (in_array($this->sendVia, ['sms', 'both'])) {
                $this->sendPaymentNotificationViaSms($user, $userName, $transaction, $loan, $smsService);
            }

        } catch (\Exception $e) {
            Log::error('=== PAYMENT NOTIFICATION JOB FAILED ===');
            Log::error(PHP_EOL . json_encode([
                'transaction_id' => $this->transactionId,
                'user_id'        => $this->userId,
                'error'          => $e->getMessage(),
                'attempt'        => $this->attempts()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Send payment notification via email
     */
    private function sendPaymentNotificationViaEmail(
        User $user,
        string $userName,
        MpesaTransaction $transaction,
        Loan $loan
    ): void {
        try {
            $user->notify(new PaymentReceivedNotification(
                $userName,
                $transaction,
                $loan,
                $this->paymentMethod,
                $this->newLoanBalance // Pass the new balance
            ));
        } catch (\Exception $e) {
            Log::error('=== FAILED TO SEND PAYMENT NOTIFICATION VIA EMAIL ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Send payment notification via SMS
     */
    private function sendPaymentNotificationViaSms(
        User $user,
        string $userName,
        MpesaTransaction $transaction,
        Loan $loan,
        SMSService $smsService
    ): void {
        try {
            // Build SMS message
            $message = $this->buildSMSMessage($userName, $transaction, $loan);

            // Format phone number
            $phoneNumber = $this->formatPhoneNumber($user->phone_number);

            // Send SMS
            $smsService->sendSMS($phoneNumber, $message);
        } catch (\Exception $e) {
            Log::error('=== FAILED TO SEND PAYMENT NOTIFICATION VIA SMS ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
            // Don't throw - let email succeed even if SMS fails
        }
    }

    /**
     * Build the SMS message for payment success
     */
    private function buildSMSMessage(
        string $userName,
        MpesaTransaction $transaction,
        Loan $loan
    ): string {
        $amount         = number_format($transaction->amount, 2);
        $newBalance     = number_format($this->newLoanBalance, 2);
        $loanNumber     = $loan->loan_number;
        $receiptNumber  = $transaction->mpesa_receipt_number ?? $transaction->transaction_id;
        $paymentDate    = $transaction->transaction_date ? 
            $transaction->transaction_date->format('d/m/Y H:i') : 
            now()->format('d/m/Y H:i');

        return "Dear {$userName}, your payment of KES {$amount} for loan {$loanNumber} has been received successfully. "
             . "Receipt: {$receiptNumber}. New loan balance: KES {$newBalance}. "
             . "Payment processed on {$paymentDate} via {$this->paymentMethod}. Thank you!";
    }

    /**
     * Format phone number to international format
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove any non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // If phone starts with 0, replace with country code (assuming Kenya +254)
        if (substr($phone, 0, 1) === '0') {
            $phone = '+254' . substr($phone, 1);
        }
        
        // If phone doesn't start with +, add Kenya country code
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+254' . $phone;
        }

        return $phone;
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('=== PAYMENT NOTIFICATION JOB FAILED PERMANENTLY ===');
        Log::error(PHP_EOL . json_encode([
            'transaction_id' => $this->transactionId,
            'user_id'        => $this->userId,
            'loan_id'        => $this->loanId,
            'error'          => $exception->getMessage(),
            'attempts'       => $this->attempts()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}


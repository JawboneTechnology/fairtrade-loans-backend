<?php

namespace App\Listeners;

use App\Events\PaymentSuccessful;
use App\Jobs\SendPaymentSuccessfulSMSJob;
use App\Notifications\PaymentReceivedNotification;
use App\Services\SMSService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPaymentSuccessNotification implements ShouldQueue
{
    use InteractsWithQueue;
    

    /**
     * Handle the event.
     */
    public function handle(PaymentSuccessful $event, SMSService $smsService): void
    {
        try {
            // Option 1: Direct SMS sending (current approach)
            $this->sendSMSDirectly($event, $smsService);

            // Send email notification as well (mirrors OTP flow)
            $applicantName = $event->user->first_name . ' ' . $event->user->last_name;
            try {
                $event->user->notify(new PaymentReceivedNotification($applicantName, $event->transaction, $event->loan));
                Log::info('Payment received email notification queued', [
                    'user_id' => $event->user->id,
                    'transaction_id' => $event->transaction->transaction_id
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to queue payment email notification', ['error' => $e->getMessage()]);
            }

            // Option 2: Dispatch job for SMS sending (alternative robust approach)
            // $this->dispatchSMSJob($event);

        } catch (\Exception $e) {
            Log::error('Failed to handle payment success event', [
                'user_id'        => $event->user->id,
                'transaction_id' => $event->transaction->transaction_id,
                'error'          => $e->getMessage()
            ]);

            // Fallback to job dispatch if direct SMS fails
            $this->dispatchSMSJob($event);
        }
    }

    /**
     * Send SMS directly via listener
     */
    private function sendSMSDirectly(PaymentSuccessful $event): void
    {
        // Get user phone number
        $phoneNumber = $event->user->phone_number;
        
        // Ensure phone number is in correct format (254...)
        if (!str_starts_with($phoneNumber, '254')) {
            $phoneNumber = '254' . ltrim($phoneNumber, '0');
        }

        // Create payment success SMS message
        $message = $this->buildPaymentSuccessMessage($event);

    // Send SMS notification
    $smsService->sendSMS($phoneNumber, $message);

        Log::info('Payment success SMS sent directly', [
            'user_id'        => $event->user->id,
            'transaction_id' => $event->transaction->transaction_id,
            'phone_number'   => $phoneNumber,
            'payment_amount' => $event->transaction->amount,
            'new_balance'    => $event->newLoanBalance
        ]);
    }

    /**
     * Dispatch SMS job for robust processing
     */
    private function dispatchSMSJob(PaymentSuccessful $event): void
    {
        SendPaymentSuccessfulSMSJob::dispatch(
            $event->transaction->transaction_id,
            $event->loan->id,
            $event->user->id,
            $event->newLoanBalance,
            $event->paymentMethod
        )->onQueue('sms')->delay(now()->addSeconds(5)); // 5-second delay for processing

        Log::info('Payment success SMS job dispatched', [
            'user_id'        => $event->user->id,
            'transaction_id' => $event->transaction->transaction_id
        ]);
    }

    /**
     * Build the SMS message for payment success
     */
    private function buildPaymentSuccessMessage(PaymentSuccessful $event): string
    {
        $userName       = $event->user->first_name . ' ' . $event->user->last_name;
        $amount         = number_format($event->transaction->amount, 2);
        $newBalance     = number_format($event->newLoanBalance, 2);
        $loanNumber     = $event->loan->loan_number;
        $receiptNumber  = $event->transaction->mpesa_receipt_number;
        $paymentDate    = $event->transaction->transaction_date->format('d/m/Y H:i');

        return "Dear {$userName}, your payment of KES {$amount} for loan {$loanNumber} has been received successfully. "
             . "Receipt: {$receiptNumber}. New loan balance: KES {$newBalance}. "
             . "Payment processed on {$paymentDate} via {$event->paymentMethod}. Thank you!";
    }

    /**
     * Handle a job failure.
     */
    public function failed(PaymentSuccessful $event, \Throwable $exception): void
    {
        Log::error('Payment SMS notification job failed permanently', [
            'user_id'        => $event->user->id,
            'transaction_id' => $event->transaction->transaction_id,
            'error'          => $exception->getMessage()
        ]);
    }
}

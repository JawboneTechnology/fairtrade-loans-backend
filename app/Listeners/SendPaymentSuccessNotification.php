<?php

namespace App\Listeners;

use App\Events\PaymentSuccessful;
use App\Jobs\SendPaymentNotificationJob;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPaymentSuccessNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationService $notificationService;

    /**
     * Create the event listener.
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event - Dispatch job to send both email and SMS notifications.
     */
    public function handle(PaymentSuccessful $event): void
    {
        try {
            // Dispatch the job to handle both SMS and email notifications
            SendPaymentNotificationJob::dispatch(
                $event->transaction->transaction_id,
                $event->loan->id,
                $event->user->id,
                $event->newLoanBalance,
                $event->paymentMethod,
                'both' // Send via both SMS and email
            ); // Will use default queue

            // Create database notification for the employee
            $this->notificationService->create($event->user, 'payment_received', [
                'transaction_id' => $event->transaction->transaction_id,
                'loan_id' => $event->loan->id,
                'loan_number' => $event->loan->loan_number,
                'amount' => number_format($event->transaction->amount, 2),
                'new_balance' => number_format($event->newLoanBalance, 2),
                'payment_method' => $event->paymentMethod,
                'action_url' => config('app.url') . '/loans/' . $event->loan->id,
            ]);

            Log::info('Payment received notification created for employee', [
                'user_id' => $event->user->id,
                'loan_id' => $event->loan->id,
                'transaction_id' => $event->transaction->transaction_id
            ]);

        } catch (\Exception $e) {
            Log::error('=== FAILED TO DISPATCH PAYMENT NOTIFICATION JOB ===');
            Log::error(PHP_EOL . json_encode([
                'user_id'        => $event->user->id ?? null,
                'transaction_id' => $event->transaction->transaction_id ?? null,
                'error'          => $e->getMessage()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            // Re-throw to mark listener as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(PaymentSuccessful $event, \Throwable $exception): void
    {
        Log::error('=== PAYMENT NOTIFICATION LISTENER FAILED PERMANENTLY ===');
        Log::error(PHP_EOL . json_encode([
            'user_id'        => $event->user->id ?? null,
            'transaction_id' => $event->transaction->transaction_id ?? null,
            'error'          => $exception->getMessage()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

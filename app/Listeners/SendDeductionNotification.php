<?php

namespace App\Listeners;

use App\Events\DeductionProcessed;
use App\Jobs\SendDeductionNotificationJob;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendDeductionNotification implements ShouldQueue
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
    public function handle(DeductionProcessed $event): void
    {
        try {
            // Dispatch the job to handle both SMS and email notifications
            SendDeductionNotificationJob::dispatch(
                $event->deduction->id,
                $event->loan->id,
                $event->user->id,
                $event->newLoanBalance,
                $event->deductionType,
                'both' // Send via both SMS and email
            );

            // Create database notification for the employee
            $this->notificationService->create($event->user, 'deduction_processed', [
                'deduction_id' => $event->deduction->id,
                'loan_id' => $event->loan->id,
                'loan_number' => $event->loan->loan_number,
                'amount' => number_format($event->deduction->deduction_amount, 2),
                'new_balance' => number_format($event->newLoanBalance, 2),
                'deduction_type' => $event->deductionType,
                'action_url' => config('app.url') . '/loans/' . $event->loan->id,
            ]);

            Log::info('Deduction processed notification created for employee', [
                'user_id' => $event->user->id,
                'loan_id' => $event->loan->id,
                'deduction_id' => $event->deduction->id
            ]);

        } catch (\Exception $e) {
            Log::error('=== FAILED TO DISPATCH DEDUCTION NOTIFICATION JOB ===');
            Log::error(PHP_EOL . json_encode([
                'user_id'        => $event->user->id ?? null,
                'deduction_id'   => $event->deduction->id ?? null,
                'loan_id'        => $event->loan->id ?? null,
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
    public function failed(DeductionProcessed $event, \Throwable $exception): void
    {
        Log::error('=== DEDUCTION NOTIFICATION LISTENER FAILED PERMANENTLY ===');
        Log::error(PHP_EOL . json_encode([
            'user_id'        => $event->user->id ?? null,
            'deduction_id'   => $event->deduction->id ?? null,
            'error'          => $exception->getMessage()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}


<?php

namespace App\Listeners;

use App\Events\UserAccountDeleted;
use App\Jobs\SendAccountDeletionNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendAccountDeletionNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event - Dispatch job to send account deletion notification and perform deletion.
     */
    public function handle(UserAccountDeleted $event): void
    {
        try {
            // Dispatch the job to handle email notification and account deletion
            SendAccountDeletionNotificationJob::dispatch(
                $event->userId,
                $event->userEmail,
                $event->userName,
                $event->employeeId,
                $event->deletedBy
            );

        } catch (\Exception $e) {
            Log::error('=== FAILED TO DISPATCH ACCOUNT DELETION NOTIFICATION JOB ===');
            Log::error(PHP_EOL . json_encode([
                'user_id' => $event->userId ?? null,
                'user_email' => $event->userEmail ?? null,
                'deleted_by' => $event->deletedBy ?? 'System',
                'error' => $e->getMessage()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            // Re-throw to mark listener as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(UserAccountDeleted $event, \Throwable $exception): void
    {
        Log::error('=== ACCOUNT DELETION NOTIFICATION LISTENER FAILED PERMANENTLY ===');
        Log::error(PHP_EOL . json_encode([
            'user_id' => $event->userId ?? null,
            'user_email' => $event->userEmail ?? null,
            'error' => $exception->getMessage()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}


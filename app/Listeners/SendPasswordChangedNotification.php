<?php

namespace App\Listeners;

use App\Events\EmployeePasswordChanged;
use App\Jobs\SendPasswordChangedNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPasswordChangedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event - Dispatch job to send password change notification email.
     */
    public function handle(EmployeePasswordChanged $event): void
    {
        try {
            // Dispatch the job to handle email notification
            SendPasswordChangedNotificationJob::dispatch(
                $event->employee->id,
                $event->newPassword,
                $event->changedBy?->id
            );

        } catch (\Exception $e) {
            Log::error('=== FAILED TO DISPATCH PASSWORD CHANGED NOTIFICATION JOB ===');
            Log::error(PHP_EOL . json_encode([
                'employee_id' => $event->employee->id ?? null,
                'employee_email' => $event->employee->email ?? null,
                'changed_by' => $event->changedBy?->email ?? 'System',
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
    public function failed(EmployeePasswordChanged $event, \Throwable $exception): void
    {
        Log::error('=== PASSWORD CHANGED NOTIFICATION LISTENER FAILED PERMANENTLY ===');
        Log::error(PHP_EOL . json_encode([
            'employee_id' => $event->employee->id ?? null,
            'employee_email' => $event->employee->email ?? null,
            'error' => $exception->getMessage()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}


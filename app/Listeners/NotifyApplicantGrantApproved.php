<?php

namespace App\Listeners;

use App\Events\GrantApproved;
use App\Jobs\NotifyApplicantGrantStatusSMS;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class NotifyApplicantGrantApproved implements ShouldQueue
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
     * Handle the event.
     */
    public function handle(GrantApproved $event): void
    {
        try {
            Log::info('GrantApproved event received, dispatching SMS notification job', [
                'grant_id' => $event->grant->id
            ]);

            // Dispatch the SMS notification job on the sms queue
            NotifyApplicantGrantStatusSMS::dispatch(
                $event->grant,
                'approved',
                $event->adminNotes
            )->onQueue('sms');

            // Create database notification for the employee
            $user = $event->grant->user;
            if ($user) {
                $this->notificationService->create($user, 'grant_approved', [
                    'grant_id' => $event->grant->id,
                    'grant_number' => $event->grant->grant_number ?? $event->grant->id,
                    'amount' => number_format($event->grant->amount, 2),
                    'admin_notes' => $event->adminNotes,
                    'action_url' => config('app.url') . '/grants/' . $event->grant->id,
                ]);

                Log::info('Grant approval notification created for employee', [
                    'user_id' => $user->id,
                    'grant_id' => $event->grant->id
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error in NotifyApplicantGrantApproved listener', [
                'grant_id' => $event->grant->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}


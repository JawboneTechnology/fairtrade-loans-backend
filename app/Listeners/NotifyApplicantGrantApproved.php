<?php

namespace App\Listeners;

use App\Events\GrantApproved;
use App\Jobs\NotifyApplicantGrantStatusSMS;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class NotifyApplicantGrantApproved implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
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

        } catch (\Exception $e) {
            Log::error('Error in NotifyApplicantGrantApproved listener', [
                'grant_id' => $event->grant->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}


<?php

namespace App\Listeners;

use App\Events\StkPushRequested;
use App\Jobs\ProcessStkPushJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessStkPush
{
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
    public function handle(StkPushRequested $event): void
    {
        // Dispatch the job
        ProcessStkPushJob::dispatch(
            $event->phoneNumber,
            $event->amount,
            $event->accountReference,
            $event->transactionDescription
        );
    }
}

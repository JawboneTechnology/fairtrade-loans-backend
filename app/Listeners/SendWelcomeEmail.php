<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Jobs\SendWelcomeEmail as SendWelcomeEmailJob;

class SendWelcomeEmail
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
    public function handle(UserRegistered $event): void
    {
        // Dispatch Send welcome email job
        SendWelcomeEmailJob::dispatch($event->user, $event->password);
    }
}

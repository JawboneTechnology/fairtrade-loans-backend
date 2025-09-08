<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use Illuminate\Support\Facades\Log;

class LogUserRegistered
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

        // Log registered user into the logger file
        log::info("New User Name: {$event->user->first_name} {$event->user->last_name} Email: {$event->user->email} Registered at " . \Carbon\Carbon::now()->toDateTimeString());
    }
}

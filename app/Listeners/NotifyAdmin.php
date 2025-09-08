<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Jobs\NotifyAdministratorNewUser;

class NotifyAdmin
{
    /**
     * Handle the event.
     * Dispatch Notify Administrator About New User Job
     */
    public function handle(UserRegistered $event): void
    {
        NotifyAdministratorNewUser::dispatch($event->user);
    }
}

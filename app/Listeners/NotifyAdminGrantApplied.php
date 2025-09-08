<?php

namespace App\Listeners;

use App\Events\GrantApplied;
use App\Jobs\NotifyAdminGrantApplication;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyAdminGrantApplied
{
    /**
     * Handle the event.
     */
    public function handle(GrantApplied $event): void
    {
        NotifyAdminGrantApplication::dispatch($event->grant);
    }
}

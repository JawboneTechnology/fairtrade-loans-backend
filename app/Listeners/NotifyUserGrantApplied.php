<?php

namespace App\Listeners;

use App\Events\GrantApplied;
use App\Jobs\NotifyUserGrantApplication;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyUserGrantApplied
{
    /**
     * Handle the event.
     */
    public function handle(GrantApplied $event): void
    {
        NotifyUserGrantApplication::dispatch($event->grant);
    }
}

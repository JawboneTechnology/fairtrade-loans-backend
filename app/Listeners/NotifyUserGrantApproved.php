<?php

namespace App\Listeners;

use App\Events\GrantApproved;
use App\Jobs\NotifyUserGrantApproval;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyUserGrantApproved
{
    /**
     * Handle the event.
     */
    public function handle(GrantApproved $event): void
    {
        NotifyUserGrantApproval::dispatch($event->grant);
    }
}

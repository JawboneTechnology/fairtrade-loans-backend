<?php

namespace App\Listeners;

use App\Events\MiniStatementSent;
use App\Jobs\SendMiniStatement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleMiniStatement
{
    /**
     * Handle the event.
     */
    public function handle(MiniStatementSent $event): void
    {
        SendMiniStatement::dispatch($event->statement, $event->user);
    }
}

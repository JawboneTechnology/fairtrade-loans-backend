<?php

namespace App\Listeners;

use App\Models\User;
use App\Events\GuarantorNotified;
use Illuminate\Support\Facades\Log;
use App\Jobs\NotifyGuarantorOfloanRequest;

class SendGuarantorNotification
{
    /**
     * Handle the event.
     */
    public function handle(GuarantorNotified $event): void
    {
        // Retrieve the guarantor using the provided guarantor ID.
        $guarantor = User::findOrFail($event->guarantorId);

        if (!$guarantor) {
            Log::error("Guarantor with ID {$event->guarantorId} not found.");
            return;
        }

        // Dispatch a job
        NotifyGuarantorOfloanRequest::dispatch($guarantor, $event->loan, $event->notificationId);
    }
}

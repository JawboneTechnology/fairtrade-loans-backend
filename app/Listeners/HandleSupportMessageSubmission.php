<?php

namespace App\Listeners;

use App\Events\SupportMessageSubmitted;
use App\Jobs\SendSupportEmailJob;
use App\Jobs\SendSMSJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleSupportMessageSubmission implements ShouldQueue
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
    public function handle(SupportMessageSubmitted $event): void
    {
        // Dispatch email job
        SendSupportEmailJob::dispatch($event->supportData);

        // Dispatch SMS job using existing SendSMSJob
        $adminPhone = config('sms.admin_phone', '+254725134449');
        
        // Prepare SMS message
        $smsMessage = "New Support Message\n" .
                      "From: {$event->supportData['name']}\n" .
                      "Email: {$event->supportData['email']}\n" .
                      "Subject: {$event->supportData['subject']}\n" .
                      "Message: " . substr($event->supportData['message'], 0, 100) . 
                      (strlen($event->supportData['message']) > 100 ? '...' : '');

        SendSMSJob::dispatch($adminPhone, $smsMessage);
    }
}
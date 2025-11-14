<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Notifications\SupportMessageNotification;

class SendSupportEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $supportData;
    public string $recipientEmail;

    /**
     * Create a new job instance.
     */
    public function __construct(array $supportData, string $recipientEmail = null)
    {
        $this->supportData = $supportData;
        $this->recipientEmail = $recipientEmail ?? config('mail.support_email', 'support@fairtrade.com');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Try to get an admin user for the notification
            $adminUser = User::where('email', $this->recipientEmail)->first();
            
            if (!$adminUser) {
                // Create a temporary notifiable object if no admin user exists
                $adminUser = (object) [
                    'email' => $this->recipientEmail,
                    'name' => 'Support Team',
                    'routeNotificationForMail' => function () {
                        return $this->recipientEmail;
                    }
                ];
                
                // Use Laravel's on-demand notifications
                \Illuminate\Support\Facades\Notification::route('mail', $this->recipientEmail)
                    ->notify(new SupportMessageNotification($this->supportData));
            } else {
                // Use the admin user to send notification
                $adminUser->notify(new SupportMessageNotification($this->supportData));
            }

            Log::info('Support email sent successfully', [
                'recipient' => $this->recipientEmail,
                'sender_email' => $this->supportData['email'],
                'subject' => $this->supportData['subject'],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send support email', [
                'recipient' => $this->recipientEmail,
                'sender_email' => $this->supportData['email'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
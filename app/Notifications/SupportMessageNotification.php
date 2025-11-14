<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public array $supportData;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $supportData)
    {
        $this->supportData = $supportData;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Support Message: ' . $this->supportData['subject'])
            ->replyTo($this->supportData['email'], $this->supportData['name'])
            ->view('emails.support-message', [
                'supportData' => $this->supportData,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
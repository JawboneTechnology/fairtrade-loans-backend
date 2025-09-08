<?php

namespace App\Notifications;

use App\Models\Dependant;
use App\Models\Grant;
use App\Models\GrantType;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserGrantApprovedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Grant $grant,
        public ?Dependant $dependant,
        public ?GrantType $grantType,
        public User $applicant
    ){}

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
            ->subject('Congratulations! Your Grant Application Has Been Approved')
            ->view('emails.grant-application-approval-notification',
                [
                    'grant' => $this->grant,
                    'dependent' => $this->dependant,
                    'grantType' => $this->grantType,
                    'applicant' => $this->applicant
                ]
            );
    }
}

<?php

namespace App\Notifications;

use App\Models\Grant;
use App\Models\User;
use App\Models\Dependant;
use App\Models\GrantType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class AdminGrantAppliedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Grant $grant,
        public ?Dependant $dependant,
        public GrantType $grantType,
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
        ->subject('Grant Application Submitted - Action Required:')
        ->view('emails.grant-approval-notification',
            [
                'grant' => $this->grant,
                'dependent' => $this->dependant,
                'grantType' => $this->grantType,
                'applicant' => $this->applicant
            ]
        );
    }
}

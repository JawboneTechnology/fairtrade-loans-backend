<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeEmailNotification extends Notification
{
    use Queueable;

    public $user;
    public $password;
    public $appStoreUrl;
    public $playStoreUrl;
    public $verificationUrl;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $user, $verificationUrl, $appStoreUrl, $playStoreUrl, $password)
    {
        //
        $this->user = $user;
        $this->password = $password;
        $this->appStoreUrl = $appStoreUrl;
        $this->playStoreUrl = $playStoreUrl;
        $this->verificationUrl = $verificationUrl;
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
            ->subject('Welcome to Fairtrade loans')
            ->view('emails.new-registration', [
                'user' => $this->user,
                'password' => $this->password,
                'appStoreUrl' => $this->appStoreUrl ,
                'playStoreUrl' => $this->playStoreUrl,
                'verificationUrl' => $this->verificationUrl,
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

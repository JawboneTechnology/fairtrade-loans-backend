<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class newPasswordNotification extends Notification
{
    use Queueable;
    public $userPassword;
    public $userEmail;
    public $userName;
    public $verificationUrl;

    /**
     * Create a new notification instance.
     */
    public function __construct($userName, $userEmail, $userPassword, $verificationUrl)
    {
        $this->userName = $userName;
        $this->userPassword = $userPassword;
        $this->userEmail = $userEmail;
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
                    ->subject('New Login Credentials')
                    ->view('emails.registered-user-password',
                    [
                        'userPassword' => $this->userPassword,
                        'userName' => $this->userName,
                        'userEmail'=> $this->userEmail,
                        'verificationUrl' => $this->verificationUrl,
                        'loginUrl' => 'http://localhost:5173/admin/login']);
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

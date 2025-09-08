<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendOtpCodeNotification extends Notification
{
    use Queueable;

    public $otpCode;
    public $userName;

    /**
     * Create a new notification instance.
     */
    public function __construct($userName, $otpCode)
    {
        $this->userName = $userName;
        $this->otpCode = $otpCode;
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
            ->subject('Fairtrade OTP Code')
            ->view('emails.login-otp-code',
                [
                    'userName' => $this->userName,
                    'otpCode' => $this->otpCode,
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

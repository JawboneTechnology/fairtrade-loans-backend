<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Barryvdh\DomPDF\Facade\Pdf;

class SendMiniStatementNotification extends Notification
{
    use Queueable;

    public $userName;
    public $statement;

    /**
     * Create a new notification instance.
     */
    public function __construct($userName, $statement)
    {
        $this->userName = $userName;
        $this->statement = $statement;
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
        $pdf = Pdf::loadView('pdf.mini-statement', [
            'miniStatement' => $this->statement,
        ]);

        return (new MailMessage)
            ->subject('Fairtrade Loan Mini Statement')
            ->view('emails.loan-mini-statement', [
                'userName' => $this->userName,
                'miniStatement' => $this->statement,
            ])
            ->attachData($pdf->output(), $this->userName . ' mini-statement.pdf', [
                'mime' => 'application/pdf',
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

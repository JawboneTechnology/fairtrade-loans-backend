<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $applicantName;
    public $transaction;
    public $loan;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $applicantName, $transaction, $loan)
    {
        $this->applicantName = $applicantName;
        $this->transaction = $transaction;
        $this->loan = $loan;
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
            ->subject('Payment Received')
            ->view('emails.payment-received-notification', [
                'applicantName' => $this->applicantName,
                'transaction'   => $this->transaction,
                'loan'          => $this->loan,
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
            'loan_number' => $this->loan->loan_number ?? null,
            'amount' => $this->transaction->amount ?? null,
            'transaction_id' => $this->transaction->transaction_id ?? null,
        ];
    }
}

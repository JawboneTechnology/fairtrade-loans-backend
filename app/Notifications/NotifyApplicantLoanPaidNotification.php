<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NotifyApplicantLoanPaidNotification extends Notification
{
    use Queueable;

    public $loan;
    public $deduction;
    public $transaction;
    public $applicantName;

    /**
     * Create a new notification instance.
     */
    public function __construct($applicantName, $loan, $deduction, $transaction)
    {
        $this->loan = $loan;
        $this->deduction = $deduction;
        $this->transaction = $transaction;
        $this->applicantName = $applicantName;
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
            ->subject('Payment Received Notification')
            ->view('emails.payment-received-notification', [
                'loan' => $this->loan,
                'deduction' => $this->deduction,
                'transaction' => $this->transaction,
                'applicantName' => $this->applicantName,
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

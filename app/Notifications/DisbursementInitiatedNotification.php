<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisbursementInitiatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $applicantName;
    public $amount;
    public $loan;
    public $payload;

    public function __construct(string $applicantName, float $amount, $loan = null, $payload = null)
    {
        $this->applicantName = $applicantName;
        $this->amount = $amount;
        $this->loan = $loan;
        $this->payload = $payload;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $loanNumber = $this->loan->loan_number ?? 'N/A';

        return (new MailMessage)
            ->subject('Loan Disbursement Initiated')
            ->greeting("Hello {$this->applicantName},")
            ->line("A disbursement of KES {number_format($this->amount, 2)} has been initiated to your mobile number.")
            ->line("Loan: {$loanNumber}")
            ->line('We will notify you again once the payment is completed.')
            ->line('Thank you for using our services.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'amount' => $this->amount,
            'loan_number' => $this->loan->loan_number ?? null,
            'payload' => $this->payload,
        ];
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NotifyApplicantLoanCanceledNotification extends Notification
{
    use Queueable;

    public $loan;
    public $loanType;
    public $applicant;

    /**
     * Create a new notification instance.
     */
    public function __construct($applicant, $loan, $loanType)
    {
        $this->applicant = $applicant;
        $this->loan = $loan;
        $this->loanType = $loanType;
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
            ->subject('Loan Cancellation Confirmation')
            ->view('emails.notify-applicant-loan-canceled', [
                'loan' => $this->loan,
                'loanTypeName' => $this->loanType,
                'applicantName' => $this->applicant,
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

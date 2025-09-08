<?php

namespace App\Notifications;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NotifyAdminLoanReadyForApproval extends Notification
{
    use Queueable;

    public $loan;
    public $loanType;
    public $applicantname;

    /**
     * Create a new notification instance.
     */
    public function __construct($applicantName, Loan $loan, $loanType)
    {
        $this->applicantname = $applicantName;
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
            ->subject('Loan Ready - Loan Number: '. $this->loan->loan_number,)
            ->view('emails.loan-applied-ready', [
                'loan'                => $this->loan,
                'loanType'            => $this->loanType,
                'applicantName'       => $this->applicantname,
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

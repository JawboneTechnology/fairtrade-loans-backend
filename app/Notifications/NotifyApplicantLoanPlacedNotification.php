<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NotifyApplicantLoanPlacedNotification extends Notification
{
    use Queueable;

    public $loan;
    public $loanType;
    public $guarantors;
    public $applicantName;
    public $applicantDashboardUrl;

    /**
     * Create a new notification instance.
     */
    public function __construct($applicantName, $loan, $loanType, $guarantors, $applicantDashboardUrl)
    {
        $this->loan = $loan;
        $this->loanType = $loanType;
        $this->guarantors = $guarantors;
        $this->applicantName = $applicantName;
        $this->applicantDashboardUrl = $applicantDashboardUrl;
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
            ->subject('Loan Application Submitted')
            ->view('emails.notify-applicant-loan-placed', [
                'applicantName' => $this->applicantName,
                'loan' => $this->loan,
                'loanType' => $this->loanType,
                'guarantors' => $this->guarantors,
                'applicantDashboardUrl' => $this->applicantDashboardUrl,
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

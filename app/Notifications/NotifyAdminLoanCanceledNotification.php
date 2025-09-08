<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NotifyAdminLoanCanceledNotification extends Notification
{
    use Queueable;

    public $loan;
    public $loanType;
    public $adminName;
    public $guarantors;
    public $applicantName;
    public $adminDashboardUrl;

    /**
     * Create a new notification instance.
     */
    public function __construct($adminName, $applicantName, $guarantors, $loan, $loanType, $adminDashboardUrl)
    {
        $this->loan = $loan;
        $this->loanType = $loanType;
        $this->adminName = $adminName;
        $this->guarantors = $guarantors;
        $this->applicantName = $applicantName;
        $this->adminDashboardUrl = $adminDashboardUrl;
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
            ->subject('Loan Cancellation Notification')
            ->view('emails.notify-administrator-loan-canceled', [
                'loan' => $this->loan,
                'loanType' => $this->loanType,
                'adminName' => $this->adminName,
                'guarantors' => $this->guarantors,
                'applicantName' => $this->applicantName,
                'adminDashboardUrl' => $this->adminDashboardUrl,
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

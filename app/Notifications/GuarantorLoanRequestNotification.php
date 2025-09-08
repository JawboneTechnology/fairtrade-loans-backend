<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuarantorLoanRequestNotification extends Notification
{
    use Queueable;

    public $guarantorName;
    public $guarantorEmail;
    public $guarantorId;
    public $loanId;
    public $loanName;
    public $loan;
    public $liabilityAmount;
    public $notificationId;

    /**
     * Create a new notification instance.
     */
    public function __construct($guarantorName, $guarantorEmail, $guarantorId, $loanId, $loanName, $loan, $liabilityAmount, $notificationId)
    {
        $this->guarantorName = $guarantorName;
        $this->guarantorEmail = $guarantorEmail;
        $this->guarantorId = $guarantorId;
        $this->loanId = $loanId;
        $this->loanName = $loanName;
        $this->loan = $loan;
        $this->liabilityAmount = $liabilityAmount;
        $this->notificationId = $notificationId;
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
            ->subject('Loan Guarantee Request')
            ->view('emails.loan-guarantee-request',
                [
                    'name' => $this->guarantorName,
                    'email' => $this->guarantorEmail,
                    'guarantorId'=> $this->guarantorId,
                    'loanId' => $this->loanId,
                    'loanName' => $this->loanName,
                    'loan' => $this->loan,
                    'guarantorLiabilityAmount' => $this->liabilityAmount,
                    'acceptUrl' => 'http://127.0.0.1:8000/api/v1/loans/guarantor-response?loan_id='.$this->loanId.'&reason=Accepted by user '.$this->guarantorName.'&guarantor_id='.$this->guarantorId .'&response=accepted&notification_id=' . $this->notificationId,
                    'declineUrl' => 'http://127.0.0.1:8000/api/v1/loans/guarantor-response?loan_id='.$this->loanId.'&reason=Declined by user '.$this->guarantorName.'&guarantor_id='.$this->guarantorId .'&response=declined&notification_id=' . $this->notificationId,
                ]
            );
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

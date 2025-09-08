<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuarantorResponseNotification extends Notification
{
    use Queueable;

    public $guarantorName;
    public $applicantName;
    public $loan;
    public $response;
    public $responseDate;

    /**
     * Create a new notification instance.
     */
    public function __construct($guarantorName, $applicantName, $loan, $response, $responseDate)
    {
        $this->guarantorName = $guarantorName;
        $this->applicantName = $applicantName;
        $this->loan = $loan;
        $this->response = $response;
        $this->responseDate = $responseDate;
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
            ->subject('Loan Guarantee Response - Guarantor '. $this->response,)
            ->view('emails.loan-guarantor-response', [
                'applicantName'       => $this->applicantName,
                'loan'                => $this->loan,
                'guarantorName'       => $this->guarantorName,
                'response'            => $this->response,
                'responseDate'        => $this->responseDate
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

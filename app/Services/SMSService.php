<?php

namespace App\Services;

use DataTables;
use App\Jobs\SendSMSJob;
use App\Models\SmsMessage;
use AfricasTalking\SDK\AfricasTalking;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;

class SMSService
{
    protected $smsProvider;

    public function __construct()
    {
        // Initialize Africastalking
        $username = config('services.africastalking.username');
        $apiKey = config('services.africastalking.api_key');
        $this->smsProvider = new AfricasTalking($username, $apiKey);
        $this->smsProvider->sms();
    }

    /**
     * Send SMS to a recipient and store a copy in the database.
     *
     * @param string $recipient
     * @param string $message
     * @return SmsMessage
     */
    public function sendSMS(string $recipient, string $message): SmsMessage
    {
        $userId = Auth::id();

        try {
            // Send SMS via Africastalking
            $response = $this->smsProvider->send([
                'to' => $recipient,
                'message' => $message,
            ]);

            $status = $response['status'] === 'Success' ? 'sent' : 'failed';
            $providerResponse = json_encode($response);

        } catch (\Exception $e) {
            $status = 'failed';
            $providerResponse = $e->getMessage();

            logger('info', 'Message Not Sent', $e->getMessage());
        }

        // Store SMS in the database
        return SmsMessage::create([
            'recipient' => $recipient,
            'message' => $message,
            'status' => $status,
            'provider_response' => $providerResponse,
            'sent_by' => $userId,
        ]);
    }

    /**
     * Send bulk SMS messages.
     *
     * @param array $messages
     * @return void
     */
    public function sendBulkSMS(array $messages): void
    {
        foreach ($messages as $sms) {
            SendSMSJob::dispatch($sms['recipient'], $sms['message']);
        }
    }

    /**
     * Get all sent SMS messages.
     *
     * @return Collection
     */
    public function getSentSMS(): Collection
    {
        return SmsMessage::all();
    }

    /**
     * Get all sent SMS messages for DataTable.
     *
     * @return mixed
     */
    public function getSentSMSDataTable()
    {
        return DataTables::of(SmsMessage::query())->make(true);
    }
}

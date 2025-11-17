<?php

namespace App\Services;

use DataTables;
use App\Jobs\SendSMSJob;
use App\Models\SmsMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use AfricasTalking\SDK\AfricasTalking;
use Illuminate\Database\Eloquent\Collection;

class SMSService
{
    protected $sms;

    public function __construct()
    {
        try {
            $username = config('services.africastalking.username');
            $apiKey = config('services.africastalking.api_key');
            
            // Trim whitespace and stray quotes
            $username = $username ? trim($username, " \t\n\r\0\x0B\"'") : null;
            $apiKey = $apiKey ? trim($apiKey, " \t\n\r\0\x0B\"'") : null;
            
            if (empty($username) || empty($apiKey)) {
                throw new \Exception('Africa\'s Talking credentials not configured. Please check your .env file.');
            }
            
            $africasTalking = new AfricasTalking($username, $apiKey);
            $this->sms = $africasTalking->sms();

        } catch (\Exception $e) {
            Log::error('Failed to initialize Africa\'s Talking SDK: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send SMS to a recipient and store a copy in the database.
     *
     * @param string $recipient
     * @param string $message
     * @param int|null $sentBy
     * @return SmsMessage
     */
    public function sendSMS(string $recipient, string $message, ?string $sentBy = null): SmsMessage
    {
        $userId = $sentBy ?? Auth::id() ?? 0;
        $senderId = config('services.africastalking.sender_id', 'JAWBONETECH');

        try {
            // Validate recipient format
            $formattedRecipient = $this->formatPhoneNumber($recipient);

            $result = $this->sms->send([
                'to' => $formattedRecipient,
                'message' => $message,
                'from' => $senderId,
            ]);

            Log::info("=== AFRICA'S TALKING SMS RESPONSE ===");
            Log::info(PHP_EOL . json_encode(['response' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $status = 'failed';
            $providerResponse = $this->normalizeProviderResponse($result);

            $deliveryStatus = data_get($providerResponse, 'status');
            $recipients = data_get($providerResponse, 'data.SMSMessageData.Recipients', []);

            if ($deliveryStatus === 'success' && !empty($recipients)) {
                $firstRecipient = $recipients[0];
                $recipientStatus = data_get($firstRecipient, 'status');
                $status = strcasecmp($recipientStatus ?? '', 'Success') === 0 ? 'sent' : 'failed';

                if ($status === 'failed') {
                    Log::warning('=== SMS DELIVERY FAILED ===');
                    Log::warning(PHP_EOL . json_encode([
                        'recipient' => $formattedRecipient,
                        'status' => $recipientStatus,
                        'status_code' => data_get($firstRecipient, 'statusCode')
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                }
            } else {
                Log::warning('=== SMS API RETURNED NON-SUCCESS STATUS ===');
                Log::warning(PHP_EOL . json_encode([
                    'recipient' => $formattedRecipient,
                    'delivery_status' => $deliveryStatus,
                    'response' => $providerResponse,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

        } catch (\Exception $e) {
            $status = 'failed';
            $providerResponse = [
                'error' => true,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ];
            Log::error('=== SMS SENDING FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error(PHP_EOL . json_encode(['recipient' => $recipient], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return SmsMessage::create([
            'recipient' => $formattedRecipient ?? $recipient,
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

    /**
     * Test SMS sending method.
     *
     * @param string $recipient
     * @param string $message
     * @return string
     */
    public function testSendSMS(string $recipient, string $message): string
    {
        // Send SMS using the service's own method
        $this->sendSMS($recipient, $message);

        return "SMS sent to: $recipient";
    }

    /**
     * Format phone number to include country code if missing.
     * Handles various formats: 0712345678, 712345678, +254712345678, 254712345678
     * Returns format: +254712345678 (Africa's Talking accepts both + and without)
     */
    public function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove all non-numeric characters except +
        $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
        
        // If already starts with +, return as is
        if (str_starts_with($phoneNumber, '+')) {
            return $phoneNumber;
        }
        
        // If starts with 254 (Kenya country code without +), add +
        if (str_starts_with($phoneNumber, '254')) {
            return '+' . $phoneNumber;
        }
        
        // If starts with 0, remove 0 and add +254
        if (str_starts_with($phoneNumber, '0')) {
            return '+254' . substr($phoneNumber, 1);
        }
        
        // Default: assume it's a local number, add +254
        return '+254' . $phoneNumber;
    }

    private function normalizeProviderResponse($result): array
    {
        if (is_array($result)) {
            return $result;
        }

        if (is_object($result)) {
            return json_decode(json_encode($result), true) ?? [];
        }

        if (is_string($result)) {
            $decoded = json_decode($result, true);
            return is_array($decoded) ? $decoded : ['raw' => $result];
        }

        return ['raw' => $result];
    }
}
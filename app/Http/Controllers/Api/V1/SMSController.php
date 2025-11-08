<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use App\Services\SMSService;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkSmsRequest;
use App\Http\Requests\SendSmsRequest;

class SMSController extends Controller
{
    protected $smsService;

    public function __construct(SMSService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Send SMS and store in the database.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendSMS(SendSmsRequest $request)
    {
        $validated = $request->validated();

        try {
            $sms = $this->smsService->sendSMS($validated['recipient'], $validated['message']);

            return response()->json(['success' => true, 'message' => 'SMS sent successfully.', 'data' => $sms], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'SMS sending failed.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Send bulk SMS and queue the messages.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendBulkSMS(BulkSmsRequest $request)
    {
        $validated = $request->validated();

        // Fetch recipients based on the selection
        if ($validated['send_to_all']) {
            $recipients = User::pluck('phone_number')->toArray();
        } else {
            $recipients = User::whereIn('id', $validated['user_ids'])
                ->pluck('phone_number')
                ->toArray();
        }

        // Prepare messages
        $messages = array_map(function ($recipient) use ($validated) {
            return [
                'recipient' => $recipient,
                'message' => $validated['message'],
            ];
        }, $recipients);

        // Send SMS using the SMS Service
        $this->smsService->sendBulkSMS($messages);

        // Returning null for the data as it's not needed for this endpoint.
        return response()->json(['success' => true, 'message' => 'SMS queued successfully.', 'data' => null], 200);
    }

    /**
     * Get all SMS messages.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSentSMS()
    {
        return $this->smsService->getSentSMSDataTable();
    }

    /**
     * Test SMS endpoint to verify controller is working.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testSMS(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            // Basic test without validation
            $recipient = $request->input('recipient', '+254700000000'); // Default test number
            $message = $request->input('message', 'Test SMS from API');

            $sms = $this->smsService->sendSMS($recipient, $message);
            
            return response()->json([
                'success' => true, 
                'message' => 'SMS sent successfully.', 
                'data' => $sms
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => 'SMS sending failed.', 
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
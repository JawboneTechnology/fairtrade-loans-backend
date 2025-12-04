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

    /**
     * Get SMS messages for DataTables (Admin)
     * Supports server-side processing with pagination, searching, and sorting
     */
    public function getSmsMessagesForDataTables(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            // DataTables parameters
            $draw = $request->input('draw', 1);
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $searchValue = $request->input('search.value', '');
            $orderColumnIndex = $request->input('order.0.column', 0);
            $orderDirection = $request->input('order.0.dir', 'desc');
            
            // Additional filters
            $status = $request->input('status'); // sent, failed, pending
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            $sentBy = $request->input('sent_by'); // User ID

            // Column mapping for sorting
            $columns = [
                0 => 'id',
                1 => 'recipient',
                2 => 'message',
                3 => 'status',
                4 => 'sent_by',
                5 => 'created_at'
            ];
            
            $orderColumn = $columns[$orderColumnIndex] ?? 'created_at';

            // Base query with relationships
            $query = \App\Models\SmsMessage::with(['sender:id,first_name,last_name,email'])
                ->select([
                    'id',
                    'recipient',
                    'message',
                    'status',
                    'provider_response',
                    'sent_by',
                    'created_at',
                    'updated_at'
                ]);

            // Apply status filter
            if ($status) {
                $query->where('status', $status);
            }

            // Apply date range filter
            if ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            // Apply sent_by filter
            if ($sentBy) {
                $query->where('sent_by', $sentBy);
            }

            // Get total records before filtering
            $recordsTotal = \App\Models\SmsMessage::count();

            // Apply search filter
            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('recipient', 'like', "%{$searchValue}%")
                      ->orWhere('message', 'like', "%{$searchValue}%")
                      ->orWhere('status', 'like', "%{$searchValue}%")
                      ->orWhereHas('sender', function ($q) use ($searchValue) {
                          $q->where('first_name', 'like', "%{$searchValue}%")
                            ->orWhere('last_name', 'like', "%{$searchValue}%")
                            ->orWhere('email', 'like', "%{$searchValue}%");
                      });
                });
            }

            // Get filtered count
            $recordsFiltered = $query->count();

            // Apply sorting
            $query->orderBy($orderColumn, $orderDirection);

            // Apply pagination
            $smsMessages = $query->skip($start)->take($length)->get();

            // Format data for DataTables
            $data = $smsMessages->map(function ($sms) {
                return [
                    'id' => $sms->id,
                    'recipient' => $sms->recipient,
                    'message' => $sms->message,
                    'message_preview' => \Illuminate\Support\Str::limit($sms->message, 50),
                    'message_length' => strlen($sms->message),
                    'status' => $sms->status,
                    'status_badge' => $this->getStatusBadge($sms->status),
                    'provider_response' => $sms->provider_response,
                    'provider_status' => $this->extractProviderStatus($sms->provider_response),
                    'sender' => $sms->sender ? [
                        'id' => $sms->sender->id,
                        'name' => $sms->sender->first_name . ' ' . $sms->sender->last_name,
                        'email' => $sms->sender->email,
                    ] : [
                        'id' => null,
                        'name' => 'System',
                        'email' => 'system@auto',
                    ],
                    'sent_by' => $sms->sent_by,
                    'created_at' => $sms->created_at->format('Y-m-d H:i:s'),
                    'created_at_formatted' => $sms->created_at->format('d M Y, h:i A'),
                    'updated_at' => $sms->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            // DataTables response format
            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
                'success' => true
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('=== ERROR FETCHING SMS MESSAGES FOR DATATABLES ===');
            \Illuminate\Support\Facades\Log::error('Error: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return response()->json([
                'draw' => intval($request->input('draw', 1)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'success' => false,
                'error' => 'Failed to fetch SMS messages: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get status badge HTML/CSS class for DataTables
     */
    private function getStatusBadge(string $status): array
    {
        $badges = [
            'sent' => [
                'text' => 'Sent',
                'class' => 'badge-success',
                'color' => '#28a745'
            ],
            'pending' => [
                'text' => 'Pending',
                'class' => 'badge-warning',
                'color' => '#ffc107'
            ],
            'failed' => [
                'text' => 'Failed',
                'class' => 'badge-danger',
                'color' => '#dc3545'
            ],
            'queued' => [
                'text' => 'Queued',
                'class' => 'badge-info',
                'color' => '#17a2b8'
            ]
        ];

        return $badges[$status] ?? [
            'text' => ucfirst($status),
            'class' => 'badge-secondary',
            'color' => '#6c757d'
        ];
    }

    /**
     * Extract status from provider response
     */
    private function extractProviderStatus($providerResponse): ?string
    {
        if (!$providerResponse || !is_array($providerResponse)) {
            return null;
        }

        $recipients = data_get($providerResponse, 'data.SMSMessageData.Recipients', []);
        
        if (!empty($recipients)) {
            return data_get($recipients[0], 'status', null);
        }

        return data_get($providerResponse, 'status', null);
    }

    /**
     * Get SMS statistics for dashboard
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSMSStatistics(): \Illuminate\Http\JsonResponse
    {
        try {
            $statistics = $this->smsService->getSMSStatistics();

            return response()->json([
                'success' => true,
                'message' => 'SMS statistics retrieved successfully.',
                'data' => $statistics
            ], 200);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to retrieve SMS statistics: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve SMS statistics: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
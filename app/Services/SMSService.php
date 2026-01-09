<?php

namespace App\Services;

use DataTables;
use App\Jobs\SendSMSJob;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Services\SmsTemplateService;
use App\Services\SmsSettingsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use AfricasTalking\SDK\AfricasTalking;
use Illuminate\Database\Eloquent\Collection;

class SMSService
{
    protected $sms;
    protected SmsSettingsService $settingsService;
    protected SmsTemplateService $templateService;

    public function __construct(SmsSettingsService $settingsService = null, SmsTemplateService $templateService = null)
    {
        $this->settingsService = $settingsService ?? app(SmsSettingsService::class);
        $this->templateService = $templateService ?? app(SmsTemplateService::class);
        
        try {
            // Get settings from service
            $settings = $this->settingsService->getSettings();
            $provider = $settings['provider'] ?? [];
            
            $username = $provider['username'] ?? config('services.africastalking.username');
            $apiKey = $provider['api_key'] ?? config('services.africastalking.api_key');
            
            // Trim whitespace and stray quotes
            $username = $username ? trim($username, " \t\n\r\0\x0B\"'") : null;
            $apiKey = $apiKey ? trim($apiKey, " \t\n\r\0\x0B\"'") : null;
            
            if (empty($username) || empty($apiKey)) {
                throw new \Exception('Africa\'s Talking credentials not configured. Please check your .env file or SMS settings.');
            }
            
            $africasTalking = new AfricasTalking($username, $apiKey);
            $this->sms = $africasTalking->sms();

        } catch (\Exception $e) {
            Log::error('Failed to initialize Africa\'s Talking SDK: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send SMS from template
     *
     * @param string $recipient
     * @param string $templateType
     * @param array $data
     * @param string|null $sentBy
     * @return SmsMessage|null
     */
    public function sendSMSFromTemplate(string $recipient, string $templateType, array $data, ?string $sentBy = null): ?SmsMessage
    {
        // Check if SMS notifications are enabled
        if (!$this->settingsService->isSmsNotificationsEnabled()) {
            Log::info('SMS notifications are disabled, skipping SMS send', [
                'recipient' => $recipient,
                'template_type' => $templateType
            ]);
            return null;
        }

        try {
            $template = $this->templateService->getTemplateByType($templateType);
            
            if (!$template) {
                Log::warning('SMS template not found, falling back to direct message', [
                    'template_type' => $templateType,
                    'recipient' => $recipient
                ]);
                return null;
            }

            $message = $template->parseMessage($data);
            return $this->sendSMS($recipient, $message, $sentBy);
        } catch (\Exception $e) {
            Log::error('Error sending SMS from template: ' . $e->getMessage(), [
                'template_type' => $templateType,
                'recipient' => $recipient
            ]);
            return null;
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
        
        // Get sender ID from settings
        $settings = $this->settingsService->getSettings();
        $senderId = $settings['provider']['sender_id'] ?? config('services.africastalking.sender_id', 'JAWBONETECH');

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

    /**
     * Get comprehensive SMS statistics for dashboard
     *
     * @return array
     */
    public function getSMSStatistics(): array
    {
        try {
            // Total SMS overview
            $totalSMS = SmsMessage::count();
            $sentSMS = SmsMessage::where('status', 'sent')->count();
            $failedSMS = SmsMessage::where('status', 'failed')->count();
            $pendingSMS = SmsMessage::where('status', 'pending')->count();
            $queuedSMS = SmsMessage::where('status', 'queued')->count();

            // Success rate
            $successRate = $totalSMS > 0 
                ? round(($sentSMS / $totalSMS) * 100, 2)
                : 0;

            $failureRate = $totalSMS > 0
                ? round(($failedSMS / $totalSMS) * 100, 2)
                : 0;

            // Status distribution
            $statusDistribution = [
                'sent' => $sentSMS,
                'failed' => $failedSMS,
                'pending' => $pendingSMS,
                'queued' => $queuedSMS,
            ];

            // Monthly trends (last 12 months)
            $monthlyTrends = SmsMessage::where('created_at', '>=', now()->subMonths(12))
                ->select(
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                    DB::raw('count(*) as count'),
                    DB::raw('sum(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as sent_count'),
                    DB::raw('sum(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count')
                )
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get()
                ->toArray();

            // This month vs last month
            $thisMonthSMS = SmsMessage::whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count();

            $lastMonthSMS = SmsMessage::whereYear('created_at', now()->subMonth()->year)
                ->whereMonth('created_at', now()->subMonth()->month)
                ->count();

            $smsGrowth = $lastMonthSMS > 0
                ? round((($thisMonthSMS - $lastMonthSMS) / $lastMonthSMS) * 100, 2)
                : 0;

            // Today's statistics
            $todaySMS = SmsMessage::whereDate('created_at', now()->toDateString())->count();
            $todaySent = SmsMessage::whereDate('created_at', now()->toDateString())
                ->where('status', 'sent')
                ->count();
            $todayFailed = SmsMessage::whereDate('created_at', now()->toDateString())
                ->where('status', 'failed')
                ->count();

            // This week's statistics
            $thisWeekSMS = SmsMessage::where('created_at', '>=', now()->startOfWeek())->count();
            $thisWeekSent = SmsMessage::where('created_at', '>=', now()->startOfWeek())
                ->where('status', 'sent')
                ->count();

            // Top senders (users who sent the most SMS)
            $topSenders = SmsMessage::select('sent_by', DB::raw('count(*) as sms_count'))
                ->whereNotNull('sent_by')
                ->groupBy('sent_by')
                ->orderBy('sms_count', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    $user = \App\Models\User::find($item->sent_by);
                    return [
                        'user_id' => $item->sent_by,
                        'user_name' => $user ? trim($user->first_name . ' ' . ($user->middle_name ?? '') . ' ' . $user->last_name) : 'Unknown',
                        'email' => $user->email ?? null,
                        'employee_id' => $user->employee_id ?? null,
                        'sms_count' => $item->sms_count,
                    ];
                })
                ->toArray();

            // Recent SMS activity (last 20)
            $recentSMS = SmsMessage::with(['sender:id,first_name,last_name,email'])
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($sms) {
                    return [
                        'id' => $sms->id,
                        'recipient' => $sms->recipient,
                        'message_preview' => \Illuminate\Support\Str::limit($sms->message, 50),
                        'message_length' => strlen($sms->message),
                        'status' => $sms->status,
                        'sender' => $sms->sender ? [
                            'id' => $sms->sender->id,
                            'name' => $sms->sender->first_name . ' ' . $sms->sender->last_name,
                            'email' => $sms->sender->email,
                        ] : ['name' => 'System'],
                        'created_at' => $sms->created_at->format('Y-m-d H:i:s'),
                        'created_at_formatted' => $sms->created_at->format('d M Y, h:i A'),
                        'days_ago' => $sms->created_at->diffForHumans(),
                    ];
                })
                ->toArray();

            // Message length statistics
            $messageLengthStats = SmsMessage::selectRaw('
                AVG(LENGTH(message)) as avg_length,
                MIN(LENGTH(message)) as min_length,
                MAX(LENGTH(message)) as max_length,
                COUNT(*) as total_messages
            ')->first();

            // Provider response analysis
            $providerSuccessCount = SmsMessage::where('status', 'sent')
                ->whereNotNull('provider_response')
                ->count();

            $providerFailureCount = SmsMessage::where('status', 'failed')
                ->whereNotNull('provider_response')
                ->count();

            // Hourly distribution (last 24 hours)
            $hourlyDistribution = SmsMessage::where('created_at', '>=', now()->subDay())
                ->select(
                    DB::raw('HOUR(created_at) as hour'),
                    DB::raw('count(*) as count'),
                    DB::raw('sum(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as sent_count')
                )
                ->groupBy('hour')
                ->orderBy('hour')
                ->get()
                ->map(function ($item) {
                    return [
                        'hour' => $item->hour,
                        'hour_formatted' => sprintf('%02d:00', $item->hour),
                        'count' => $item->count,
                        'sent_count' => $item->sent_count,
                    ];
                })
                ->toArray();

            // Daily statistics (last 30 days)
            $dailyStats = SmsMessage::where('created_at', '>=', now()->subDays(30))
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('count(*) as count'),
                    DB::raw('sum(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as sent_count'),
                    DB::raw('sum(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count')
                )
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->limit(30)
                ->get()
                ->toArray();

            // Unique recipients count
            $uniqueRecipients = SmsMessage::distinct('recipient')->count('recipient');

            // Most contacted recipients
            $topRecipients = SmsMessage::select('recipient', DB::raw('count(*) as sms_count'))
                ->groupBy('recipient')
                ->orderBy('sms_count', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'recipient' => $item->recipient,
                        'sms_count' => $item->sms_count,
                    ];
                })
                ->toArray();

            return [
                'overview' => [
                    'total_sms' => $totalSMS,
                    'sent_sms' => $sentSMS,
                    'failed_sms' => $failedSMS,
                    'pending_sms' => $pendingSMS,
                    'queued_sms' => $queuedSMS,
                    'unique_recipients' => $uniqueRecipients,
                ],
                'performance' => [
                    'success_rate' => $successRate,
                    'failure_rate' => $failureRate,
                    'provider_success_count' => $providerSuccessCount,
                    'provider_failure_count' => $providerFailureCount,
                ],
                'status_distribution' => $statusDistribution,
                'trends' => [
                    'this_month' => $thisMonthSMS,
                    'last_month' => $lastMonthSMS,
                    'growth_percentage' => $smsGrowth,
                    'monthly_trends' => $monthlyTrends,
                    'daily_stats' => $dailyStats,
                ],
                'time_periods' => [
                    'today' => [
                        'total' => $todaySMS,
                        'sent' => $todaySent,
                        'failed' => $todayFailed,
                    ],
                    'this_week' => [
                        'total' => $thisWeekSMS,
                        'sent' => $thisWeekSent,
                    ],
                ],
                'message_statistics' => [
                    'average_length' => round($messageLengthStats->avg_length ?? 0, 2),
                    'min_length' => $messageLengthStats->min_length ?? 0,
                    'max_length' => $messageLengthStats->max_length ?? 0,
                    'total_messages' => $messageLengthStats->total_messages ?? 0,
                ],
                'top_senders' => $topSenders,
                'top_recipients' => $topRecipients,
                'hourly_distribution' => $hourlyDistribution,
                'recent_activity' => [
                    'recent_sms' => $recentSMS,
                ],
                'generated_at' => now()->toDateTimeString(),
            ];

        } catch (\Exception $e) {
            Log::error('Error generating SMS statistics: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            throw new \Exception('Error generating SMS statistics: ' . $e->getMessage());
        }
    }
}
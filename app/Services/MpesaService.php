<?php

namespace App\Services;

use App\Models\MpesaTransaction;
use App\Models\User;
use App\Models\Loan;
use App\Events\PaymentSuccessful;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Iankumu\Mpesa\Facades\Mpesa;
use Illuminate\Support\Str;

class MpesaService
{
    /**
     * Initiate STK Push payment
     */
    public function initiateStkPush(array $data): array
    {
        try {
            // Refresh token before making API call
            $tokenRefresh = $this->refreshAccessToken();
            if (!$tokenRefresh['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to refresh access token: ' . $tokenRefresh['error']
                ];
            }

            // Create pending transaction record
            $transaction = MpesaTransaction::create([
                'phone_number' => $data['phone_number'],
                'amount' => $data['amount'],
                'account_reference' => $data['account_reference'],
                'transaction_description' => $data['transaction_description'],
                'transaction_type' => 'STK_PUSH',
                'status' => 'PENDING',
                'user_id' => $data['user_id'] ?? null,
                'loan_id' => $data['loan_id'] ?? null,
                'payment_method' => $data['payment_method'] ?? 'APP',
            ]);

            // Initiate STK Push via M-Pesa API
            // Method signature: stkpush($phonenumber, $amount, $account_number, $callbackurl = null)
            $response = Mpesa::stkpush(
                $data['phone_number'],
                $data['amount'],
                $data['account_reference'],
                route('mpesa.stk-callback')
            );

            // The response is an HTTP Client Response object, convert to array
            $responseData = [];
            if (method_exists($response, 'json')) {
                $responseData = $response->json();
            } elseif (method_exists($response, 'body')) {
                $responseData = json_decode($response->body(), true) ?? [];
            } elseif (is_array($response)) {
                $responseData = $response;
            }

            Log::info('=== STK PUSH RESPONSE ===');
            Log::info(PHP_EOL . json_encode([
                'response_data' => $responseData,
                'status' => method_exists($response, 'status') ? $response->status() : 'unknown'
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Update transaction with M-Pesa response
            if (isset($responseData['CheckoutRequestID'])) {
                $transaction->update([
                    'checkout_request_id' => $responseData['CheckoutRequestID'],
                    'merchant_request_id' => $responseData['MerchantRequestID'] ?? null,
                ]);

                Log::info('=== STK PUSH INITIATED SUCCESSFULLY ===');
                Log::info(PHP_EOL . json_encode([
                    'environment' => $tokenRefresh['environment'],
                    'transaction_id' => $transaction->transaction_id,
                    'checkout_request_id' => $responseData['CheckoutRequestID']
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return [
                    'success' => true,
                    'message' => 'STK Push initiated successfully',
                    'data' => [
                        'transaction_id' => $transaction->transaction_id,
                        'checkout_request_id' => $responseData['CheckoutRequestID'],
                        'merchant_request_id' => $responseData['MerchantRequestID'] ?? null,
                        'environment' => $tokenRefresh['environment'],
                    ]
                ];
            } else {
                // STK Push failed
                $transaction->update([
                    'status' => 'FAILED',
                    'result_desc' => $responseData['errorMessage'] ?? 'Failed to initiate STK Push'
                ]);

                Log::error('=== STK PUSH INITIATION FAILED ===');
                Log::error(PHP_EOL . json_encode([
                    'response_data' => $responseData,
                    'response_code' => $responseData['ResponseCode'] ?? $responseData['errorCode'] ?? 'unknown'
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return [
                    'success' => false,
                    'message' => 'Failed to initiate STK Push',
                    'error' => $responseData
                ];
            }

        } catch (\Exception $e) {
            Log::error('=== STK PUSH SERVICE ERROR ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ' (Line: ' . $e->getLine() . ')');
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return [
                'success' => false,
                'message' => 'STK Push service error',
                'error' => $e->getMessage() . ' - ' . $e->getLine() . ' - ' . $e->getFile()
            ];
        }
    }

    /**
     * Process STK Push callback
     */
    public function processStkCallback(array $callbackData): void
    {
        try {
            $stkCallback = $callbackData['Body']['stkCallback'];
            $checkoutRequestId = $stkCallback['CheckoutRequestID'];
            $resultCode = $stkCallback['ResultCode'];

            // Find the transaction
            $transaction = MpesaTransaction::where('checkout_request_id', $checkoutRequestId)->first();

            if (!$transaction) {
                Log::warning('Transaction not found for checkout request ID: ' . $checkoutRequestId);
                return;
            }

            if ($resultCode == 0) {
                // Payment successful
                $callbackMetadata = $stkCallback['CallbackMetadata']['Item'];
                
                $amount = null;
                $mpesaReceiptNumber = null;
                $phoneNumber = null;
                $transactionDate = null;

                foreach ($callbackMetadata as $item) {
                    switch ($item['Name']) {
                        case 'Amount':
                            $amount = $item['Value'];
                            break;
                        case 'MpesaReceiptNumber':
                            $mpesaReceiptNumber = $item['Value'];
                            break;
                        case 'PhoneNumber':
                            $phoneNumber = $item['Value'];
                            break;
                        case 'TransactionDate':
                            $transactionDate = date('Y-m-d H:i:s', strtotime($item['Value']));
                            break;
                    }
                }

                // Update transaction as successful
                $transaction->update([
                    'status' => 'SUCCESS',
                    'result_code' => $resultCode,
                    'result_desc' => $stkCallback['ResultDesc'],
                    'mpesa_receipt_number' => $mpesaReceiptNumber,
                    'transaction_date' => $transactionDate,
                    'callback_data' => $callbackData,
                ]);

                // Process the payment (e.g., update loan balance)
                if ($transaction->loan_id) {
                    $this->processLoanPayment($transaction);
                }

                Log::info('=== STK PUSH PAYMENT PROCESSED SUCCESSFULLY ===');
                Log::info(PHP_EOL . json_encode([
                    'transaction_id' => $transaction->transaction_id,
                    'mpesa_receipt' => $mpesaReceiptNumber,
                    'amount' => $amount
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            } else {
                // Payment failed
                $transaction->update([
                    'status' => 'FAILED',
                    'result_code' => $resultCode,
                    'result_desc' => $stkCallback['ResultDesc'],
                    'callback_data' => $callbackData,
                ]);

                Log::warning('=== STK PUSH PAYMENT FAILED ===');
                Log::warning(PHP_EOL . json_encode([
                    'transaction_id' => $transaction->transaction_id,
                    'result_code' => $resultCode,
                    'result_desc' => $stkCallback['ResultDesc']
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

        } catch (\Exception $e) {
            Log::error('=== ERROR PROCESSING STK CALLBACK ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
        }
    }

    /**
     * Validate paybill payment (Method 2)
     */
    public function validatePaybillPayment(string $billRefNumber, float $amount): array
    {
        try {
            // Try to find loan by loan number first
            $loan = Loan::where('loan_number', $billRefNumber)->first();
            
            if (!$loan) {
                // Try to find loan by employee ID if loan number not found
                $user = User::where('employee_id', $billRefNumber)->first();
                if ($user) {
                    // Get the user's active loan (assuming one active loan per user)
                    $loan = Loan::where('employee_id', $user->id)
                        ->where('loan_status', 'approved')
                        ->where('loan_balance', '>', 0)
                        ->first();
                }
            }

            if (!$loan) {
                return [
                    'valid' => false,
                    'reason' => 'Invalid loan number or employee ID'
                ];
            }

            // Check if loan is active and has balance
            if ($loan->loan_status !== 'approved') {
                return [
                    'valid' => false,
                    'reason' => 'Loan is not active'
                ];
            }

            if ($loan->loan_balance <= 0) {
                return [
                    'valid' => false,
                    'reason' => 'Loan is fully paid'
                ];
            }

            // Check if payment amount is reasonable
            if ($amount > $loan->loan_balance) {
                return [
                    'valid' => false,
                    'reason' => 'Payment amount exceeds loan balance'
                ];
            }

            if ($amount < 1) {
                return [
                    'valid' => false,
                    'reason' => 'Invalid payment amount'
                ];
            }

            return [
                'valid'         => true,
                'loan_id'       => $loan->id,
                'loan_number'   => $loan->loan_number,
                'loan_balance'  => $loan->loan_balance,
                'employee_id'   => $loan->employee_id
            ];

        } catch (\Exception $e) {
            Log::error('=== PAYBILL VALIDATION ERROR ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
            return [
                'valid' => false,
                'reason' => 'Validation system error'
            ];
        }
    }

    /**
     * Process paybill payment (Method 2)
     */
    public function processPaybillPayment(array $paymentData): array
    {
        try {
            $billRefNumber = $paymentData['BillRefNumber'] ?? '';
            $amount = $paymentData['TransAmount'] ?? 0;
            $phoneNumber = $paymentData['MSISDN'] ?? '';
            $transactionId = $paymentData['TransID'] ?? '';

            // Validate and get loan information
            $validation = $this->validatePaybillPayment($billRefNumber, $amount);
            
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['reason']
                ];
            }

            $loan = Loan::find($validation['loan_id']);
            
            // Create transaction record
            $transaction = MpesaTransaction::create([
                'phone_number'             => $phoneNumber,
                'amount'                   => $amount,
                'account_reference'        => $billRefNumber,
                'transaction_description'  => "Paybill payment for loan {$loan->loan_number}",
                'transaction_type'         => 'C2B',
                'status'                   => 'SUCCESS',
                'mpesa_receipt_number'     => $transactionId,
                'transaction_date'         => now(),
                'callback_data'            => $paymentData,
                'user_id'                  => $loan->employee_id,
                'loan_id'                  => $loan->id,
                'payment_method'           => 'PAYBILL'
            ]);

            // Process the loan payment
            $this->processLoanPayment($transaction);

            Log::info('=== PAYBILL PAYMENT PROCESSED SUCCESSFULLY ===');
            Log::info(PHP_EOL . json_encode([
                'transaction_id'    => $transaction->transaction_id,
                'loan_number'       => $loan->loan_number,
                'amount'            => $amount,
                'phone'             => $phoneNumber
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return [
                'success' => true,
                'message' => 'Payment processed successfully',
                'transaction_id' => $transaction->transaction_id,
                'loan_id' => $loan->id
            ];

        } catch (\Exception $e) {
            Log::error('=== ERROR PROCESSING PAYBILL PAYMENT ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Payment processing failed'
            ];
        }
    }

    /**
     * Process C2B payment (Legacy method - kept for compatibility)
     */
    public function processC2BPayment(array $paymentData): void
    {
        // This now calls the new paybill payment method
        $this->processPaybillPayment($paymentData);
    }

    /**
     * Process loan payment
     */
    private function processLoanPayment(MpesaTransaction $transaction): void
    {
        DB::beginTransaction();
        
        try {
            if (!$transaction->loan_id) {
                return;
            }

            $loan = Loan::find($transaction->loan_id);
            
            if (!$loan) {
                Log::warning('Loan not found for transaction: ' . $transaction->transaction_id);
                return;
            }

            $user = User::find($loan->employee_id);
            
            if (!$user) {
                Log::warning('User not found for loan: ' . $loan->id);
                return;
            }

            // Calculate new loan balance
            $oldBalance = $loan->loan_balance;
            $newBalance = max(0, $oldBalance - $transaction->amount);

            // Update loan balance and status
            $updateData = ['loan_balance' => $newBalance];
            
            // Mark loan as completed when balance reaches zero
            if ($newBalance <= 0) {
                $updateData['loan_status'] = 'completed';
                Log::info('=== LOAN FULLY PAID - MARKING AS COMPLETED ===');
                Log::info(PHP_EOL . json_encode([
                    'loan_id' => $loan->id,
                    'loan_number' => $loan->loan_number,
                    'final_payment_amount' => $transaction->amount
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
            
            $loan->update($updateData);

            // Create a transaction record in your existing transactions table
            $loanTransaction = \App\Models\Transaction::create([
                'id'                    => Str::uuid(),
                'loan_id'               => $loan->id,
                'employee_id'           => $loan->employee_id,
                'amount'                => $transaction->amount,
                'payment_type'          => 'Mobile_Money',
                'transaction_reference' => $transaction->mpesa_receipt_number ?? $transaction->transaction_id,
                'transaction_date'      => $transaction->transaction_date ?? now(),
            ]);

            // Determine payment method for SMS
            $paymentMethod = $transaction->payment_method === 'PAYBILL' ? 'M-Pesa Paybill' : 'M-Pesa (App)';

            //TODO: Fire payment successful event for SMS notification
            event(new PaymentSuccessful(
                $transaction,
                $loan,
                $user,
                $newBalance,
                $paymentMethod
            ));

            DB::commit();

            Log::info('=== LOAN PAYMENT PROCESSED SUCCESSFULLY ===');
            Log::info(PHP_EOL . json_encode([
                'loan_id' => $loan->id,
                'transaction_id' => $loanTransaction->id,
                'mpesa_transaction_id' => $transaction->transaction_id,
                'amount' => $transaction->amount,
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance,
                'payment_method' => $paymentMethod,
                'user_id' => $user->id,
                'phone_number' => $user->phone_number
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('=== ERROR PROCESSING LOAN PAYMENT ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error(PHP_EOL . json_encode([
                'transaction_id' => $transaction->transaction_id,
                'loan_id' => $transaction->loan_id ?? null
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
        }
    }

    /**
     * Get transaction by checkout request ID
     */
    public function getTransactionByCheckoutId(string $checkoutRequestId): ?MpesaTransaction
    {
        return MpesaTransaction::where('checkout_request_id', $checkoutRequestId)->first();
    }

    /**
     * Get user transactions
     */
    public function getUserTransactions(string $userId, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return MpesaTransaction::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get loan transactions
     */
    public function getLoanTransactions(string $loanId): \Illuminate\Database\Eloquent\Collection
    {
        return MpesaTransaction::where('loan_id', $loanId)
            ->successful()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Query transaction status from M-Pesa
     */
    public function queryTransactionStatus(string $checkoutRequestId): array
    {
        try {
            // Refresh token before making API call
            $tokenRefresh = $this->refreshAccessToken();
            if (!$tokenRefresh['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to refresh access token: ' . $tokenRefresh['error']
                ];
            }

            $response = Mpesa::stkStatus([
                'CheckoutRequestID' => $checkoutRequestId,
            ]);

            Log::info('=== TRANSACTION STATUS QUERY COMPLETED ===');
            Log::info(PHP_EOL . json_encode([
                'environment' => $tokenRefresh['environment'],
                'checkout_request_id' => $checkoutRequestId
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return [
                'success' => true,
                'data' => $response,
                'environment' => $tokenRefresh['environment']
            ];

        } catch (\Exception $e) {
            Log::error('=== ERROR QUERYING TRANSACTION STATUS ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get loan payment information for paybill users
     */
    public function getLoanPaymentInfo(string $loanIdentifier): array
    {
        try {
            // Try to find loan by loan number first
            $loan = Loan::where('loan_number', $loanIdentifier)->first();
            
            if (!$loan) {
                // Try to find by employee ID
                $user = User::where('employee_id', $loanIdentifier)->first();
                if ($user) {
                    $loan = Loan::where('employee_id', $user->id)
                        ->where('loan_status', 'approved')
                        ->where('loan_balance', '>', 0)
                        ->orderBy('created_at', 'desc')
                        ->first();
                }
            }

            if (!$loan) {
                return [
                    'found' => false,
                    'message' => 'No active loan found for this identifier'
                ];
            }

            $user = User::find($loan->employee_id);

            return [
                'found' => true,
                'data' => [
                    'loan_id' => $loan->id,
                    'loan_number' => $loan->loan_number,
                    'loan_amount' => $loan->loan_amount,
                    'loan_balance' => $loan->loan_balance,
                    'monthly_installment' => $loan->monthly_installment,
                    'next_due_date' => $loan->next_due_date,
                    'borrower_name' => $user ? "{$user->first_name} {$user->last_name}" : 'N/A',
                    'employee_id' => $user->employee_id ?? 'N/A',
                    'phone_number' => $user->phone_number ?? 'N/A',
                    'paybill_number' => config('mpesa.shortcode'),
                    'account_reference_options' => [
                        'loan_number' => $loan->loan_number,
                        'employee_id' => $user->employee_id ?? null
                    ]
                ]
            ];

        } catch (\Exception $e) {
            Log::error('=== ERROR GETTING LOAN PAYMENT INFO ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
            return [
                'found' => false,
                'message' => 'Error retrieving loan information'
            ];
        }
    }

    /**
     * Force token refresh based on environment
     *
     * @return array
     */
    private function refreshAccessToken(): array
    {
        $environment = config('mpesa.environment', 'sandbox');

        $maxAttempts = 4;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                // The iankumu/mpesa package exposes a single token generation method
                $tokenResponse = Mpesa::generateAccessToken();

                // If the package returned an HTTP client Response object, try to extract useful info
                if (is_object($tokenResponse) && method_exists($tokenResponse, 'body')) {
                    $raw = $tokenResponse->body();
                } else {
                    $raw = is_string($tokenResponse) ? $tokenResponse : json_encode($tokenResponse);
                }

                // Detect Incapsula or HTML blocking
                if ($this->isIncapsulaHtml($raw)) {
                    $incident = $this->extractIncapsulaIncidentId($raw);
                    $msg = 'Incapsula block detected during token generation' . ($incident ? ' (incident: ' . $incident . ')' : '');
                    Log::warning('=== INCAPSULA BLOCK DETECTED ===');
                    Log::warning(PHP_EOL . json_encode([
                        'environment' => $environment,
                        'attempt' => $attempt,
                        'incident' => $incident,
                        'raw_preview' => substr($raw, 0, 600)
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    return [
                        'success' => false,
                        'error' => $msg,
                        'incapsula_incident' => $incident,
                    ];
                }

                Log::info('=== ACCESS TOKEN GENERATION RESPONSE ===');
                Log::info(PHP_EOL . json_encode([
                    'environment' => $environment,
                    'attempt' => $attempt,
                    'token_generated' => !empty($tokenResponse)
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return [
                    'success' => true,
                    'environment' => $environment,
                    'token_response' => $tokenResponse
                ];

            } catch (\Throwable $e) {
                // If we hit a server-side block or transient error, retry with backoff
                $rawMsg = $e->getMessage();
                Log::warning('=== TOKEN GENERATION ATTEMPT FAILED ===');
                Log::warning(PHP_EOL . json_encode([
                    'environment' => $environment,
                    'attempt' => $attempt,
                    'error' => $rawMsg
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                if ($attempt >= $maxAttempts) {
                    Log::error('=== TOKEN REFRESH FAILED AFTER ALL ATTEMPTS ===');
                    Log::error('Error: ' . $rawMsg);
                    Log::error('Environment: ' . $environment);
                    return [
                        'success' => false,
                        'error' => $rawMsg
                    ];
                }

                // exponential backoff (in microseconds)
                $sleep = (int) (500000 * (2 ** ($attempt - 1))); // 0.5s, 1s, 2s, ...
                usleep($sleep);
                continue;
            }
        }

        return [
            'success' => false,
            'error' => 'Token refresh failed'
        ];
    }

    /**
     * Detect whether the given response body is an Incapsula / HTML block page
     */
    private function isIncapsulaHtml($body): bool
    {
        if (empty($body) || !is_string($body)) {
            return false;
        }

        $lower = strtolower($body);
        if (strpos($lower, 'incapsula') !== false || strpos($lower, 'request unsuccessful') !== false || strpos($lower, 'iframe id="main-iframe"') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Try to extract Incapsula incident id from HTML
     */
    private function extractIncapsulaIncidentId(string $body): ?string
    {
        if (preg_match('/incident_id=([0-9\-]+)/i', $body, $m)) {
            return $m[1];
        }

        if (preg_match('/Incapsula incident ID:\s*([0-9\-]+)/i', $body, $m2)) {
            return $m2[1];
        }

        return null;
    }

    /**
     * Register C2B callback URLs with Safaricom (uses the iankumu/mpesa package)
     *
     * @param string|null $shortcode
     * @return array
     */
    public function registerC2BUrls(?string $shortcode = null): array
    {
        try {
            $shortcode = $shortcode ?? config('mpesa.shortcode');

            if (empty($shortcode)) {
                return [
                    'success' => false,
                    'message' => 'Shortcode not configured'
                ];
            }

            // Force token refresh before registration
            Log::info('Attempting to register C2B URLs with shortcode: ' . $shortcode);
            
            // Clear any cached token and force regeneration based on environment
            $tokenRefresh = $this->refreshAccessToken();
            if (!$tokenRefresh['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to refresh access token: ' . $tokenRefresh['error']
                ];
            }

            // Add a small delay to ensure token is ready
            usleep(500000); // 0.5 seconds

            // Clear config cache to ensure fresh values
            \Artisan::call('config:clear');

            Log::info('=== ABOUT TO REGISTER C2B URLS ===');
            Log::info(PHP_EOL . json_encode([
                'shortcode' => $shortcode,
                'environment' => $tokenRefresh['environment'],
                'consumer_key_length' => strlen(config('mpesa.mpesa_consumer_key', '')),
                'consumer_secret_length' => strlen(config('mpesa.mpesa_consumer_secret', '')),
                'validation_url' => config('mpesa.c2b_validation_url'),
                'confirmation_url' => config('mpesa.c2b_confirmation_url'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $maxAttempts = 3;
            $attempt = 0;
            $finalResponse = null;

            while ($attempt < $maxAttempts) {
                $attempt++;
                try {
                    $response = Mpesa::c2bregisterURLS($shortcode);
                    $finalResponse = $response;

                    $body = method_exists($response, 'body') ? $response->body() : (is_string($response) ? $response : json_encode($response));
                    $status = method_exists($response, 'status') ? $response->status() : null;

                    // Detect Incapsula HTML
                    if ($this->isIncapsulaHtml($body)) {
                        $incident = $this->extractIncapsulaIncidentId($body);
                        Log::warning('=== INCAPSULA BLOCK DETECTED DURING C2B REGISTRATION ===');
                        Log::warning(PHP_EOL . json_encode([
                            'attempt' => $attempt,
                            'incident' => $incident,
                            'status' => $status
                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                        return [
                            'success' => false,
                            'data' => $body,
                            'http_status' => $status,
                            'environment' => $tokenRefresh['environment'],
                            'error_message' => 'Blocked by Incapsula',
                            'incapsula_incident' => $incident
                        ];
                    }

                    Log::info('=== C2B URL REGISTRATION RESPONSE ===');
                    Log::info(PHP_EOL . json_encode([
                        'environment' => $tokenRefresh['environment'],
                        'attempt' => $attempt,
                        'status' => $status,
                        'body_preview' => substr($body, 0, 600)
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                    if (method_exists($response, 'successful') && $response->successful()) {
                        $responseData = $response->json();
                        return [
                            'success' => true,
                            'data' => $responseData,
                            'environment' => $tokenRefresh['environment']
                        ];
                    }

                    // For 401/403 retryable statuses, attempt again unless last try
                    if (in_array($status, [401, 403]) && $attempt < $maxAttempts) {
                        $sleep = (int) (500000 * (2 ** ($attempt - 1)));
                        usleep($sleep);
                        continue;
                    }

                    // Not successful and not retrying further
                    if (method_exists($response, 'json')) {
                        $responseBody = $response->json();
                    } else {
                        $responseBody = is_string($response) ? json_decode($response, true) : $response;
                    }

                    return [
                        'success' => false,
                        'data' => $responseBody,
                        'http_status' => $status,
                        'environment' => $tokenRefresh['environment'],
                        'error_message' => 'Registration failed with HTTP status: ' . $status
                    ];

                } catch (\Throwable $e) {
                    Log::warning('=== C2B REGISTRATION ATTEMPT FAILED ===');
                    Log::warning('Attempt: ' . $attempt . ' - Error: ' . $e->getMessage());
                    if ($attempt >= $maxAttempts) {
                        Log::error('=== C2B REGISTRATION FAILED AFTER ALL ATTEMPTS ===');
                        Log::error('Error: ' . $e->getMessage());
                        Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
                        return [
                            'success' => false,
                            'message' => 'C2B registration test failed',
                            'error' => $e->getMessage()
                        ];
                    }
                    $sleep = (int) (500000 * (2 ** ($attempt - 1)));
                    usleep($sleep);
                    continue;
                }
            }

            // If we exit loop without returning, return final response info
            $body = $finalResponse && method_exists($finalResponse, 'body') ? $finalResponse->body() : null;
            $status = $finalResponse && method_exists($finalResponse, 'status') ? $finalResponse->status() : null;

            return [
                'success' => false,
                'data' => $body,
                'http_status' => $status,
                'environment' => $tokenRefresh['environment'],
                'error_message' => 'Registration failed after retries'
            ];

        } catch (\Throwable $e) {
            Log::error('=== ERROR REGISTERING C2B URLS WITH SAFARICOM ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Exception Type: ' . get_class($e));
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => get_class($e)
            ];
        }
    }

    /**
     * Initiate B2C payment
     * 
     * Package method signature: b2c($phonenumber, $command_id, $amount, $remarks)
     * 
     * Package automatically sends:
     * - InitiatorName (from config: mpesa.initiator_name)
     * - SecurityCredential (from config: mpesa.initiator_password - auto-encrypted)
     * - PartyA (from config: mpesa.b2c_shortcode)
     * - QueueTimeOutURL (from config: mpesa.b2c_timeout_url)
     * - ResultURL (from config: mpesa.b2c_result_url)
     * - Occassion (empty string)
     */
    public function initiateB2C(array $data): array
    {
        try {
            // Validate B2C configuration
            $configCheck = $this->validateB2CConfig();
            if (!$configCheck['valid']) {
                return [
                    'success' => false,
                    'message' => 'B2C configuration error',
                    'error' => $configCheck['errors']
                ];
            }

            // Refresh token before making API call
            $tokenRefresh = $this->refreshAccessToken();
            if (!$tokenRefresh['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to refresh access token: ' . $tokenRefresh['error']
                ];
            }

            // Create transaction record BEFORE API call
            $transaction = MpesaTransaction::create([
                'phone_number' => $data['phone_number'],
                'amount' => $data['amount'],
                'transaction_description' => $data['remarks'] ?? 'B2C Payment',
                'transaction_type' => 'B2C',
                'status' => 'PENDING',
                'user_id' => $data['user_id'] ?? null,
            ]);

            // Call B2C API with correct parameter order
            // Method signature: b2c($phonenumber, $command_id, $amount, $remarks)
            $response = Mpesa::b2c(
                $data['phone_number'],
                $data['command_id'] ?? 'BusinessPayment',  // BusinessPayment, SalaryPayment, or PromotionPayment
                $data['amount'],
                $data['remarks'] ?? 'B2C Payment'
            );

            // Convert response to array for logging
            $responseData = [];
            if (method_exists($response, 'json')) {
                $responseData = $response->json();
            } elseif (method_exists($response, 'body')) {
                $responseData = json_decode($response->body(), true) ?? [];
            } elseif (is_array($response)) {
                $responseData = $response;
            }

            // Update transaction with response data
            if (isset($responseData['ConversationID']) || isset($responseData['OriginatorConversationID'])) {
                $transaction->update([
                    'merchant_request_id' => $responseData['OriginatorConversationID'] ?? null,
                    'checkout_request_id' => $responseData['ConversationID'] ?? null,
                ]);
            }

            Log::info('=== B2C PAYMENT INITIATED ===');
            Log::info(PHP_EOL . json_encode([
                'environment' => $tokenRefresh['environment'],
                'transaction_id' => $transaction->transaction_id,
                'phone_number' => $data['phone_number'],
                'amount' => $data['amount'],
                'command_id' => $data['command_id'] ?? 'BusinessPayment',
                'response' => $responseData,
                'http_status' => method_exists($response, 'status') ? $response->status() : 'unknown'
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return [
                'success' => true,
                'message' => 'B2C payment initiated successfully',
                'data' => [
                    'transaction_id' => $transaction->transaction_id,
                    'conversation_id' => $responseData['ConversationID'] ?? null,
                    'originator_conversation_id' => $responseData['OriginatorConversationID'] ?? null,
                    'response_code' => $responseData['ResponseCode'] ?? null,
                    'response_description' => $responseData['ResponseDescription'] ?? null,
                ],
                'environment' => $tokenRefresh['environment']
            ];

        } catch (\Exception $e) {
            Log::error('=== B2C PAYMENT ERROR ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return [
                'success' => false,
                'message' => 'B2C payment failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate B2C configuration
     */
    private function validateB2CConfig(): array
    {
        $errors = [];

        if (empty(config('mpesa.initiator_name'))) {
            $errors[] = 'MPESA_INITIATOR_NAME not configured';
        }

        if (empty(config('mpesa.initiator_password'))) {
            $errors[] = 'MPESA_INITIATOR_PASSWORD not configured';
        }

        if (empty(config('mpesa.b2c_shortcode'))) {
            $errors[] = 'MPESA_B2C_SHORTCODE not configured (Bulk Disbursement Account required)';
        }

        if (empty(config('mpesa.b2c_result_url'))) {
            $errors[] = 'MPESA_B2C_RESULT_URL not configured';
        }

        if (empty(config('mpesa.b2c_timeout_url'))) {
            $errors[] = 'MPESA_B2C_TIMEOUT_URL not configured';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
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
            $response = Mpesa::stkPush([
                'amount' => $data['amount'],
                'phone' => $data['phone_number'],
                'reference' => $data['account_reference'],
                'description' => $data['transaction_description'],
                'callback' => route('mpesa.stk-callback'),
            ]);

            // Update transaction with M-Pesa response
            if (isset($response['CheckoutRequestID'])) {
                $transaction->update([
                    'checkout_request_id' => $response['CheckoutRequestID'],
                    'merchant_request_id' => $response['MerchantRequestID'] ?? null,
                ]);

                Log::info('STK Push initiated successfully', [
                    'environment' => $tokenRefresh['environment'],
                    'transaction_id' => $transaction->transaction_id,
                    'checkout_request_id' => $response['CheckoutRequestID']
                ]);

                return [
                    'success' => true,
                    'message' => 'STK Push initiated successfully',
                    'data' => [
                        'transaction_id' => $transaction->transaction_id,
                        'checkout_request_id' => $response['CheckoutRequestID'],
                        'merchant_request_id' => $response['MerchantRequestID'] ?? null,
                        'environment' => $tokenRefresh['environment'],
                    ]
                ];
            } else {
                // STK Push failed
                $transaction->update([
                    'status' => 'FAILED',
                    'result_desc' => 'Failed to initiate STK Push'
                ]);

                Log::error('STK Push initiation failed', $response);

                return [
                    'success' => false,
                    'message' => 'Failed to initiate STK Push',
                    'error' => $response
                ];
            }

        } catch (\Exception $e) {
            Log::error('STK Push service error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'STK Push service error',
                'error' => $e->getMessage()
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

                Log::info('STK Push payment processed successfully', [
                    'transaction_id' => $transaction->transaction_id,
                    'mpesa_receipt' => $mpesaReceiptNumber,
                    'amount' => $amount
                ]);

            } else {
                // Payment failed
                $transaction->update([
                    'status' => 'FAILED',
                    'result_code' => $resultCode,
                    'result_desc' => $stkCallback['ResultDesc'],
                    'callback_data' => $callbackData,
                ]);

                Log::warning('STK Push payment failed', [
                    'transaction_id' => $transaction->transaction_id,
                    'result_code' => $resultCode,
                    'result_desc' => $stkCallback['ResultDesc']
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error processing STK callback: ' . $e->getMessage());
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
            Log::error('Paybill validation error: ' . $e->getMessage());
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

            Log::info('Paybill payment processed successfully', [
                'transaction_id'    => $transaction->transaction_id,
                'loan_number'       => $loan->loan_number,
                'amount'            => $amount,
                'phone'             => $phoneNumber
            ]);

            return [
                'success' => true,
                'message' => 'Payment processed successfully',
                'transaction_id' => $transaction->transaction_id,
                'loan_id' => $loan->id
            ];

        } catch (\Exception $e) {
            Log::error('Error processing paybill payment: ' . $e->getMessage());
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
                Log::info('Loan fully paid - marking as completed', [
                    'loan_id' => $loan->id,
                    'loan_number' => $loan->loan_number,
                    'final_payment_amount' => $transaction->amount
                ]);
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

            Log::info('Loan payment processed successfully', [
                'loan_id' => $loan->id,
                'transaction_id' => $loanTransaction->id,
                'mpesa_transaction_id' => $transaction->transaction_id,
                'amount' => $transaction->amount,
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance,
                'payment_method' => $paymentMethod,
                'user_id' => $user->id,
                'phone_number' => $user->phone_number
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing loan payment: ' . $e->getMessage(), [
                'transaction_id' => $transaction->transaction_id,
                'loan_id' => $transaction->loan_id ?? null,
                'error_trace' => $e->getTraceAsString()
            ]);
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

            Log::info('Transaction status query completed', [
                'environment' => $tokenRefresh['environment'],
                'checkout_request_id' => $checkoutRequestId
            ]);

            return [
                'success' => true,
                'data' => $response,
                'environment' => $tokenRefresh['environment']
            ];

        } catch (\Exception $e) {
            Log::error('Error querying transaction status: ' . $e->getMessage());

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
            Log::error('Error getting loan payment info: ' . $e->getMessage());
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
        try {
            $environment = config('mpesa.environment', 'sandbox');
            
            if ($environment === 'live' || $environment === 'production') {
                $tokenResponse = Mpesa::generateLiveToken();
                Log::info('Live token generation response: ', ['environment' => 'live']);
            } else {
                $tokenResponse = Mpesa::generateSandBoxToken();
                Log::info('Sandbox token generation response: ', ['environment' => 'sandbox']);
            }
            
            return [
                'success' => true,
                'environment' => $environment,
                'token_response' => $tokenResponse
            ];
        } catch (\Exception $e) {
            Log::error('Token refresh failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
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

            $response = Mpesa::c2bregisterURLS($shortcode);
            
            Log::info('C2B URL registration response: ', [
                'environment' => $tokenRefresh['environment'],
                'response_body' => $response->body(),
                'response_status' => $response->status(),
                'response_headers' => $response->headers()
            ]);

            // Check if response is successful
            if (method_exists($response, 'successful') && $response->successful()) {
                $responseData = $response->json();
                return [
                    'success' => true,
                    'data' => $responseData,
                    'environment' => $tokenRefresh['environment']
                ];
            }

            // Handle different types of responses
            $responseBody = $response->json() ?? json_decode($response->body(), true);
            
            return [
                'success' => false,
                'data' => $responseBody,
                'http_status' => $response->status(),
                'environment' => $tokenRefresh['environment'],
                'error_message' => 'Registration failed with HTTP status: ' . $response->status()
            ];

        } catch (\Throwable $e) {
            Log::error('Error registering C2B URLs with Safaricom: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => get_class($e)
            ];
        }
    }

    /**
     * Initiate B2C payment
     */
    public function initiateB2C(array $data): array
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

            $response = Mpesa::b2c([
                'Amount' => $data['amount'],
                'PhoneNumber' => $data['phone_number'],
                'CommandID' => $data['command_id'] ?? 'BusinessPayment',
                'Remarks' => $data['remarks'] ?? 'B2C Payment',
                'Occasion' => $data['occasion'] ?? 'Payment'
            ]);

            // Create transaction record
            $transaction = MpesaTransaction::create([
                'phone_number' => $data['phone_number'],
                'amount' => $data['amount'],
                'transaction_description' => $data['remarks'] ?? 'B2C Payment',
                'transaction_type' => 'B2C',
                'status' => 'PENDING',
                'user_id' => $data['user_id'] ?? null,
            ]);

            Log::info('B2C payment initiated', [
                'environment' => $tokenRefresh['environment'],
                'transaction_id' => $transaction->transaction_id,
                'response' => $response
            ]);

            return [
                'success' => true,
                'message' => 'B2C payment initiated successfully',
                'data' => $response,
                'environment' => $tokenRefresh['environment']
            ];

        } catch (\Exception $e) {
            Log::error('B2C payment error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'B2C payment failed',
                'error' => $e->getMessage()
            ];
        }
    }
}
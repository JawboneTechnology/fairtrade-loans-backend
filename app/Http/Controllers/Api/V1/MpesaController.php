<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Events\StkPushRequested;
use App\Services\MpesaService;
use App\Services\SMSService;
use App\Notifications\DisbursementInitiatedNotification;
use App\Models\User;
use App\Models\Loan as LoanModel;

class MpesaController extends Controller
{
    protected MpesaService $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    /**
     * Handle STK Push initiation for loan payment (Method 1 - Via App)
     */
    public function initiateLoanPayment(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => 'required|string|regex:/^254[0-9]{9}$/',
            'amount'       => 'required|numeric|min:1',
            'loan_id'      => 'required|uuid|exists:loans,id'
        ]);

        try {
            // Get loan details
            $loan = \App\Models\Loan::findOrFail($request->loan_id);
            
            // Verify the loan belongs to the authenticated user
            if ($loan->employee_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to loan'
                ], 403);
            }

            // Validate payment amount against loan balance
            if ($request->amount > $loan->loan_balance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount exceeds loan balance'
                ], 400);
            }

            $paymentData = [
                'phone_number' => $request->phone_number,
                'amount' => $request->amount,
                'account_reference' => $loan->loan_number,
                'transaction_description' => "Loan payment for {$loan->loan_number}",
                'user_id' => auth()->id(),
                'loan_id' => $loan->id,
                'payment_method' => 'APP'
            ];

            $result = $this->mpesaService->initiateStkPush($paymentData);

            if ($result['success']) {
                return response()->json($result);
            } else {
                return response()->json($result, 500);
            }
        } catch (\Exception $e) {
            Log::error('=== LOAN PAYMENT INITIATION ERROR ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate loan payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle general STK Push initiation (Method 1 - General payments)
     */
    public function initiateStkPush(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => 'required|string|regex:/^254[0-9]{9}$/',
            'amount' => 'required|numeric|min:1',
            'account_reference' => 'required|string|max:20',
            'transaction_description' => 'required|string|max:100'
        ]);

        $paymentData = [
            'phone_number' => $request->phone_number,
            'amount' => $request->amount,
            'account_reference' => $request->account_reference,
            'transaction_description' => $request->transaction_description,
            'user_id' => auth()->id(),
            'loan_id' => $request->loan_id ?? null,
            'payment_method' => 'APP'
        ];

        $result = $this->mpesaService->initiateStkPush($paymentData);

        if ($result['success']) {
            return response()->json($result);
        } else {
            return response()->json($result, 500);
        }
    }

    /**
     * Handle STK Push callback
     */
    public function stkCallback(Request $request): JsonResponse
    {
        $callbackData = $request->all();
        
        Log::info('=== STK PUSH CALLBACK RECEIVED ===');
        Log::info(PHP_EOL . json_encode($callbackData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        try {
            $this->mpesaService->processStkCallback($callbackData);
        } catch (\Exception $e) {
            Log::error('Error processing STK callback: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
        }

        // Always return success to M-Pesa
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    /**
     * Handle C2B Validation (Method 2 - Paybill payments)
     */
    public function c2bValidation(Request $request): JsonResponse
    {
        Log::info('=== C2B VALIDATION RECEIVED ===');
        Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        try {
            $billRefNumber = $request->input('BillRefNumber');
            $amount = $request->input('TransAmount');
            
            // Validate if the bill reference number corresponds to a valid loan
            $validation = $this->mpesaService->validatePaybillPayment($billRefNumber, $amount);
            
            if ($validation['valid']) {
                Log::info('=== C2B VALIDATION SUCCESSFUL ===');
                Log::info(PHP_EOL . json_encode([
                    'bill_ref' => $billRefNumber,
                    'amount' => $amount,
                    'loan_id' => $validation['loan_id'] ?? null
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                
                return response()->json([
                    "ResultCode" => "0",
                    "ResultDesc" => "Accepted",
                ]);
            } else {
                Log::warning('=== C2B VALIDATION FAILED ===');
                Log::warning(PHP_EOL . json_encode([
                    'bill_ref' => $billRefNumber,
                    'amount' => $amount,
                    'reason' => $validation['reason']
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                
                return response()->json([
                    "ResultCode" => "C2B00014", // Invalid KYC Details
                    "ResultDesc" => "Rejected",
                ]);
            }
        } catch (\Exception $e) {
            Log::error('=== C2B VALIDATION ERROR ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
            
            return response()->json([
                'ResultCode' => "C2B00016", // Other errors
                'ResultDesc' => 'Rejected'
            ]);
        }
    }

    /**
     * Handle C2B Confirmation (Method 2 - Paybill payments)
     */
    public function c2bConfirmation(Request $request): JsonResponse
    {
        $callbackData = $request->all();
        
        Log::info('=== C2B CONFIRMATION RECEIVED ===');
        Log::info(PHP_EOL . json_encode($callbackData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        try {
            // Process paybill payment with loan identification
            $result = $this->mpesaService->processPaybillPayment($callbackData);
            
            if ($result['success']) {
                Log::info('=== C2B PAYMENT PROCESSED SUCCESSFULLY ===');
                Log::info(PHP_EOL . json_encode([
                    'transaction_id' => $result['transaction_id'] ?? null,
                    'loan_id' => $result['loan_id'] ?? null
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                Log::error('=== C2B PAYMENT PROCESSING FAILED ===');
                Log::error('Reason: ' . ($result['message'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            Log::error('=== ERROR PROCESSING C2B CONFIRMATION ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
        }

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Success'
        ]);
    }

    /**
     * Handle B2C Result
     */
    public function b2cResult(Request $request): JsonResponse
    {
        Log::info('=== B2C RESULT RECEIVED ===');
        Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Process B2C result
        try {
            $this->processB2CResult($request->all());
        } catch (\Exception $e) {
            Log::error('=== ERROR PROCESSING B2C RESULT ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
        }

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Success'
        ]);
    }

    /**
     * Handle B2C Timeout
     */
    public function b2cTimeout(Request $request): JsonResponse
    {
        Log::info('=== B2C TIMEOUT RECEIVED ===');
        Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Success'
        ]);
    }

    /**
     * Handle Transaction Status Result
     */
    public function statusResult(Request $request): JsonResponse
    {
        Log::info('=== TRANSACTION STATUS RESULT RECEIVED ===');
        Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Success'
        ]);
    }

    /**
     * Handle Transaction Status Timeout
     */
    public function statusTimeout(Request $request): JsonResponse
    {
        Log::info('=== TRANSACTION STATUS TIMEOUT RECEIVED ===');
        Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Success'
        ]);
    }

    /**
     * Handle Balance Result
     */
    public function balanceResult(Request $request): JsonResponse
    {
        Log::info('=== BALANCE RESULT RECEIVED ===');
        Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Success'
        ]);
    }

    /**
     * Handle Balance Timeout
     */
    public function balanceTimeout(Request $request): JsonResponse
    {
        Log::info('=== BALANCE TIMEOUT RECEIVED ===');
        Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Success'
        ]);
    }

    /**
     * Handle Reversal Result
     */
    public function reversalResult(Request $request): JsonResponse
    {
        Log::info('=== REVERSAL RESULT RECEIVED ===');
        Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Success'
        ]);
    }

    /**
     * Handle Reversal Timeout
     */
    public function reversalTimeout(Request $request): JsonResponse
    {
        Log::info('=== REVERSAL TIMEOUT RECEIVED ===');
        Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Success'
        ]);
    }

    /**
     * Handle B2B Result
     */
    public function b2bResult(Request $request): JsonResponse
    {
        Log::info('=== B2B RESULT RECEIVED ===');
        Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Success'
        ]);
    }

    /**
     * Handle B2B Timeout
     */
    public function b2bTimeout(Request $request): JsonResponse
    {
        Log::info('=== B2B TIMEOUT RECEIVED ===');
        Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Success'
        ]);
    }

    /**
     * Get user M-Pesa transactions
     */
    public function getUserTransactions(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            $limit = $request->input('limit', 20);
            
            $transactions = $this->mpesaService->getUserTransactions($userId, $limit);
            
            return response()->json([
                'success' => true,
                'message' => 'Transactions retrieved successfully',
                'data' => $transactions
            ]);
            
        } catch (\Exception $e) {
            Log::error('=== ERROR FETCHING USER TRANSACTIONS ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Query transaction status
     */
    public function queryTransactionStatus(Request $request): JsonResponse
    {
        $request->validate([
            'checkout_request_id' => 'required|string'
        ]);

        try {
            $result = $this->mpesaService->queryTransactionStatus($request->checkout_request_id);
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error('=== ERROR QUERYING TRANSACTION STATUS ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to query transaction status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get loan payment information for paybill users
     */
    public function getLoanPaymentInfo(Request $request): JsonResponse
    {
        $request->validate([
            'loan_identifier' => 'required|string' // Can be loan number or employee ID
        ]);

        try {
            $loanInfo = $this->mpesaService->getLoanPaymentInfo($request->loan_identifier);
            
            if ($loanInfo['found']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Loan information retrieved successfully',
                    'data' => $loanInfo['data']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Loan not found'
                ], 404);
            }
            
        } catch (\Exception $e) {
            Log::error('=== ERROR GETTING LOAN PAYMENT INFO ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve loan information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test payment success notification (for testing purposes)
     */
    public function testPaymentNotification(Request $request): JsonResponse
    {
        $request->validate([
            'transaction_id' => 'required|string',
            'test_mode' => 'boolean'
        ]);

        try {
            $transaction = \App\Models\MpesaTransaction::where('transaction_id', $request->transaction_id)->first();
            
            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found'
                ], 404);
            }

            if (!$transaction->loan_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No loan associated with this transaction'
                ], 400);
            }

            $loan = \App\Models\Loan::find($transaction->loan_id);
            $user = \App\Models\User::find($loan->employee_id);

            if ($request->input('test_mode', false)) {
                // Test mode - just return what would be sent
                return response()->json([
                    'success' => true,
                    'message' => 'Test mode - SMS content generated',
                    'data' => [
                        'recipient' => $user->phone_number,
                        'sms_content' => $this->buildTestSMSMessage($transaction, $loan, $user),
                        'payment_amount' => $transaction->amount,
                        'loan_balance' => $loan->loan_balance
                    ]
                ]);
            }

            // Fire the actual event
            event(new \App\Events\PaymentSuccessful(
                $transaction,
                $loan,
                $user,
                $loan->loan_balance,
                $transaction->payment_method === 'PAYBILL' ? 'M-Pesa Paybill' : 'M-Pesa (App)'
            ));

            return response()->json([
                'success' => true,
                'message' => 'Payment notification event fired successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('=== ERROR TESTING PAYMENT NOTIFICATION ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to test payment notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Initiate B2C disbursement to user identified by loan number or user id/employee id
     */
    public function initiateB2CPayment(Request $request, $identifier, MpesaService $mpesaService, SMSService $smsService): JsonResponse
    {
        $request->validate([
            'amount' => 'nullable|numeric|min:1',
            'command_id' => 'nullable|string',
            'remarks' => 'nullable|string'
        ]);

        try {
            // Try to find loan by loan number first
            $loan = LoanModel::where('loan_number', $identifier)->first();

            if ($loan) {
                $user = User::find($loan->employee_id);
                $amount = $request->input('amount', $loan->loan_amount);
                $remarks = $request->input('remarks', "Loan disbursement for {$loan->loan_number}");
            } else {
                // Try to find user by id or employee_id
                $user = User::where('id', $identifier)->orWhere('employee_id', $identifier)->first();
                $amount = $request->input('amount');
                $remarks = $request->input('remarks', 'Loan disbursement');
            }

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User or loan not found'], 404);
            }

            if (!$amount) {
                return response()->json(['success' => false, 'message' => 'Amount is required when no loan is specified'], 400);
            }

            // Format phone number to 254... (no +)
            $phone = preg_replace('/[^0-9+]/', '', $user->phone_number);
            if (str_starts_with($phone, '+')) {
                $phone = ltrim($phone, '+');
            }
            if (str_starts_with($phone, '0')) {
                $phone = '254' . substr($phone, 1);
            }
            if (!str_starts_with($phone, '254')) {
                $phone = '254' . $phone;
            }

            $payload = [
                'amount' => $amount,
                'phone_number' => $phone,
                'command_id' => $request->input('command_id', 'BusinessPayment'),
                'remarks' => $remarks,
                'occasion' => $request->input('occasion', 'Loan Disbursement'),
                'user_id' => $user->id,
                'loan_id' => $loan->id ?? null,
            ];

            $result = $mpesaService->initiateB2C($payload);

            // Send immediate notification that disbursement was initiated
            $applicantName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->email ?? 'Customer';

            try {
                $user->notify(new DisbursementInitiatedNotification($applicantName, (float)$amount, $loan ?? null, $result['data'] ?? null));
            } catch (\Exception $e) {
                // Log but do not fail the request
                Log::error('=== FAILED TO QUEUE DISBURSEMENT EMAIL ===');
                Log::error('Error: ' . $e->getMessage());
            }

            // Send SMS informing the user that the disbursement has been initiated
            try {
                $smsMessage = "Dear {$applicantName}, a disbursement of KES " . number_format($amount, 2) . " has been initiated to your mobile number. We will notify you when the payment is completed.";
                $smsService->sendSMS($phone, $smsMessage, $user->id ?? null);
            } catch (\Exception $e) {
                Log::error('=== FAILED TO SEND DISBURSEMENT SMS ===');
                Log::error('Error: ' . $e->getMessage());
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('=== ERROR INITIATING B2C DISBURSEMENT ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate disbursement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build test SMS message
     */
    private function buildTestSMSMessage(\App\Models\MpesaTransaction $transaction, \App\Models\Loan $loan, \App\Models\User $user): string
    {
        $userName = $user->first_name . ' ' . $user->last_name;
        $amount = number_format($transaction->amount, 2);
        $newBalance = number_format($loan->loan_balance, 2);
        $loanNumber = $loan->loan_number;
        $receiptNumber = $transaction->mpesa_receipt_number ?? $transaction->transaction_id;
        $paymentDate = $transaction->transaction_date ? 
            $transaction->transaction_date->format('d/m/Y H:i') : 
            now()->format('d/m/Y H:i');

        return "Dear {$userName}, your payment of KES {$amount} for loan {$loanNumber} has been received successfully. "
             . "Receipt: {$receiptNumber}. New loan balance: KES {$newBalance}. "
             . "Payment processed on {$paymentDate} via M-Pesa. Thank you for choosing our services!";
    }
}
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Events\StkPushRequested;
use App\Services\MpesaService;

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
            Log::error('Loan payment initiation error: ' . $e->getMessage());
            
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
        
        Log::info('STK Push Callback received:', $callbackData);

        try {
            $this->mpesaService->processStkCallback($callbackData);
        } catch (\Exception $e) {
            Log::error('Error processing STK callback: ' . $e->getMessage());
        }

        // Always return success to M-Pesa
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    /**
     * Handle C2B Validation (Method 2 - Paybill payments)
     */
    public function c2bValidation(Request $request): JsonResponse
    {
        Log::info('C2B Validation received:', $request->all());

        try {
            $billRefNumber = $request->input('BillRefNumber');
            $amount = $request->input('TransAmount');
            
            // Validate if the bill reference number corresponds to a valid loan
            $validation = $this->mpesaService->validatePaybillPayment($billRefNumber, $amount);
            
            if ($validation['valid']) {
                Log::info('C2B Validation successful', [
                    'bill_ref' => $billRefNumber,
                    'amount' => $amount,
                    'loan_id' => $validation['loan_id'] ?? null
                ]);
                
                return response()->json([
                    'ResultCode' => 0,
                    'ResultDesc' => 'Success'
                ]);
            } else {
                Log::warning('C2B Validation failed', [
                    'bill_ref' => $billRefNumber,
                    'amount' => $amount,
                    'reason' => $validation['reason']
                ]);
                
                return response()->json([
                    'ResultCode' => 1,
                    'ResultDesc' => $validation['reason']
                ]);
            }
        } catch (\Exception $e) {
            Log::error('C2B Validation error: ' . $e->getMessage());
            
            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Validation failed'
            ]);
        }
    }

    /**
     * Handle C2B Confirmation (Method 2 - Paybill payments)
     */
    public function c2bConfirmation(Request $request): JsonResponse
    {
        $callbackData = $request->all();
        
        Log::info('C2B Confirmation received:', $callbackData);

        try {
            // Process paybill payment with loan identification
            $result = $this->mpesaService->processPaybillPayment($callbackData);
            
            if ($result['success']) {
                Log::info('C2B Payment processed successfully', [
                    'transaction_id' => $result['transaction_id'] ?? null,
                    'loan_id' => $result['loan_id'] ?? null
                ]);
            } else {
                Log::error('C2B Payment processing failed', [
                    'reason' => $result['message'] ?? 'Unknown error'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error processing C2B confirmation: ' . $e->getMessage());
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
        Log::info('B2C Result received:', $request->all());

        // Process B2C result
        try {
            $this->processB2CResult($request->all());
        } catch (\Exception $e) {
            Log::error('Error processing B2C result: ' . $e->getMessage());
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
        Log::info('B2C Timeout received:', $request->all());

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
        Log::info('Transaction Status Result received:', $request->all());

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
        Log::info('Transaction Status Timeout received:', $request->all());

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
        Log::info('Balance Result received:', $request->all());

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
        Log::info('Balance Timeout received:', $request->all());

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
        Log::info('Reversal Result received:', $request->all());

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
        Log::info('Reversal Timeout received:', $request->all());

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
        Log::info('B2B Result received:', $request->all());

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
        Log::info('B2B Timeout received:', $request->all());

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
            Log::error('Error fetching user transactions: ' . $e->getMessage());
            
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
            Log::error('Error querying transaction status: ' . $e->getMessage());
            
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
            Log::error('Error getting loan payment info: ' . $e->getMessage());
            
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
            Log::error('Error testing payment notification: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to test payment notification',
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
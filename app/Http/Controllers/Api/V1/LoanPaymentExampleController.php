<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LoanPaymentExampleController extends Controller
{
    protected MpesaService $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    /**
     * Example: Method 1 - App-based loan payment
     * User selects loan from app interface and pays
     */
    public function payLoanViaApp(Request $request): JsonResponse
    {
        $request->validate([
            'loan_id' => 'required|uuid|exists:loans,id',
            'amount' => 'required|numeric|min:1',
            'phone_number' => 'required|string|regex:/^254[0-9]{9}$/'
        ]);

        $loan = Loan::findOrFail($request->loan_id);
        
        // Verify loan belongs to authenticated user
        if ($loan->employee_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to loan'
            ], 403);
        }

        // Initiate payment
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

        return response()->json($result);
    }

    /**
     * Example: Get loan info for paybill payment
     * Helps customers know their loan details for paybill payments
     */
    public function getLoanInfoForPaybill(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string' // Can be loan number or employee ID
        ]);

        $loanInfo = $this->mpesaService->getLoanPaymentInfo($request->identifier);

        if ($loanInfo['found']) {
            return response()->json([
                'success' => true,
                'message' => 'Loan information retrieved',
                'data' => $loanInfo['data'],
                'paybill_instructions' => [
                    'step_1' => 'Go to M-Pesa menu',
                    'step_2' => 'Select Lipa na M-Pesa > Pay Bill',
                    'step_3' => 'Enter Business Number: ' . config('mpesa.shortcode'),
                    'step_4' => 'Enter Account Number: ' . $loanInfo['data']['loan_number'] . ' or ' . $loanInfo['data']['account_reference_options']['employee_id'],
                    'step_5' => 'Enter Amount (Max: KES ' . number_format($loanInfo['data']['loan_balance'], 2) . ')',
                    'step_6' => 'Enter your M-Pesa PIN to complete'
                ]
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $loanInfo['message']
            ], 404);
        }
    }

    /**
     * Example: Get user's payment history with method breakdown
     */
    public function getPaymentHistory(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 20);
        $transactions = $this->mpesaService->getUserTransactions(auth()->id(), $limit);

        // Group by payment method for analytics
        $appPayments = $transactions->where('payment_method', 'APP');
        $paybillPayments = $transactions->where('payment_method', 'PAYBILL');

        return response()->json([
            'success' => true,
            'data' => [
                'all_transactions' => $transactions,
                'summary' => [
                    'total_transactions' => $transactions->count(),
                    'app_payments' => $appPayments->count(),
                    'paybill_payments' => $paybillPayments->count(),
                    'total_amount' => $transactions->sum('amount'),
                    'successful_payments' => $transactions->where('status', 'SUCCESS')->count()
                ]
            ]
        ]);
    }
}
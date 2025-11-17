<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Iankumu\Mpesa\Facades\Mpesa;
use App\Services\MpesaService;
use App\Models\MpesaTransaction;

/**
 * M-Pesa Test Controller
 * 
 * This controller provides test endpoints for M-Pesa integrations
 * Use these endpoints with Postman to test STK Push, B2C, and C2B transactions
 */
class MpesaTestController extends Controller
{
    protected MpesaService $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    /**
     * Test M-Pesa access token generation
     * 
     * Endpoint: GET /api/v1/mpesa/test-token
     */
    public function testAccessToken()
    {
        try {
            Log::info('=== M-PESA ACCESS TOKEN TEST STARTED ===');
            
            // Clear any cached tokens first
            $this->clearCachedToken();
            
            // Try to generate a new token by making a test request
            $response = $this->generateAccessToken();
            
            Log::info('=== M-PESA ACCESS TOKEN TEST COMPLETED ===', $response);
            
            return response()->json([
                'success' => true,
                'message' => 'Access token test completed',
                'token_info' => $response,
                'timestamp' => now()->toDateTimeString()
            ]);
            
        } catch (\Exception $e) {
            Log::error('=== M-PESA ACCESS TOKEN TEST FAILED ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Access token test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test STK Push (Lipa Na M-Pesa Online)
     * 
     * Endpoint: POST /api/v1/mpesa/test-stk-push
     * 
     * Postman Request Body:
     * {
     *   "phone_number": "254712345678",
     *   "amount": 100,
     *   "account_reference": "TEST-001",
     *   "transaction_description": "Test STK Push Payment"
     * }
     */
    public function testStkPush(Request $request)
    {
        try {
            Log::info('=== STK PUSH TEST STARTED ===');
            Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Validate request
            $validated = $request->validate([
                'phone_number' => 'required|string|regex:/^254[0-9]{9}$/',
                'amount' => 'required|numeric|min:1',
                'account_reference' => 'nullable|string|max:20',
                'transaction_description' => 'nullable|string|max:100'
            ]);

            // Set defaults
            $data = [
                'phone_number' => $validated['phone_number'],
                'amount' => $validated['amount'],
                'account_reference' => $validated['account_reference'] ?? 'TEST-' . time(),
                'transaction_description' => $validated['transaction_description'] ?? 'Test Payment',
                'user_id' => auth()->id() ?? null,
                'payment_method' => 'APP' // Valid values: 'APP' or 'PAYBILL'
            ];

            Log::info('=== STK PUSH REQUEST DATA ===');
            Log::info(PHP_EOL . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Initiate STK Push
            $result = $this->mpesaService->initiateStkPush($data);

            Log::info('=== STK PUSH TEST COMPLETED ===');
            Log::info(PHP_EOL . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'STK Push initiated successfully',
                    'data' => $result['data'] ?? $result,
                    'timestamp' => now()->toDateTimeString(),
                    'note' => 'Check your phone for M-Pesa prompt. Callback will be logged automatically.'
                ]);
            } else {
                return response()->json($result, 500);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('=== STK PUSH VALIDATION FAILED ===');
            Log::error(PHP_EOL . json_encode(['errors' => $e->errors()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('=== STK PUSH TEST FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'STK Push test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test B2C Payment (Business to Customer)
     * 
     * Endpoint: POST /api/v1/mpesa/test-b2c
     * 
     * Postman Request Body:
     * {
     *   "phone_number": "254712345678",
     *   "amount": 100,
     *   "command_id": "BusinessPayment",
     *   "remarks": "Test B2C Payment",
     *   "occasion": "Testing"
     * }
     * 
     * Available CommandIDs:
     * - BusinessPayment: For normal business to customer payment
     * - SalaryPayment: For salary disbursement
     * - PromotionPayment: For promotional payment
     */
    public function testB2C(Request $request)
    {
        try {
            Log::info('=== B2C PAYMENT TEST STARTED ===');
            Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Validate request
            $validated = $request->validate([
                'phone_number' => 'required|string|regex:/^254[0-9]{9}$/',
                'amount' => 'required|numeric|min:10',
                'command_id' => 'nullable|string|in:BusinessPayment,SalaryPayment,PromotionPayment',
                'remarks' => 'nullable|string|max:100',
                'occasion' => 'nullable|string|max:100'
            ]);

            $data = [
                'phone_number' => $validated['phone_number'],
                'amount' => $validated['amount'],
                'command_id' => $validated['command_id'] ?? 'BusinessPayment',
                'remarks' => $validated['remarks'] ?? 'Test B2C Payment',
                'occasion' => $validated['occasion'] ?? 'Testing',
                'user_id' => auth()->id() ?? null
            ];

            Log::info('=== B2C REQUEST DATA ===');
            Log::info(PHP_EOL . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Initiate B2C payment
            $result = $this->mpesaService->initiateB2C($data);

            Log::info('=== B2C PAYMENT TEST COMPLETED ===');
            Log::info(PHP_EOL . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'B2C payment initiated successfully',
                    'data' => $result['data'] ?? $result,
                    'timestamp' => now()->toDateTimeString(),
                    'note' => 'Customer will receive funds shortly. Result callback will be logged.'
                ]);
            } else {
                return response()->json($result, 500);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('=== B2C VALIDATION FAILED ===');
            Log::error(PHP_EOL . json_encode(['errors' => $e->errors()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('=== B2C PAYMENT TEST FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'B2C payment test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test C2B Registration
     * 
     * Endpoint: GET /api/v1/mpesa/test-c2b
     */
    public function testC2BRegistration()
    {
        try {
            Log::info('=== C2B REGISTRATION TEST STARTED ===');
            
            // Clear any cached tokens
            $this->clearCachedToken();
            
            // Test token generation first
            $tokenTest = $this->generateAccessToken();
            
            if (!$tokenTest['token_generated']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token generation failed',
                    'token_test' => $tokenTest
                ], 400);
            }
            
            // Get configuration
            $shortcode = config('mpesa.shortcode');
            $validationUrl = config('mpesa.c2b_validation_url');
            $confirmationUrl = config('mpesa.c2b_confirmation_url');
            
            Log::info('=== C2B REGISTRATION CONFIG ===');
            Log::info(PHP_EOL . json_encode([
                'shortcode' => $shortcode,
                'validation_url' => $validationUrl,
                'confirmation_url' => $confirmationUrl
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            
            // Register C2B URLs
            $result = $this->mpesaService->registerC2BUrls($shortcode);
            
            Log::info('=== C2B REGISTRATION TEST COMPLETED ===');
            Log::info(PHP_EOL . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            
            return response()->json([
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? 'C2B registration completed',
                'token_test' => $tokenTest,
                'registration_result' => $result,
                'timestamp' => now()->toDateTimeString(),
                'note' => 'After successful registration, customers can send payments to the paybill number.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('=== C2B REGISTRATION TEST FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'C2B registration test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test C2B Validation Callback
     * 
     * Endpoint: POST /api/v1/mpesa/test-c2b-validation
     * 
     * This simulates what Safaricom sends to your validation URL
     * 
     * Postman Request Body:
     * {
     *   "TransactionType": "Pay Bill",
     *   "TransID": "OEI2AK4Q16",
     *   "TransTime": "20230615143000",
     *   "TransAmount": "100.00",
     *   "BusinessShortCode": "174379",
     *   "BillRefNumber": "LOAN-001",
     *   "InvoiceNumber": "",
     *   "OrgAccountBalance": "10000.00",
     *   "ThirdPartyTransID": "",
     *   "MSISDN": "254712345678",
     *   "FirstName": "John",
     *   "MiddleName": "Doe",
     *   "LastName": "Smith"
     * }
     */
    public function testC2BValidation(Request $request)
    {
        try {
            Log::info('=== C2B VALIDATION TEST STARTED ===');
            Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $billRefNumber = $request->input('BillRefNumber');
            $amount = $request->input('TransAmount');

            // Validate the payment
            $validation = $this->mpesaService->validatePaybillPayment($billRefNumber, $amount);

            Log::info('=== C2B VALIDATION RESULT ===');
            Log::info(PHP_EOL . json_encode($validation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            Log::info('=== C2B VALIDATION TEST COMPLETED ===');

            if ($validation['valid']) {
                return response()->json([
                    'ResultCode' => 0,
                    'ResultDesc' => 'Accepted',
                    'validation_details' => $validation
                ]);
            } else {
                return response()->json([
                    'ResultCode' => 1,
                    'ResultDesc' => $validation['reason'] ?? 'Rejected',
                    'validation_details' => $validation
                ]);
            }

        } catch (\Exception $e) {
            Log::error('=== C2B VALIDATION TEST FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'System error during validation'
            ]);
        }
    }

    /**
     * Test C2B Confirmation Callback
     * 
     * Endpoint: POST /api/v1/mpesa/test-c2b-confirmation
     * 
     * This simulates what Safaricom sends to your confirmation URL
     * 
     * Postman Request Body:
     * {
     *   "TransactionType": "Pay Bill",
     *   "TransID": "OEI2AK4Q16",
     *   "TransTime": "20230615143000",
     *   "TransAmount": "100.00",
     *   "BusinessShortCode": "174379",
     *   "BillRefNumber": "LOAN-001",
     *   "InvoiceNumber": "",
     *   "OrgAccountBalance": "10000.00",
     *   "ThirdPartyTransID": "",
     *   "MSISDN": "254712345678",
     *   "FirstName": "John",
     *   "MiddleName": "Doe",
     *   "LastName": "Smith"
     * }
     */
    public function testC2BConfirmation(Request $request)
    {
        try {
            Log::info('=== C2B CONFIRMATION TEST STARTED ===');
            Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Process the payment
            $result = $this->mpesaService->processPaybillPayment($request->all());

            Log::info('=== C2B CONFIRMATION RESULT ===');
            Log::info(PHP_EOL . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            Log::info('=== C2B CONFIRMATION TEST COMPLETED ===');

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Accepted',
                'processing_result' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('=== C2B CONFIRMATION TEST FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'System error during confirmation'
            ]);
        }
    }

    /**
     * Test B2C Result Callback
     * 
     * Endpoint: POST /api/v1/mpesa/test-b2c-result
     * 
     * This simulates what Safaricom sends to your B2C result URL
     * 
     * Postman Request Body:
     * {
     *   "Result": {
     *     "ResultType": 0,
     *     "ResultCode": 0,
     *     "ResultDesc": "The service request is processed successfully.",
     *     "OriginatorConversationID": "10816-7910404-1",
     *     "ConversationID": "AG_20230615_00004f7e3b9f9e3c9b1e",
     *     "TransactionID": "OEI2AK4Q16",
     *     "ResultParameters": {
     *       "ResultParameter": [
     *         {"Key": "TransactionReceipt", "Value": "OEI2AK4Q16"},
     *         {"Key": "TransactionAmount", "Value": 100},
     *         {"Key": "B2CWorkingAccountAvailableFunds", "Value": 50000},
     *         {"Key": "B2CUtilityAccountAvailableFunds", "Value": 10000},
     *         {"Key": "TransactionCompletedDateTime", "Value": "15.06.2023 14:30:00"},
     *         {"Key": "ReceiverPartyPublicName", "Value": "254712345678 - John Doe"},
     *         {"Key": "B2CChargesPaidAccountAvailableFunds", "Value": 0},
     *         {"Key": "B2CRecipientIsRegisteredCustomer", "Value": "Y"}
     *       ]
     *     },
     *     "ReferenceData": {
     *       "ReferenceItem": {
     *         "Key": "QueueTimeoutURL",
     *         "Value": "https://internalsandbox.safaricom.co.ke/mpesa/b2cresults/v1/submit"
     *       }
     *     }
     *   }
     * }
     */
    public function testB2CResult(Request $request)
    {
        try {
            Log::info('=== B2C RESULT CALLBACK TEST STARTED ===');
            Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $result = $request->input('Result');
            $resultCode = $result['ResultCode'] ?? null;
            $conversationId = $result['ConversationID'] ?? null;

            // Log the complete result
            Log::info('=== B2C RESULT DETAILS ===');
            Log::info(PHP_EOL . json_encode([
                'result_code' => $resultCode,
                'result_desc' => $result['ResultDesc'] ?? null,
                'conversation_id' => $conversationId,
                'transaction_id' => $result['TransactionID'] ?? null
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Update transaction in database if needed
            // You would match this by ConversationID or OriginatorConversationID

            Log::info('=== B2C RESULT CALLBACK TEST COMPLETED ===');

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Accepted',
                'note' => 'B2C result received and logged successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('=== B2C RESULT CALLBACK TEST FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Error processing result'
            ]);
        }
    }

    /**
     * Test B2C Timeout Callback
     * 
     * Endpoint: POST /api/v1/mpesa/test-b2c-timeout
     * 
     * Postman Request Body:
     * {
     *   "Result": {
     *     "ResultType": 0,
     *     "ResultCode": 1,
     *     "ResultDesc": "The service request has timed out.",
     *     "OriginatorConversationID": "10816-7910404-1",
     *     "ConversationID": "AG_20230615_00004f7e3b9f9e3c9b1e",
     *     "TransactionID": ""
     *   }
     * }
     */
    public function testB2CTimeout(Request $request)
    {
        try {
            Log::info('=== B2C TIMEOUT CALLBACK TEST STARTED ===');
            Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $result = $request->input('Result');

            Log::info('=== B2C TIMEOUT DETAILS ===');
            Log::info(PHP_EOL . json_encode([
                'conversation_id' => $result['ConversationID'] ?? null,
                'result_desc' => $result['ResultDesc'] ?? null
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            Log::info('=== B2C TIMEOUT CALLBACK TEST COMPLETED ===');

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Accepted'
            ]);

        } catch (\Exception $e) {
            Log::error('=== B2C TIMEOUT CALLBACK TEST FAILED ===');
            Log::error('Error: ' . $e->getMessage());

            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Error processing timeout'
            ]);
        }
    }

    /**
     * Get recent test transactions
     * 
     * Endpoint: GET /api/v1/mpesa/test-transactions
     */
    public function getTestTransactions()
    {
        try {
            $transactions = MpesaTransaction::orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Recent transactions retrieved',
                'count' => $transactions->count(),
                'transactions' => $transactions
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching test transactions: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error fetching transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear cached M-Pesa token
     */
    private function clearCachedToken()
    {
        if (cache()->has('mpesa_access_token')) {
            cache()->forget('mpesa_access_token');
            Log::info('Cleared cached M-Pesa access token');
        }
    }
    
    /**
     * Generate fresh access token
     */
    private function generateAccessToken()
    {
        $consumerKey = config('mpesa.mpesa_consumer_key');
        $consumerSecret = config('mpesa.mpesa_consumer_secret');
        $environment = config('mpesa.environment');
        
            Log::info('=== M-PESA CONFIGURATION CHECK ===');
            Log::info(PHP_EOL . json_encode([
                'environment' => $environment,
                'consumer_key_set' => !empty($consumerKey),
                'consumer_secret_set' => !empty($consumerSecret),
                'consumer_key_length' => strlen($consumerKey ?? ''),
                'consumer_secret_length' => strlen($consumerSecret ?? ''),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        if (empty($consumerKey) || empty($consumerSecret)) {
            throw new \Exception('M-Pesa consumer key or secret not configured');
        }
        
        // Use environment-specific URL
        $url = ($environment === 'live' || $environment === 'production')
            ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
            : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
            
        $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
        
        // Also test using the package method
        try {
            $packageToken = \Iankumu\Mpesa\Facades\Mpesa::generateAccessToken();
            Log::info('=== PACKAGE TOKEN GENERATION SUCCESSFUL ===');
            Log::info(PHP_EOL . json_encode([
                'environment' => $environment,
                'package_method' => 'generateAccessToken'
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (\Exception $e) {
            Log::warning('=== PACKAGE TOKEN GENERATION FAILED ===');
            Log::warning('Error: ' . $e->getMessage());
        }
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $credentials,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            throw new \Exception('cURL error: ' . $error);
        }
        
        $responseData = json_decode($response, true);
        
        Log::info('=== M-PESA TOKEN GENERATION RESPONSE ===');
        Log::info(PHP_EOL . json_encode([
            'environment' => $environment,
            'url' => $url,
            'http_code' => $httpCode,
            'response' => $responseData
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        if ($httpCode !== 200) {
            throw new \Exception('Token generation failed with HTTP code: ' . $httpCode . ', Response: ' . $response);
        }
        
        if (!isset($responseData['access_token'])) {
            throw new \Exception('Access token not found in response: ' . $response);
        }
        
        return [
            'token_generated' => true,
            'environment' => $environment,
            'token_length' => strlen($responseData['access_token']),
            'expires_in' => $responseData['expires_in'] ?? 'unknown',
            'http_code' => $httpCode
        ];
    }
}
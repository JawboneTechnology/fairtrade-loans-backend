<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Iankumu\Mpesa\Facades\Mpesa;

class MpesaTestController extends Controller
{
    /**
     * Test M-Pesa access token generation
     */
    public function testAccessToken()
    {
        try {
            // Test token generation by making a simple request
            Log::info('Testing M-Pesa access token generation...');
            
            // Clear any cached tokens first
            $this->clearCachedToken();
            
            // Try to generate a new token by making a test request
            $response = $this->generateAccessToken();
            
            return response()->json([
                'success' => true,
                'message' => 'Access token test completed',
                'token_info' => $response
            ]);
            
        } catch (\Exception $e) {
            Log::error('M-Pesa access token test failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Access token test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Clear cached M-Pesa token
     */
    private function clearCachedToken()
    {
        // Clear Laravel cache if tokens are cached there
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
        
        Log::info('M-Pesa Configuration Check', [
            'environment' => $environment,
            'consumer_key_set' => !empty($consumerKey),
            'consumer_secret_set' => !empty($consumerSecret),
            'consumer_key_length' => strlen($consumerKey ?? ''),
            'consumer_secret_length' => strlen($consumerSecret ?? ''),
        ]);
        
        if (empty($consumerKey) || empty($consumerSecret)) {
            throw new \Exception('M-Pesa consumer key or secret not configured');
        }
        
        // Use environment-specific URL
        $url = ($environment === 'live' || $environment === 'production')
            ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
            : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
            
        $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
        
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
        
        Log::info('M-Pesa Token Generation Response', [
            'environment' => $environment,
            'url' => $url,
            'http_code' => $httpCode,
            'response' => $responseData
        ]);
        
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
    
    /**
     * Test C2B registration with fresh token
     */
    public function testC2BRegistration()
    {
        try {
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
            
            // Now try the C2B registration
            $shortcode = config('mpesa.shortcode');
            $validationUrl = config('mpesa.c2b_validation_url');
            $confirmationUrl = config('mpesa.c2b_confirmation_url');
            
            Log::info('Testing C2B Registration with fresh token', [
                'shortcode' => $shortcode,
                'validation_url' => $validationUrl,
                'confirmation_url' => $confirmationUrl
            ]);
            
            $response = Mpesa::c2bregisterURLS($shortcode);
            
            return response()->json([
                'success' => true,
                'message' => 'C2B registration test completed',
                'token_test' => $tokenTest,
                'registration_response' => [
                    'status_code' => method_exists($response, 'status') ? $response->status() : 'unknown',
                    'body' => method_exists($response, 'json') ? $response->json() : $response
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('M-Pesa C2B registration test failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'C2B registration test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
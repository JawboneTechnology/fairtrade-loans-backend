<?php

namespace App\Jobs;

use Iankumu\Mpesa\Facades\Mpesa;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessStkPushJob implements ShouldQueue
{
    use Queueable;

    public $phoneNumber;
    public $amount;
    public $accountReference;
    public $transactionDescription;

    /**
     * Create a new job instance.
     */
    public function __construct($phoneNumber, $amount, $accountReference, $transactionDescription)
    {
        $this->phoneNumber = $phoneNumber;
        $this->amount = $amount;
        $this->accountReference = $accountReference;
        $this->transactionDescription = $transactionDescription;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Call M-Pesa STK Push API
        // Method signature: stkpush($phonenumber, $amount, $account_number, $callbackurl = null)
        $response = Mpesa::stkpush(
            $this->phoneNumber,
            $this->amount,
            $this->accountReference,
            route('mpesa.stk-callback')
        );

        // Convert response to array for logging
        $responseData = [];
        if (method_exists($response, 'json')) {
            $responseData = $response->json();
        } elseif (method_exists($response, 'body')) {
            $responseData = json_decode($response->body(), true) ?? [];
        }

        // Log the response
        Log::info('=== STK PUSH JOB RESPONSE ===');
        Log::info(PHP_EOL . json_encode([
            'amount' => $this->amount,
            'phone' => $this->phoneNumber,
            'reference' => $this->accountReference,
            'response' => $responseData,
            'status' => method_exists($response, 'status') ? $response->status() : 'unknown'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

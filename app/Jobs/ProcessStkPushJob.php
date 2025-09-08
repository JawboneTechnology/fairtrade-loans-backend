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
        $response = Mpesa::stkPush([
            'amount' => $this->amount,
            'phone' => $this->phoneNumber,
            'reference' => $this->accountReference,
            'description' => $this->transactionDescription,
            'callback' => route('mpesa.stk-callback'), // Define your callback route
        ]);

        // Log the response or handle as needed
        log::info('STK Push Response:', [
            'amount' => $this->amount,
            'phone' => $this->phoneNumber,
            'reference' => $this->accountReference,
            'description' => $this->transactionDescription,
            'callback' => route('mpesa.stk-callback'), // Define your callback route
        ]);
    }
}

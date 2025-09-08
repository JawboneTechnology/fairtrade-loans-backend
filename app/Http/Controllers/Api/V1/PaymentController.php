<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\StkPushRequested;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    // handle STK Push
    public function initiateStkPush(Request $request)
    {
        $phoneNumber = $request->input('phone');
        $amount = $request->input('amount');
        $accountReference = 'Order123'; // Example reference
        $transactionDescription = 'Payment for order 123';

        // Dispatch the event
        event(new StkPushRequested($phoneNumber, $amount, $accountReference, $transactionDescription));

        return response()->json(['message' => 'STK Push initiated']);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\USSDService;

class USSDController extends Controller
{
    protected $ussdService;

    public function __construct(USSDService $ussdService)
    {
        $this->ussdService = $ussdService;
    }

    public function handleUSSD(Request $request)
    {
        $sessionId = $request->get('sessionId');
        $phoneNumber = $request->get('phoneNumber');
        $text = $request->get('text');

        $response = $this->ussdService->processUSSDRequest($sessionId, $phoneNumber, $text);

        return response($response, 200)
            ->header('Content-Type', 'text/plain');
    }
}

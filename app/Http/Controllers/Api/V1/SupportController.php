<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Events\SupportMessageSubmitted;
use App\Http\Requests\SupportMessageRequest;

class SupportController extends Controller
{
    /**
     * Submit a support message
     *
     * @param SupportMessageRequest $request
     * @return JsonResponse
     */
    public function submitSupportMessage(SupportMessageRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // Dispatch event which will trigger the email and SMS jobs
            event(new SupportMessageSubmitted($data));

            return response()->json([
                'success' => true,
                'message' => 'Support message submitted successfully. We will get back to you soon.',
                'data' => [
                    'submitted_at' => now()->toISOString(),
                    'reference' => 'SUP' . strtoupper(substr(md5(uniqid()), 0, 8)),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit support message.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\PasswordResetService;
use App\Http\Controllers\Controller;
use App\Models\User;

class PasswordResetController extends Controller
{
    protected $passwordResetService;

    public function __construct(PasswordResetService $passwordResetService)
    {
        $this->passwordResetService = $passwordResetService;
    }

    // Send Reset Code
    public function sendResetCode(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $this->passwordResetService->forgotPassword($user);

            return response()->json([
                'success' => true,
                'message' => 'Reset code sent successfully',
                'data' => [
                    'email' => $user->email,
                ]
            ], 200);
        } catch (\Exception $exception) {
            // Return an error response
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again',
                'error' => $exception->getMessage()
            ], 500);
        }
    }

    // Verify Reset Code
    public function verifyResetCode(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
                'reset_code' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::select('id', 'email')->where('email', $request->email)->first();
            $user->reset_code = $request->reset_code;

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $this->passwordResetService->verifyResetCode($user);

            return response()->json([
                'success' => true,
                'message' => 'Reset code verified successfully',
                'data' => [
                    'email' => $user->email,
                ]
            ], 200);
        } catch (\Exception $exception) {
            // Return an error response
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again',
                'error' => $exception->getMessage()
            ], 500);
        }
    }

    // Reset Password
    public function resetPassword(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
                'reset_code' => 'required|numeric',
                'password' => 'required|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $this->passwordResetService->resetPassword($user, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully',
                'data' => []
            ], 200);
        } catch (\Exception $exception) {
            // Return an error response
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while resetting your password. Please try again',
                'error' => $exception->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Jobs\SendOtpCode;
use Illuminate\Http\Request;
use App\Services\LoanService;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use \Illuminate\Contracts\View\View;
use App\Http\Requests\UserLoginRequest;
use \Illuminate\Foundation\Application;
use \Illuminate\Contracts\View\Factory;
use App\Http\Requests\AuthNumberRequest;
use App\Http\Requests\UserOtpAuthRequest;
use App\Http\Requests\AdminRegisterRequest;
use App\Http\Requests\PasswordResetRequest;
use App\Http\Requests\VerifyAccountRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ExternalRegisterRequest;
use App\Http\Requests\PasswordResetCodeRequest;

class AuthController extends Controller
{
    private AuthService $authService;
    private LoanService $loanService;

    public function __construct(AuthService $authService, LoanService $loanService)
    {
        $this->authService = $authService;
        $this->loanService = $loanService;
    }

    public function register(AdminRegisterRequest $request): JsonResponse
    {
        try {
            $user = $this->authService->registerAdmin($request->all());
            return response()->json([ 'success' => true, 'message' => 'User created successfully', 'data'    => new UserResource($user) ], 201);
        } catch (\Exception $exception) {
            return response()->json([ 'success' => false, 'message' => 'An error occurred. Please try again', 'error'   => $exception->getMessage() ], 500);
        }
    }

    public function loginUser(LoginRequest $request): JsonResponse
    {
        try {

            if (!Auth::attempt($request->only( 'phone_number', 'password'))) {
                return response()->json([ 'success' => false, 'message' => 'Invalid login details' ], 401);
            }

            $user = User::with('roles')->where('phone_number', $request->phone_number)->first();

            if (!$user) {
                return response()->json([ 'success' => false, 'message' => 'User not found', 'error' => 'User not found'], 404);
            }

            if (!$user->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'This account is not verified. Please verify your email address.',
                    'data' => null
                ], 404);
            }

            $restrictedRoles = ['supervisor', 'admin', 'manager', 'super-admin', 'hr'];

            if ($user->roles->pluck('name')->intersect($restrictedRoles)->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your role is not authorized to access this application.',
                    'data' => null
                ], 403);
            }

            $userRole = $user->roles->first();
            $user->role = $userRole->name;

            $user = $this->authService->verifyUserAccount($user);

            return response()->json([ 'success' => true, 'message' => 'User logged in successfully', 'data' => $user ], 200);
        } catch (\Exception $exception) {
            return response()->json([ 'success' => false, 'message' => 'An error occurred. Please try again', 'error' => $exception->getMessage() ], 500);
        }
    }

    public function loginDashboardUsers(UserLoginRequest $request): JsonResponse
    {

        try {
            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json([ 'success' => false, 'message' => 'Invalid login details' ], 401);
            }

            $user = User::with('roles')->where('email', $request->email)->first();

            // Check if user has at least one role
            if ($user->roles->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User has no assigned roles. Please contact administrator.',
                ], 403);
            }

            // Get the user's primary role (first role)
            $userRoleName = $user->roles->first()->name;

            // Handle login based on role
            // Allow all roles: employee, admin, super-admin, employer, etc.
            if ($userRoleName === 'super-admin') {
                $userData = $this->authService->loginSuperAdmin($request->all(), $userRoleName);
            } elseif (in_array($userRoleName, ['admin', 'employer', 'employee'])) {
                $userData = $this->authService->loginAdmin($request->all(), $userRoleName);
            } else {
                // For any other custom roles, use the generic admin login
                $userData = $this->authService->loginAdmin($request->all(), $userRoleName);
            }

            return response()->json([
                'success' => true,
                'message' => 'User logged in successfully',
                'data' => $userData
            ], 200);

        } catch (\Exception $exception) {
            return response()->json([ 'success' => false, 'message' => 'An error occurred. Please try again', 'error' => $exception->getMessage() ], 500);
        }
    }

    public function profile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $this->loanService->calculateLoanLimit($user);

            return response()->json([ 'success' => true, 'message' => 'User profile retrieved successfully', 'data' => $user ], 200);
        } catch (\Exception $exception) {
            return response()->json([ 'success' => false, 'message' => 'An error occurred. Please try again', 'error' => $exception->getMessage() ], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return response()->json([ 'success' => true, 'message' => 'User logged out successfully' ], 200);
        } catch (\Exception $exception) {
            return response()->json([ 'success' => false, 'message' => 'An error occurred. Please try again', 'error' => $exception->getMessage() ], 500);
        }
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }

            $this->authService->forgetPassword($user->email);

            return response()->json([
                'success' => true,
                'message' => 'A password reset code has been sent to your email',
                'data' => null
            ], 200);
        } catch (\Exception $exception) {
            return response()->json([ 'success' => false, 'message' => 'An error occurred. Please try again', 'error' => $exception->getMessage() ], 500);
        }
    }

    public function verifyResetPasswordCode(PasswordResetCodeRequest $request): JsonResponse
    {
        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([ 'success' => false, 'message' => 'User not found.' ], 404);
            }

            $this->authService->verifyResetCode($user, $request->code);

            $data = [
                'email' => $user->email,
            ];

            return response()->json([ 'success' => true, 'message' => 'Reset code verified successfully.', 'data' => $data ], 200);
        } catch (\Exception $exception) {
            return response()->json([ 'success' => false, 'message' => $exception->getMessage(), 'data' => null ], 400);
        }
    }

    public function resetPassword(PasswordResetRequest $request): JsonResponse
    {
        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([ 'success' => false, 'message' => 'User not found.' ], 404);
            }

            $this->authService->updateUserPassword($user, $request->password);

            return response()->json([ 'success' => true, 'message' => 'Password updated successfully.', 'data' => $user ], 200);
        } catch (\Exception $exception) {
            return response()->json([ 'success' => false, 'message' => $exception->getMessage(), 'data' => null ], 400);
        }
    }

    public function verifyAccount(VerifyAccountRequest $request): Factory|Application|View|JsonResponse
    {
        try {
            DB::beginTransaction();
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([ 'success' => false, 'message' => 'User not found.', 'data' => null ], 404);
            }

            if ($user->email_verified_at !== null) {
                return view('auth.already-verify-account');
            }

            $user->email_verified_at = now();

            $user->update();

            DB::commit();

            return view('auth.verify-account');
        } catch (\Exception $exception) {
            DB::rollBack();
            return response()->json([ 'success' => false, 'message' => $exception->getMessage(), 'data' => null ], 500);
        }
    }

    public function userOtpAuth(UserOtpAuthRequest $request): JsonResponse
    {
        try {
            $resetCode = rand(100000, 999999);
            $user = User::with('roles')->where('phone_number', $request->phone)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null
                ], 404);
            }

            if (!$user->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'This account is not verified. Please verify your email address.',
                    'data' => null
                ], 404);
            }

            $restrictedRoles = ['supervisor', 'admin', 'manager', 'super-admin', 'hr'];
            
            if ($user->roles->pluck('name')->intersect($restrictedRoles)->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your role is not authorized to access this application.',
                    'data' => null
                ], 403);
            }

            SendOtpCode::dispatch($resetCode, $user);

            return response()->json([
                'success' => true,
                'message' => 'Phone number verified successfully',
                'data' => null
            ], 200);
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during authentication',
                'error' => $exception->getMessage(),
                'data' => null
            ], 500);
        }
    }

    public function phoneNumberAuth(AuthNumberRequest $request): JsonResponse
    {
        try {
            $user = User::where('phone_number', $request->phone)->first();

            if (!$user) {
                return response()->json([ 'success' => false, 'message' => 'User not found.', 'data' => null ], 404);
            }

            $response = $this->authService->verifyUserAccount($user, $request->otp_code);

            return response()->json([
                'success' => true,
                'message' => 'Account verification successfully',
                'data' => $response
            ], 200);
        } catch (\Exception $exception) {
            return response()->json([ 'success' => false, 'message' => $exception->getMessage(), 'data' => null ], 500);
        }
    }

    public function changePasswordInternal(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $authUser = auth()->user();

            $user = User::findOrFail($authUser->id);

            $this->authService->changeUserPassword($user, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully',
                'data' => null
            ], 200);
        } catch (\Exception $exception) {
            return response()->json([ 'success' => false, 'message' => $exception->getMessage(), 'data' => null ], 500);
        }
    }

    public function externalRegister(ExternalRegisterRequest $request): JsonResponse
    {
        try {
            $this->authService->handleExternalRegister($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Account created successfully',
                'data' => $request->all()
            ], 201);
        } catch (\Exception $exception) {
            return response()->json([ 'success' => false, 'message' => $exception->getMessage(), 'data' => null ], 500);
        }
    }
}

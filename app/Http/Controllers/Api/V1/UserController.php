<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\EmployeeResource;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UserProfileRequest;
use App\Http\Requests\EmployeeSalaryRequest;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function getUsers()
    {
        return $this->userService->getUsers();
    }

    public function deleteUser(Request $request, $userId): JsonResponse
    {
        try {
            // Find user by ID
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            $this->userService->deleteUser($user);

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully.',
                'data' => null,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors occurred.',
                'errors' => $e->errors(),
            ], 500);

        }
    }

    public function createUser(CreateUserRequest $request): JsonResponse
    {
        try {
            $user = $this->userService->createUser($request->all());

            $user['token'] = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'User created successfully. Please check your email for the verification link and login details.',
                'data' => new UserResource($user),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors occurred.',
                'errors' => $e->getMessage(),
            ], 500);

        }
    }

    public function updateUser(Request $request, $userId): JsonResponse
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            $user = $this->userService->updateUser($user, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully.',
                'data' => new UserResource($user),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors occurred.',
                'errors' => $e->getMessage(),
            ], 500);

        }
    }

    public function setEmployeeSalary(EmployeeSalaryRequest $request, $employeeId): JsonResponse
    {
        try {
            $employee = User::find($employeeId);

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found.',
                ], 404);
            }

            $employee = $this->userService->setEmployeeSalary($employee, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Employee salary updated successfully.',
                'data' => ['salary' => $employee->salary],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);

        }
    }

    public function updateProfile(UserProfileRequest $request, $userId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            $user = User::findOrFail($userId);

            if (!$user) {
                return response()->json([ 'success' => false, 'message' => 'User not found.', 'data' => null ], 404);
            }

            $user->updated_at = now();
            $user->update($validatedData);
            $user->token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully.',
                'data' => new EmployeeResource($user)
            ], 200);
        } catch (\Exception $exception) {
            DB::rollBack();
            return response()->json([ 'success' => false, 'message' => $exception->getMessage(), 'data' => null ], 500);
        }
    }

    public function searchUser(Request $request): JsonResponse
    {
        try {
            $authUser = auth()->user();

            $user = User::findOrFail($authUser->id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null,
                ], 404);
            }

            $query = $request->input('query', "");
            $start = $request->input('start', 0);
            $limit = $request->input('limit', 10);

            $results = $this->userService->searchSystemUsers($query, $start, $limit);

            return response()->json([
                'success' => true,
                'message' => 'Users fetched successfully.',
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search for users.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getSystemEmployees(Request $request): JsonResponse
    {
        return $this->userService->getUsers();
    }

    public function getEmployee(Request $request, $userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null,
                ]);
            }

            $userDetails = $this->userService->getEmployeeDetails($user);

            return response()->json([
                'success' => true,
                'message' => 'User fetched successfully.',
                'data' => $userDetails,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate mini statement.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

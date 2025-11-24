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
use Illuminate\Support\Facades\Log;

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

    /**
     * Update Employee Profile Details
     * Allows updating personal information fields
     */
    public function updateEmployeeProfile(\App\Http\Requests\UpdateEmployeeProfileRequest $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = User::with('roles')->findOrFail($id);

            // Get validated data
            $validatedData = $request->validated();

            // Update only the fields that were provided
            $user->fill($validatedData);
            $user->save();

            // Reload user with relationships and counts
            $user->refresh();
            $user->loadCount([
                'loans as total_loans_count',
                'loans as pending_loans_count'      => fn($q) => $q->where('loan_status', 'pending'),
                'loans as processing_loans_count'   => fn($q) => $q->where('loan_status', 'processing'),
                'loans as approved_loans_count'     => fn($q) => $q->where('loan_status', 'approved'),
                'loans as rejected_loans_count'     => fn($q) => $q->where('loan_status', 'rejected'),
                'loans as completed_loans_count'    => fn($q) => $q->where('loan_status', 'completed'),
                'loans as repaid_loans_count'       => fn($q) => $q->where('loan_status', 'repaid'),
                'loans as defaulted_loans_count'    => fn($q) => $q->where('loan_status', 'defaulted'),
                'loans as canceled_loans_count'     => fn($q) => $q->where('loan_status', 'canceled'),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Employee profile updated successfully.',
                'data' => [
                    'id'                    => $user->id,
                    'employee_id'           => $user->employee_id,
                    'created_by'            => $user->created_by,
                    'first_name'            => $user->first_name,
                    'middle_name'           => $user->middle_name,
                    'last_name'             => $user->last_name,
                    'email'                 => $user->email,
                    'phone_number'          => $user->phone_number,
                    'address'               => $user->address,
                    'dob'                   => $user->dob,
                    'gender'                => $user->gender,
                    'national_id'           => $user->national_id,
                    'passport_image'        => $user->passport_image,
                    'years_of_employment'   => $user->years_of_employment,
                    'salary'                => $user->salary,
                    'loan_limit'            => $user->loan_limit,
                    'email_verified_at'     => $user->email_verified_at,
                    'created_at'            => $user->created_at,
                    'updated_at'            => $user->updated_at,
                    'roles'                 => $user->roles,
                    'total_loans_count'     => $user->total_loans_count,
                    'pending_loans_count'   => $user->pending_loans_count,
                    'processing_loans_count' => $user->processing_loans_count,
                    'approved_loans_count'  => $user->approved_loans_count,
                    'rejected_loans_count'  => $user->rejected_loans_count,
                    'completed_loans_count' => $user->completed_loans_count,
                    'repaid_loans_count'    => $user->repaid_loans_count,
                    'defaulted_loans_count' => $user->defaulted_loans_count,
                    'canceled_loans_count'  => $user->canceled_loans_count,
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Employee not found.',
                'data' => null
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('=== ERROR UPDATING EMPLOYEE PROFILE ===');
            Log::error('Employee ID: ' . $id);
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee profile: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Change Employee Password (Admin Only)
     * Allows admin to reset an employee's password
     */
    public function changeEmployeePassword(\App\Http\Requests\ChangeEmployeePasswordRequest $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($id);

            // Get the new password from request (before hashing)
            $newPassword = $request->validated()['password'];

            // Hash and update the password
            $user->password = bcrypt($newPassword);
            $user->save();

            // Get the admin who changed the password
            $changedBy = auth()->user();

            // Log the password change
            Log::info('=== PASSWORD CHANGED BY ADMIN ===');
            Log::info('Employee ID: ' . $user->id);
            Log::info('Employee Email: ' . $user->email);
            Log::info('Changed By: ' . ($changedBy->email ?? 'System'));
            Log::info('Timestamp: ' . now()->toDateTimeString());

            // Dispatch event to send password notification email
            event(new \App\Events\EmployeePasswordChanged(
                $user,
                $newPassword,
                $changedBy
            ));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Employee password changed successfully. Notification email is being sent.',
                'data' => [
                    'id'                    => $user->id,
                    'employee_id'           => $user->employee_id,
                    'created_by'            => $user->created_by,
                    'email'                 => $user->email,
                    'first_name'            => $user->first_name,
                    'middle_name'           => $user->middle_name,
                    'last_name'             => $user->last_name,
                    'password_updated_at'   => now()->toDateTimeString(),
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            
            Log::warning('=== PASSWORD CHANGE FAILED - EMPLOYEE NOT FOUND ===');
            Log::warning('Employee ID: ' . $id);
            
            return response()->json([
                'success' => false,
                'message' => 'Employee not found.',
                'data' => null
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Password validation failed.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('=== ERROR CHANGING EMPLOYEE PASSWORD ===');
            Log::error('Employee ID: ' . $id);
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to change employee password: ' . $e->getMessage(),
                'data' => null
            ], 500);
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

    /**
     * Change Employee Roles (Admin Only)
     * Allows admin to update an employee's roles
     */
    public function changeEmployeeRoles(\App\Http\Requests\ChangeEmployeeRolesRequest $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($id);

            // Get current roles before change
            $oldRoles = $user->roles->pluck('name')->toArray();

            // Get new roles from request
            $newRoles = $request->validated()['roles'];

            // Get the admin who changed the roles
            $changedBy = auth()->user();

            // Sync the new roles (this will remove old roles and assign new ones)
            $user->syncRoles($newRoles);

            // Refresh the user to get updated roles
            $user->refresh();
            $user->load('roles');

            DB::commit();

            // Log the role change
            Log::info('=== EMPLOYEE ROLES CHANGED BY ADMIN ===');
            Log::info('Employee ID: ' . $user->id);
            Log::info('Employee Email: ' . $user->email);
            Log::info('Old Roles: ' . implode(', ', $oldRoles));
            Log::info('New Roles: ' . implode(', ', $newRoles));
            Log::info('Changed By: ' . ($changedBy->email ?? 'System'));
            Log::info('Timestamp: ' . now()->toDateTimeString());

            return response()->json([
                'success' => true,
                'message' => 'Employee roles updated successfully.',
                'data' => [
                    'id'                => $user->id,
                    'employee_id'       => $user->employee_id,
                    'email'             => $user->email,
                    'first_name'        => $user->first_name,
                    'last_name'         => $user->last_name,
                    'old_roles'         => $oldRoles,
                    'new_roles'         => $user->roles->pluck('name')->toArray(),
                    'roles_updated_at'  => now()->toDateTimeString(),
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            
            Log::warning('=== ROLES CHANGE FAILED - EMPLOYEE NOT FOUND ===');
            Log::warning('Employee ID: ' . $id);
            
            return response()->json([
                'success' => false,
                'message' => 'Employee not found.',
                'data'    => null
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Roles validation failed.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('=== ERROR CHANGING EMPLOYEE ROLES ===');
            Log::error('Employee ID: ' . $id);
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to change employee roles: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Delete User Account (Admin Only)
     * Checks for pending loans, active loans, and grants before deletion
     */
    public function deleteUserAccount($id): JsonResponse
    {
        try {
            $user = User::with(['loans', 'roles'])->findOrFail($id);

            // Validate if account can be deleted using UserService
            $validationResult = $this->userService->validateAccountDeletion($user);

            // If validation fails, return the error response
            if (!$validationResult['can_delete']) {
                return response()->json([
                    'success' => false,
                    'message' => $validationResult['message'],
                    'data' => $validationResult['data']
                ], $validationResult['status_code']);
            }

            // Prepare deletion data
            $deletionData = $this->userService->prepareDeletionData($user);

            // Log the deletion request
            Log::info('=== USER ACCOUNT DELETION INITIATED ===');
            Log::info('User ID: ' . $user->id);
            Log::info('Employee ID: ' . ($user->employee_id ?? 'N/A'));
            Log::info('Email: ' . $user->email);
            Log::info('Deleted By: ' . ($deletionData['deleted_by'] ?? 'System'));
            Log::info('Timestamp: ' . now()->toDateTimeString());

            // Dispatch event to handle email notification and deletion
            event(new \App\Events\UserAccountDeleted(
                $deletionData['user_id'],
                $deletionData['email'],
                $deletionData['name'],
                $deletionData['employee_id'],
                $deletionData['deleted_by']
            ));

            return response()->json([
                'success' => true,
                'message' => 'Account deletion initiated successfully. User will receive an email notification and the account will be removed.',
                'data' => array_merge($deletionData, [
                    'notification_status' => 'Email notification is being sent'
                ])
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('=== USER DELETION FAILED - USER NOT FOUND ===');
            Log::warning('User ID: ' . $id);
            
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'data' => null
            ], 404);

        } catch (\Exception $e) {
            Log::error('=== ERROR DELETING USER ACCOUNT ===');
            Log::error('User ID: ' . $id);
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user account: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Bulk Import Employees from CSV (Admin Only)
     * Accepts CSV file and creates multiple user accounts
     */
    public function bulkImportEmployees(\App\Http\Requests\BulkImportEmployeesRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            $sendEmails = $request->input('send_emails', true);

            Log::info('=== BULK EMPLOYEE IMPORT INITIATED ===');
            Log::info('File Name: ' . $file->getClientOriginalName());
            Log::info('File Size: ' . $file->getSize() . ' bytes');
            Log::info('Send Emails: ' . ($sendEmails ? 'Yes' : 'No'));
            Log::info('Initiated By: ' . (auth()->user()->email ?? 'System'));
            Log::info('Timestamp: ' . now()->toDateTimeString());

            // Process the CSV file using UserService
            $results = $this->userService->bulkImportEmployees($file, $sendEmails);

            // Log results
            Log::info('=== BULK EMPLOYEE IMPORT COMPLETED ===');
            Log::info('Total Rows: ' . $results['total']);
            Log::info('Successful: ' . $results['successful']);
            Log::info('Failed: ' . $results['failed']);

            // Determine response status
            if ($results['failed'] > 0 && $results['successful'] === 0) {
                // All failed
                return response()->json([
                    'success' => false,
                    'message' => 'All imports failed. Please check the errors and try again.',
                    'data' => $results
                ], 422);
            } elseif ($results['failed'] > 0) {
                // Partial success
                return response()->json([
                    'success' => true,
                    'message' => "Import completed with some errors. {$results['successful']} users created successfully, {$results['failed']} failed.",
                    'data' => $results
                ], 207); // 207 Multi-Status
            } else {
                // All successful
                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported {$results['successful']} employee(s)." . ($sendEmails ? ' Welcome emails have been sent.' : ''),
                    'data' => $results
                ], 201);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('=== ERROR IN BULK EMPLOYEE IMPORT ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk import: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Get Employee Statistics for Dashboard
     * Returns comprehensive statistics about employees, loans, grants, etc.
     */
    public function getEmployeeStatistics(): JsonResponse
    {
        try {
            Log::info('=== EMPLOYEE STATISTICS REQUEST ===');
            Log::info('Requested By: ' . (auth()->user()->email ?? 'System'));
            Log::info('Timestamp: ' . now()->toDateTimeString());

            $statistics = $this->userService->getEmployeeStatistics();

            return response()->json([
                'success' => true,
                'message' => 'Employee statistics retrieved successfully.',
                'data' => $statistics
            ], 200);

        } catch (\Exception $e) {
            Log::error('=== ERROR RETRIEVING EMPLOYEE STATISTICS ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee statistics: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}

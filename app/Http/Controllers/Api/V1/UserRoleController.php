<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignRolesToUserRequest;
use App\Services\UserRoleService;
use App\Http\Resources\UserRoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserRoleController extends Controller
{
    protected UserRoleService $userRoleService;

    public function __construct(UserRoleService $userRoleService)
    {
        $this->userRoleService = $userRoleService;
    }

    /**
     * Get user's roles and permissions
     */
    public function getUserRolesAndPermissions(string $userId): JsonResponse
    {
        try {
            // Check if user can access (admin or own user)
            $currentUser = auth()->user();
            if (!$currentUser->hasRole('super-admin') && !$currentUser->hasRole('admin') && $currentUser->id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this user\'s roles and permissions',
                    'data' => null,
                ], 403);
            }

            $data = $this->userRoleService->getUserRolesAndPermissions($userId);

            return response()->json([
                'success' => true,
                'message' => 'User roles and permissions retrieved successfully',
                'data' => $data,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error retrieving user roles and permissions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user roles and permissions',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Assign roles to user
     */
    public function assignRoles(AssignRolesToUserRequest $request, string $userId): JsonResponse
    {
        try {
            $user = $this->userRoleService->assignRoles($userId, $request->input('roles'));

            return response()->json([
                'success' => true,
                'message' => 'Roles assigned to user successfully',
                'data' => [
                    'user_id' => $user->id,
                    'roles' => $user->roles->pluck('name')->toArray(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error assigning roles to user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign roles to user',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Sync roles to user
     */
    public function syncRoles(AssignRolesToUserRequest $request, string $userId): JsonResponse
    {
        try {
            $user = $this->userRoleService->syncRoles($userId, $request->input('roles'));

            return response()->json([
                'success' => true,
                'message' => 'Roles synced to user successfully',
                'data' => [
                    'user_id' => $user->id,
                    'roles' => $user->roles->pluck('name')->toArray(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error syncing roles to user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync roles to user',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Remove role from user
     */
    public function removeRole(string $userId, string $roleId): JsonResponse
    {
        try {
            $user = $this->userRoleService->removeRole($userId, $roleId);

            return response()->json([
                'success' => true,
                'message' => 'Role removed from user successfully',
                'data' => [
                    'user_id' => $user->id,
                    'roles' => $user->roles->pluck('name')->toArray(),
                ],
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error removing role from user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove role from user',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Assign direct permission to user
     */
    public function assignPermission(Request $request, string $userId): JsonResponse
    {
        try {
            $request->validate([
                'permission_id' => 'required|uuid|exists:permissions,id',
            ]);

            $user = $this->userRoleService->assignPermission($userId, $request->input('permission_id'));

            return response()->json([
                'success' => true,
                'message' => 'Permission assigned to user successfully',
                'data' => [
                    'user_id' => $user->id,
                    'permissions' => $user->permissions->pluck('name')->toArray(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error assigning permission to user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign permission to user',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Remove direct permission from user
     */
    public function removePermission(string $userId, string $permissionId): JsonResponse
    {
        try {
            $user = $this->userRoleService->removePermission($userId, $permissionId);

            return response()->json([
                'success' => true,
                'message' => 'Permission removed from user successfully',
                'data' => [
                    'user_id' => $user->id,
                    'permissions' => $user->permissions->pluck('name')->toArray(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error removing permission from user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove permission from user',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}

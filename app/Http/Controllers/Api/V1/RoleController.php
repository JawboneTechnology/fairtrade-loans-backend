<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Http\Requests\CreateRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Requests\SyncRolePermissionsRequest;
use App\Http\Requests\BulkAssignRolesRequest;
use App\Services\RoleService;
use App\Http\Resources\RoleResource;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class RoleController extends Controller
{
    protected RoleService $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    // ========== NEW ENDPOINTS (Standardized) ==========

    /**
     * Legacy: Get roles (DataTables format) - kept for backward compatibility
     */
    public function index()
    {
        $roles = Role::all();
        return DataTables::of($roles)->make(true);
    }

    /**
     * Get all roles (new standardized endpoint)
     */
    public function getRolesStandardized(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->input('search'),
                'include_permissions' => $request->boolean('include_permissions'),
                'include_users_count' => $request->boolean('include_users_count'),
                'per_page' => $request->input('per_page', 15),
            ];

            $result = $this->roleService->getRoles($filters);

            return response()->json([
                'success' => true,
                'message' => 'Roles retrieved successfully',
                'data' => $result,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving roles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve roles',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get single role
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $options = [
                'include_permissions' => $request->boolean('include_permissions', true),
                'include_users' => $request->boolean('include_users', false),
            ];

            $role = $this->roleService->getRole($id, $options);

            return response()->json([
                'success' => true,
                'message' => 'Role retrieved successfully',
                'data' => $role,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error retrieving role: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve role',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Create role
     */
    public function store(CreateRoleRequest $request): JsonResponse
    {
        try {
            $role = $this->roleService->createRole($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
                'data' => new RoleResource($role),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating role: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create role',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update role
     */
    public function update(UpdateRoleRequest $request, string $id): JsonResponse
    {
        try {
            $role = $this->roleService->updateRole($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully',
                'data' => new RoleResource($role),
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error updating role: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Delete role
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->roleService->deleteRole($id);

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully',
                'data' => null,
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting role: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Sync permissions to role
     */
    public function syncPermissions(SyncRolePermissionsRequest $request, string $id): JsonResponse
    {
        try {
            $role = $this->roleService->syncPermissions($id, $request->input('permissions'));

            return response()->json([
                'success' => true,
                'message' => 'Permissions synced successfully',
                'data' => new RoleResource($role),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error syncing permissions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync permissions',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Add permission to role
     */
    public function addPermission(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'permission_id' => 'required|uuid|exists:permissions,id',
            ]);

            $role = $this->roleService->addPermission($id, $request->input('permission_id'));

            return response()->json([
                'success' => true,
                'message' => 'Permission added to role successfully',
                'data' => new RoleResource($role),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error adding permission to role: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add permission to role',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Remove permission from role
     */
    public function removePermission(string $id, string $permissionId): JsonResponse
    {
        try {
            $role = $this->roleService->removePermission($id, $permissionId);

            return response()->json([
                'success' => true,
                'message' => 'Permission removed from role successfully',
                'data' => new RoleResource($role),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error removing permission from role: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove permission from role',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get users by role
     */
    public function getUsersByRole(Request $request, string $id): JsonResponse
    {
        try {
            $filters = [
                'per_page' => $request->input('per_page', 15),
            ];

            $result = $this->roleService->getUsersByRole($id, $filters);

            return response()->json([
                'success' => true,
                'message' => 'Users with role retrieved successfully',
                'data' => $result,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving users by role: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users by role',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get role statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $statistics = $this->roleService->getStatistics();

            return response()->json([
                'success' => true,
                'message' => 'Roles statistics retrieved successfully',
                'data' => $statistics,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving role statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve role statistics',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Bulk assign role to users
     */
    public function bulkAssign(BulkAssignRolesRequest $request): JsonResponse
    {
        try {
            $result = $this->roleService->bulkAssignRole(
                $request->input('user_ids'),
                $request->input('role_id')
            );

            return response()->json([
                'success' => true,
                'message' => 'Bulk role assignment completed',
                'data' => $result,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error bulk assigning roles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk assign roles',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Bulk remove role from users
     */
    public function bulkRemove(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'required|uuid|exists:users,id',
                'role_id' => 'required|uuid|exists:roles,id',
            ]);

            $result = $this->roleService->bulkRemoveRole(
                $request->input('user_ids'),
                $request->input('role_id')
            );

            return response()->json([
                'success' => true,
                'message' => 'Bulk role removal completed',
                'data' => $result,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error bulk removing roles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk remove roles',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Bulk assign permissions to role
     */
    public function bulkAssignPermissions(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'permission_ids' => 'required|array|min:1',
                'permission_ids.*' => 'required|uuid|exists:permissions,id',
            ]);

            $role = $this->roleService->bulkAssignPermissions($id, $request->input('permission_ids'));

            return response()->json([
                'success' => true,
                'message' => 'Bulk permissions assignment completed',
                'data' => new RoleResource($role),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error bulk assigning permissions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk assign permissions',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    // ========== LEGACY ENDPOINTS (For backward compatibility) ==========

    /**
     * Legacy: Get roles (DataTables format)
     */
    public function getRolesLegacy()
    {
        $roles = Role::all();
        return DataTables::of($roles)->make(true);
    }

    /**
     * Legacy: Get roles (JSON format) - kept for backward compatibility
     */
    public function getRoles(Request $request): JsonResponse
    {
        try {
            $roles = Role::all();

            return response()->json([
                'success' => true,
                'message' => 'Roles fetched successfully.',
                'data' => $roles
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch roles.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Legacy: Store role (old endpoint)
     */
    public function storeLegacy(Request $request): JsonResponse
    {
        try {
            $request->validate(['name' => 'required|string|unique:roles']);

            $roleName = $request->name;

            $role = Role::create([
                'name'=> $roleName,
                'guard_name' => 'web',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully.',
                'data' => $role
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors occurred.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Legacy: Show role
     */
    public function showLegacy(Role $role, $roleId)
    {
        try {
            $role = Role::find($roleId);
            return response()->json([
                'success' => true,
                'message' => 'Role fetched successfully.',
                'data' => $role
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch role.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Legacy: Update role
     */
    public function updateLegacy(Request $request, $roleId)
    {
        try {
            $request->validate(['name' => 'required|string|unique:roles,name,' . $roleId]);

            $role = Role::find($roleId);
            $role->name = $request->name;
            $role->update([
                'name'=> $request->name,
                'guard_name' => 'web',
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully.',
                'data' => $role
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Legacy: Remove role
     */
    public function removeRole(Request $request, $roleId)
    {
        try {
            $role = Role::find($roleId);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found.',
                    'data' => null
                ], 404);
            }

            DB::table('roles')->where('id', $roleId)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully.',
                'data' => null
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Legacy: Sync permissions
     */
    public function syncPermissionsLegacy(Request $request, $roleId)
    {
        try {
            $request->validate([
                'permissions' => 'required|array',
                'permissions.*' => 'uuid|exists:permissions,id',
            ]);

            $role = Role::find($roleId);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found.',
                    'data' => null,
                ], 404);
            }

            $role->syncPermissions($request->permissions);

            return response()->json([
                'success' => true,
                'message' => 'Permissions synced successfully.',
                'data' => $role
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync permissions.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Legacy: Get role permissions (DataTables)
     */
    public function getRolePermissions(Request $request)
    {
        $roles = Role::with('permissions')->get();

        $filteredRoles = $roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                    ];
                }),
                'created_at' => $role->created_at,
            ];
        });

        return DataTables::of($filteredRoles)->make(true);
    }

    /**
     * Legacy: Remove permission from role
     */
    public function removePermissionFromRole(Request $request, $roleId)
    {
        try {
            $request->validate([
                'permission_id' => 'required|uuid|exists:permissions,id',
            ]);

            $role = Role::find($roleId);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found.'
                ], 404);
            }

            $permission = Permission::find($request->permission_id);

            if (!$permission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission not found.',
                    'data' => null
                ], 404);
            }

            $role->revokePermissionTo($permission);

            return response()->json([
                'success' => true,
                'message' => 'Permission removed from role successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove permission from role.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Legacy: Assign role to user
     */
    public function assignRoleToUser(Request $request, $userId): JsonResponse
    {
        try {
            $request->validate([
                'role_id' => 'required|uuid|exists:roles,id',
            ]);

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null
                ], 404);
            }

            $role = Role::find($request->role_id);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found.',
                    'data' => null
                ], 404);
            }

            $user->assignRole($role);

            return response()->json([
                'success' => true,
                'message' => 'Role assigned to user successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign role to user.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Legacy: Remove role from user
     */
    public function removeRoleFromUser(Request $request, $userId): JsonResponse
    {
        try {
            $request->validate([
                'role_id' => 'required|uuid|exists:roles,id',
            ]);

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null
                ], 404);
            }

            $role = Role::find($request->role_id);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found.',
                    'data' => null
                ], 404);
            }

            $user->removeRole($role);

            return response()->json([
                'success' => true,
                'message' => 'Role removed from user successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove role from user.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Legacy: Delete role and permissions
     */
    public function deleteRoleAndPermission(Request $request, $roleId): JsonResponse
    {
        $role = Role::where('id', $roleId)->first();

        if (! $role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found.',
            ], 404);
        }

        $role->permissions()->detach();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully.',
            'data'=> null
        ], 200);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::all();

        return DataTables::of($roles)
            ->make(true);
    }

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

    public function store(Request $request): JsonResponse
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

    public function show(Role $role, $roleId)
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

    public function update(Request $request, $roleId)
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

    public function syncPermissions(Request $request, $roleId)
    {
        try {
            $request->validate([
                'permissions' => 'required|array',
                'permissions.*' => 'integer|exists:permissions,id',
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

    public function getRolePermissions(Request $request)
    {
        $roles = Role::with('permissions')->get();

        // Map roles to only include id and name, and permissions as well
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

    public function removePermissionFromRole(Request $request, $roleId)
    {
        try {
            $request->validate([
                'permission_id' => 'required|integer|exists:permissions,id',
            ]);

            $role = Role::findById($roleId);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found.'
                ], 404);
            }

            $permission = Permission::findById($request->permission_id);

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

    public function assignRoleToUser(Request $request, $userId): JsonResponse
    {
        try {
            $request->validate([
                'role_id' => 'required|integer|exists:roles,id',
            ]);

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null
                ], 404);
            }

            $role = Role::findById($request->role_id);

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

    public function removeRoleFromUser(Request $request, $userId): JsonResponse
    {
        try {
            $request->validate([
                'role_id' => 'required|integer|exists:roles,id',
            ]);

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null
                ], 404);
            }

            $role = Role::findById($request->role_id);

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

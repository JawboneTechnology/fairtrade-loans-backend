<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    // Get all Permissions
    public function index(): JsonResponse
    {
        try {
            $permissions = Permission::all();

            return response()->json([
                'success' => true,
                'message' => 'Permissions fetched successfully.',
                'data' => $permissions
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch permissions.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Create Permission
    public function storePermission(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:permissions',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors occurred.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $permissionName = $request->name;

        $permission = Permission::create([
            'name'=> $permissionName,
            'guard_name' => 'web',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully.',
            'data' => $permission
        ], 201);
    }

    // Get all Permissions
    public function getPermissions(Request $request)
    {
        $permissions = Permission::with('roles')->get();

        return DataTables::of($permissions)
            ->addColumn('role_name', function ($permission) {
                return $permission->roles->pluck('name')->implode(', ');
            })
            ->make(true);
    }

    // Get a single Permission
    public function getSinglePermission(Request $request, $permissionId): JsonResponse
    {
        $permission = Permission::where('id', $permissionId)->first();

        return response()->json([
            'success' => true,
            'message' => 'Permission fetched successfully.',
            'data' => $permission
        ], 200);
    }

    // Update Permission
    public function updatePermission(Request $request, $permissionId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors occurred.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $permission = Permission::where('id', $permissionId)->first();
        $permission->name = $request->name;
        $permission->update([
            'name'=> $request->name,
            'guard_name' => 'web',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission updated successfully.',
            'data' => $permission
        ], 200);
    }

    // Delete Permission
    public function deletePermission(Request $request, $permissionId): JsonResponse
    {
        $permission = Permission::where('id', $permissionId)->first();

        if (! $permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found.',
            ], 404);
        }

        DB::table('permissions')->where('id', $permissionId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully.',
            'data'=> null
        ], 200);
    }

    // Delete Role & Permissions
    public function destroyPermission(Request $request, $permissionId): JsonResponse
    {
        $permission = Permission::where('id', $permissionId)->first();

        if (! $permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found.',
            ], 404);
        }

        $permission->roles()->detach();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully.',
            'data'=> null
        ], 200);
    }
}

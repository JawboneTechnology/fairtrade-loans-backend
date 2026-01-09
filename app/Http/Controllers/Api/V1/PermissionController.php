<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Services\PermissionService;
use App\Http\Resources\PermissionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use App\Models\Permission;

class PermissionController extends Controller
{
    protected PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    // ========== NEW ENDPOINTS (Standardized) ==========

    /**
     * Get all permissions (new standardized endpoint)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->input('search'),
                'group' => $request->input('group'),
                'include_roles' => $request->boolean('include_roles'),
                'per_page' => $request->input('per_page', 15),
            ];

            $result = $this->permissionService->getPermissions($filters);

            return response()->json([
                'success' => true,
                'message' => 'Permissions retrieved successfully',
                'data' => $result,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving permissions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve permissions',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get single permission
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $options = [
                'include_roles' => $request->boolean('include_roles', false),
            ];

            $permission = $this->permissionService->getPermission($id, $options);

            return response()->json([
                'success' => true,
                'message' => 'Permission retrieved successfully',
                'data' => $permission,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error retrieving permission: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve permission',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Create permission
     */
    public function store(CreatePermissionRequest $request): JsonResponse
    {
        try {
            $permission = $this->permissionService->createPermission($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Permission created successfully',
                'data' => new PermissionResource($permission),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating permission: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create permission',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update permission
     */
    public function update(UpdatePermissionRequest $request, string $id): JsonResponse
    {
        try {
            $permission = $this->permissionService->updatePermission($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Permission updated successfully',
                'data' => new PermissionResource($permission),
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
                'message' => 'Permission not found',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error updating permission: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update permission',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Delete permission
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->permissionService->deletePermission($id);

            return response()->json([
                'success' => true,
                'message' => 'Permission deleted successfully',
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
                'message' => 'Permission not found',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting permission: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete permission',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get permissions by group
     */
    public function getPermissionsByGroup(): JsonResponse
    {
        try {
            $grouped = $this->permissionService->getPermissionsByGroup();

            return response()->json([
                'success' => true,
                'message' => 'Permissions grouped retrieved successfully',
                'data' => $grouped,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving permissions by group: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve permissions by group',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get permission groups list
     */
    public function getGroups(): JsonResponse
    {
        try {
            $groups = $this->permissionService->getGroups();

            return response()->json([
                'success' => true,
                'message' => 'Permission groups retrieved successfully',
                'data' => $groups,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving permission groups: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve permission groups',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get permission statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $statistics = $this->permissionService->getStatistics();

            return response()->json([
                'success' => true,
                'message' => 'Permissions statistics retrieved successfully',
                'data' => $statistics,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving permission statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve permission statistics',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    // ========== LEGACY ENDPOINTS (For backward compatibility) ==========

    /**
     * Legacy: Create Permission
     */
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

    /**
     * Legacy: Get all Permissions (DataTables)
     */
    public function getPermissions(Request $request)
    {
        $permissions = Permission::with('roles')->get();

        return DataTables::of($permissions)
            ->addColumn('role_name', function ($permission) {
                return $permission->roles->pluck('name')->implode(', ');
            })
            ->make(true);
    }

    /**
     * Legacy: Get a single Permission
     */
    public function getSinglePermission(Request $request, $permissionId): JsonResponse
    {
        $permission = Permission::where('id', $permissionId)->first();

        return response()->json([
            'success' => true,
            'message' => 'Permission fetched successfully.',
            'data' => $permission
        ], 200);
    }

    /**
     * Legacy: Update Permission
     */
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

    /**
     * Legacy: Delete Permission
     */
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

    /**
     * Legacy: Delete Role & Permissions
     */
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

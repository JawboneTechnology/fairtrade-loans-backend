<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Log;

class PermissionService
{
    /**
     * Get all permissions with pagination and filters
     */
    public function getPermissions(array $filters = []): array
    {
        $query = Permission::query();

        // Search by name or display_name
        if (isset($filters['search']) && !empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('display_name', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Filter by group
        if (isset($filters['group']) && !empty($filters['group'])) {
            $query->where('group', $filters['group']);
        }

        // Include roles
        if (isset($filters['include_roles']) && $filters['include_roles']) {
            $query->with('roles');
        }

        $perPage = $filters['per_page'] ?? 15;
        $permissions = $query->orderBy('group')->orderBy('name')->paginate($perPage);

        $permissions->getCollection()->transform(function ($permission) {
            $permissionData = [
                'id' => $permission->id,
                'name' => $permission->name,
                'display_name' => $permission->display_name ?? $permission->name,
                'group' => $permission->group,
                'description' => $permission->description,
                'guard_name' => $permission->guard_name,
                'is_system_permission' => $permission->is_system_permission ?? false,
                'roles_count' => $permission->roles->count() ?? 0,
                'created_at' => $permission->created_at,
                'updated_at' => $permission->updated_at,
            ];

            if ($permission->relationLoaded('roles')) {
                $permissionData['roles'] = $permission->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'display_name' => $role->display_name ?? $role->name,
                    ];
                });
            }

            return $permissionData;
        });

        return [
            'current_page' => $permissions->currentPage(),
            'data' => $permissions->items(),
            'total' => $permissions->total(),
            'per_page' => $permissions->perPage(),
            'last_page' => $permissions->lastPage(),
        ];
    }

    /**
     * Get single permission
     */
    public function getPermission(string $id, array $options = []): array
    {
        $query = Permission::where('id', $id);

        $includeRoles = $options['include_roles'] ?? false;

        if ($includeRoles) {
            $query->with('roles');
        }

        $permission = $query->firstOrFail();

        $permissionData = [
            'id' => $permission->id,
            'name' => $permission->name,
            'display_name' => $permission->display_name ?? $permission->name,
            'group' => $permission->group,
            'description' => $permission->description,
            'guard_name' => $permission->guard_name,
            'is_system_permission' => $permission->is_system_permission ?? false,
            'roles_count' => $permission->roles->count(),
            'created_at' => $permission->created_at,
            'updated_at' => $permission->updated_at,
        ];

        if ($includeRoles && $permission->relationLoaded('roles')) {
            $permissionData['roles'] = $permission->roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name ?? $role->name,
                ];
            });
        }

        return $permissionData;
    }

    /**
     * Create permission
     */
    public function createPermission(array $data): Permission
    {
        return Permission::create([
            'name' => $data['name'],
            'display_name' => $data['display_name'] ?? $data['name'],
            'group' => $data['group'] ?? null,
            'description' => $data['description'] ?? null,
            'guard_name' => $data['guard_name'] ?? 'web',
            'is_system_permission' => $data['is_system_permission'] ?? false,
        ]);
    }

    /**
     * Update permission
     */
    public function updatePermission(string $id, array $data): Permission
    {
        $permission = Permission::findOrFail($id);

        // Prevent updating system permissions
        if ($permission->is_system_permission && isset($data['name'])) {
            throw new \InvalidArgumentException('Cannot update name of system permission');
        }

        $updateData = [];
        if (isset($data['display_name'])) $updateData['display_name'] = $data['display_name'];
        if (isset($data['group'])) $updateData['group'] = $data['group'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];

        if (!empty($updateData)) {
            $permission->update($updateData);
        }

        return $permission->fresh();
    }

    /**
     * Delete permission
     */
    public function deletePermission(string $id): bool
    {
        $permission = Permission::findOrFail($id);

        // Prevent deleting system permissions
        if ($permission->is_system_permission) {
            throw new \InvalidArgumentException('Cannot delete system permission');
        }

        // Check if permission is assigned to roles
        if ($permission->roles()->count() > 0) {
            throw new \InvalidArgumentException('Cannot delete permission that is assigned to roles');
        }

        return $permission->delete();
    }

    /**
     * Get permissions grouped by group
     */
    public function getPermissionsByGroup(): array
    {
        $permissions = Permission::orderBy('group')->orderBy('name')->get();

        $grouped = $permissions->groupBy('group')->map(function ($groupPermissions, $group) {
            return $groupPermissions->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name ?? $permission->name,
                    'description' => $permission->description,
                ];
            })->values();
        })->toArray();

        return $grouped;
    }

    /**
     * Get permission groups list
     */
    public function getGroups(): array
    {
        $groups = Permission::select('group')
            ->whereNotNull('group')
            ->distinct()
            ->get()
            ->map(function ($permission) {
                $count = Permission::where('group', $permission->group)->count();
                return [
                    'group' => $permission->group,
                    'display_name' => ucfirst(str_replace('_', ' ', $permission->group)),
                    'permissions_count' => $count,
                ];
            })
            ->unique('group')
            ->values()
            ->toArray();

        return $groups;
    }

    /**
     * Get permission statistics
     */
    public function getStatistics(): array
    {
        try {
            $totalPermissions = Permission::count();
            $totalRoles = Role::count();

            // Permissions by group
            $permissionsByGroup = Permission::select('group', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->whereNotNull('group')
                ->groupBy('group')
                ->get()
                ->map(function ($item) {
                    return [
                        'group' => $item->group,
                        'display_name' => ucfirst(str_replace('_', ' ', $item->group)),
                        'count' => $item->count,
                    ];
                })
                ->toArray();

            // Most assigned permissions
            $mostAssigned = Permission::withCount('roles')->get()
                ->sortByDesc('roles_count')
                ->take(10)
                ->map(function ($permission) {
                    return [
                        'permission' => $permission->name,
                        'display_name' => $permission->display_name ?? $permission->name,
                        'roles_count' => $permission->roles_count,
                    ];
                })
                ->values()
                ->toArray();

            // Unused permissions
            $unusedPermissions = Permission::doesntHave('roles')->count();

            return [
                'total_permissions' => $totalPermissions,
                'total_roles' => $totalRoles,
                'permissions_by_group' => $permissionsByGroup,
                'most_assigned_permissions' => $mostAssigned,
                'unused_permissions' => $unusedPermissions,
            ];
        } catch (\Exception $e) {
            Log::error('Error generating permission statistics: ' . $e->getMessage());
            throw new \Exception('Error generating permission statistics: ' . $e->getMessage());
        }
    }
}


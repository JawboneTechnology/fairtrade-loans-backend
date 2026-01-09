<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RoleService
{
    /**
     * Get all roles with pagination and filters
     */
    public function getRoles(array $filters = []): array
    {
        $query = Role::query();

        // Search by name or display_name
        if (isset($filters['search']) && !empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('display_name', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Include permissions
        if (isset($filters['include_permissions']) && $filters['include_permissions']) {
            $query->with('permissions');
        }

        // Include users count
        $includeUsersCount = isset($filters['include_users_count']) && $filters['include_users_count'];

        $perPage = $filters['per_page'] ?? 15;
        $roles = $query->orderBy('priority', 'desc')->orderBy('name')->paginate($perPage);

        $roles->getCollection()->transform(function ($role) use ($includeUsersCount) {
            $roleData = [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name ?? $role->name,
                'description' => $role->description,
                'guard_name' => $role->guard_name,
                'priority' => $role->priority ?? 0,
                'is_system_role' => $role->is_system_role ?? false,
                'permissions_count' => $role->permissions->count() ?? 0,
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ];

            if ($includeUsersCount) {
                $roleData['users_count'] = $role->users()->count();
            }

            if ($role->relationLoaded('permissions')) {
                $roleData['permissions'] = $role->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'display_name' => $permission->display_name ?? $permission->name,
                        'group' => $permission->group,
                    ];
                });
            }

            return $roleData;
        });

        return [
            'current_page' => $roles->currentPage(),
            'data' => $roles->items(),
            'total' => $roles->total(),
            'per_page' => $roles->perPage(),
            'last_page' => $roles->lastPage(),
        ];
    }

    /**
     * Get single role
     */
    public function getRole(string $id, array $options = []): array
    {
        $query = Role::where('id', $id);

        $includePermissions = $options['include_permissions'] ?? true;
        $includeUsers = $options['include_users'] ?? false;

        if ($includePermissions) {
            $query->with('permissions');
        }

        if ($includeUsers) {
            $query->with('users:id,email,first_name,last_name,employee_id');
        }

        $role = $query->firstOrFail();

        $roleData = [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name ?? $role->name,
            'description' => $role->description,
            'guard_name' => $role->guard_name,
            'priority' => $role->priority ?? 0,
            'is_system_role' => $role->is_system_role ?? false,
            'users_count' => $role->users()->count(),
            'permissions_count' => $role->permissions->count(),
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
        ];

        if ($includePermissions && $role->relationLoaded('permissions')) {
            $roleData['permissions'] = $role->permissions->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name ?? $permission->name,
                    'group' => $permission->group,
                    'description' => $permission->description,
                ];
            });
        }

        if ($includeUsers && $role->relationLoaded('users')) {
            $roleData['users'] = $role->users->map(function ($user) use ($role) {
                return [
                    'id' => $user->id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'employee_id' => $user->employee_id,
                    'assigned_at' => $user->pivot->created_at ?? null,
                ];
            });
        }

        return $roleData;
    }

    /**
     * Create role
     */
    public function createRole(array $data): Role
    {
        $role = Role::create([
            'name' => $data['name'],
            'display_name' => $data['display_name'] ?? $data['name'],
            'description' => $data['description'] ?? null,
            'guard_name' => $data['guard_name'] ?? 'web',
            'priority' => $data['priority'] ?? 0,
            'is_system_role' => $data['is_system_role'] ?? false,
            'metadata' => $data['metadata'] ?? null,
        ]);

        // Assign permissions if provided
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $permissions = Permission::whereIn('id', $data['permissions'])->get();
            $role->syncPermissions($permissions);
        }

        return $role->fresh(['permissions']);
    }

    /**
     * Update role
     */
    public function updateRole(string $id, array $data): Role
    {
        $role = Role::findOrFail($id);

        // Prevent updating system roles
        if ($role->is_system_role && isset($data['name'])) {
            throw new \InvalidArgumentException('Cannot update name of system role');
        }

        $updateData = [];
        if (isset($data['display_name'])) $updateData['display_name'] = $data['display_name'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['priority'])) $updateData['priority'] = $data['priority'];
        if (isset($data['metadata'])) $updateData['metadata'] = $data['metadata'];

        if (!empty($updateData)) {
            $role->update($updateData);
        }

        return $role->fresh(['permissions']);
    }

    /**
     * Delete role
     */
    public function deleteRole(string $id): bool
    {
        $role = Role::findOrFail($id);

        // Prevent deleting system roles
        if ($role->is_system_role) {
            throw new \InvalidArgumentException('Cannot delete system role');
        }

        // Check if role has users
        if ($role->users()->count() > 0) {
            throw new \InvalidArgumentException('Cannot delete role that is assigned to users');
        }

        return $role->delete();
    }

    /**
     * Sync permissions to role
     */
    public function syncPermissions(string $roleId, array $permissionIds): Role
    {
        $role = Role::findOrFail($roleId);
        $permissions = Permission::whereIn('id', $permissionIds)->get();
        $role->syncPermissions($permissions);
        return $role->fresh(['permissions']);
    }

    /**
     * Add permission to role
     */
    public function addPermission(string $roleId, string $permissionId): Role
    {
        $role = Role::findOrFail($roleId);
        $permission = Permission::findOrFail($permissionId);
        $role->givePermissionTo($permission);
        return $role->fresh(['permissions']);
    }

    /**
     * Remove permission from role
     */
    public function removePermission(string $roleId, string $permissionId): Role
    {
        $role = Role::findOrFail($roleId);
        $permission = Permission::findOrFail($permissionId);
        $role->revokePermissionTo($permission);
        return $role->fresh(['permissions']);
    }

    /**
     * Get users by role
     */
    public function getUsersByRole(string $roleId, array $filters = []): array
    {
        $role = Role::findOrFail($roleId);
        
        $query = $role->users();

        $perPage = $filters['per_page'] ?? 15;
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $users->getCollection()->transform(function ($user) use ($role) {
            return [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'employee_id' => $user->employee_id,
                'roles' => $user->roles->pluck('name')->toArray(),
                'assigned_at' => $user->pivot->created_at ?? null,
            ];
        });

        return [
            'current_page' => $users->currentPage(),
            'data' => $users->items(),
            'total' => $users->total(),
            'per_page' => $users->perPage(),
            'last_page' => $users->lastPage(),
        ];
    }

    /**
     * Get role statistics
     */
    public function getStatistics(): array
    {
        try {
            $totalRoles = Role::count();
            $totalPermissions = Permission::count();
            $totalUsers = User::count();

            // Roles distribution - use direct query to avoid Spatie relationship issues
            $rolesDistribution = Role::all()->map(function ($role) use ($totalUsers) {
                $usersCount = DB::table('model_has_roles')
                    ->where('role_id', $role->id)
                    ->where('model_type', User::class)
                    ->count();
                
                return [
                    'role' => $role->name,
                    'display_name' => $role->display_name ?? $role->name,
                    'users_count' => $usersCount,
                    'percentage' => $totalUsers > 0 ? round(($usersCount / $totalUsers) * 100, 2) : 0,
                ];
            })->toArray();

            // Most used permissions - use direct queries to avoid Spatie relationship issues
            $mostUsedPermissions = Permission::all()->map(function ($permission) {
                $rolesCount = DB::table('role_has_permissions')
                    ->where('permission_id', $permission->id)
                    ->count();
                
                $usersCount = DB::table('model_has_permissions')
                    ->where('permission_id', $permission->id)
                    ->where('model_type', User::class)
                    ->count();
                
                return [
                    'permission' => $permission->name,
                    'display_name' => $permission->display_name ?? $permission->name,
                    'roles_count' => $rolesCount,
                    'users_count' => $usersCount,
                ];
            })
                ->sortByDesc('roles_count')
                ->take(10)
                ->values()
                ->toArray();

            // Users without roles
            $usersWithoutRoles = User::doesntHave('roles')->count();

            return [
                'total_roles' => $totalRoles,
                'total_permissions' => $totalPermissions,
                'total_users' => $totalUsers,
                'roles_distribution' => $rolesDistribution,
                'most_used_permissions' => $mostUsedPermissions,
                'users_without_roles' => $usersWithoutRoles,
            ];
        } catch (\Exception $e) {
            Log::error('Error generating role statistics: ' . $e->getMessage());
            throw new \Exception('Error generating role statistics: ' . $e->getMessage());
        }
    }

    /**
     * Bulk assign role to users
     */
    public function bulkAssignRole(array $userIds, string $roleId): array
    {
        $role = Role::findOrFail($roleId);
        $users = User::whereIn('id', $userIds)->get();

        if ($users->count() !== count($userIds)) {
            throw new \InvalidArgumentException('Some user IDs were not found');
        }

        $assigned = [];
        $failed = [];

        foreach ($users as $user) {
            try {
                if (!$user->hasRole($role)) {
                    $user->assignRole($role);
                    $assigned[] = $user->id;
                }
            } catch (\Exception $e) {
                $failed[] = ['user_id' => $user->id, 'error' => $e->getMessage()];
            }
        }

        return [
            'assigned_count' => count($assigned),
            'failed_count' => count($failed),
            'assigned' => $assigned,
            'failed' => $failed,
        ];
    }

    /**
     * Bulk remove role from users
     */
    public function bulkRemoveRole(array $userIds, string $roleId): array
    {
        $role = Role::findOrFail($roleId);
        $users = User::whereIn('id', $userIds)->get();

        $removed = [];
        $failed = [];

        foreach ($users as $user) {
            try {
                if ($user->hasRole($role)) {
                    $user->removeRole($role);
                    $removed[] = $user->id;
                }
            } catch (\Exception $e) {
                $failed[] = ['user_id' => $user->id, 'error' => $e->getMessage()];
            }
        }

        return [
            'removed_count' => count($removed),
            'failed_count' => count($failed),
            'removed' => $removed,
            'failed' => $failed,
        ];
    }

    /**
     * Bulk assign permissions to role
     */
    public function bulkAssignPermissions(string $roleId, array $permissionIds): Role
    {
        $role = Role::findOrFail($roleId);
        $permissions = Permission::whereIn('id', $permissionIds)->get();

        if ($permissions->count() !== count($permissionIds)) {
            throw new \InvalidArgumentException('Some permission IDs were not found');
        }

        foreach ($permissions as $permission) {
            if (!$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        return $role->fresh(['permissions']);
    }
}


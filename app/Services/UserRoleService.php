<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Log;

class UserRoleService
{
    /**
     * Get user's roles and permissions
     */
    public function getUserRolesAndPermissions(string $userId): array
    {
        $user = User::with(['roles', 'permissions'])->findOrFail($userId);

        // Get all permissions (via roles and direct)
        $permissionsViaRoles = $user->getPermissionsViaRoles();
        $directPermissions = $user->permissions;
        $allPermissions = $user->getAllPermissions();

        $permissions = $allPermissions->map(function ($permission) use ($user, $permissionsViaRoles, $directPermissions) {
            $isDirect = $directPermissions->contains('id', $permission->id);
            $sourceRole = null;

            if (!$isDirect) {
                // Find which role granted this permission
                foreach ($user->roles as $role) {
                    if ($role->hasPermissionTo($permission)) {
                        $sourceRole = $role->name;
                        break;
                    }
                }
            }

            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'display_name' => $permission->display_name ?? $permission->name,
                'group' => $permission->group,
                'source' => $isDirect ? 'direct' : 'role',
                'source_role' => $sourceRole,
            ];
        });

        return [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
            ],
            'roles' => $user->roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name ?? $role->name,
                    'assigned_at' => $role->pivot->created_at ?? null,
                ];
            }),
            'permissions' => $permissions->values(),
            'direct_permissions' => $directPermissions->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name ?? $permission->name,
                    'group' => $permission->group,
                ];
            }),
            'permissions_via_roles' => $permissionsViaRoles->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name ?? $permission->name,
                    'group' => $permission->group,
                ];
            }),
        ];
    }

    /**
     * Assign roles to user
     */
    public function assignRoles(string $userId, array $roleIds): User
    {
        $user = User::findOrFail($userId);
        $roles = Role::whereIn('id', $roleIds)->get();

        if ($roles->count() !== count($roleIds)) {
            throw new \InvalidArgumentException('Some role IDs were not found');
        }

        foreach ($roles as $role) {
            if (!$user->hasRole($role)) {
                $user->assignRole($role);
            }
        }

        return $user->fresh(['roles']);
    }

    /**
     * Sync roles to user (replace all roles)
     */
    public function syncRoles(string $userId, array $roleIds): User
    {
        $user = User::findOrFail($userId);
        $roles = Role::whereIn('id', $roleIds)->get();

        if ($roles->count() !== count($roleIds)) {
            throw new \InvalidArgumentException('Some role IDs were not found');
        }

        $user->syncRoles($roles);

        return $user->fresh(['roles']);
    }

    /**
     * Remove role from user
     */
    public function removeRole(string $userId, string $roleId): User
    {
        $user = User::findOrFail($userId);
        $role = Role::findOrFail($roleId);

        // Prevent user from removing their own admin role
        if ($user->id === auth()->id() && $role->name === 'super-admin') {
            throw new \InvalidArgumentException('Cannot remove your own super-admin role');
        }

        $user->removeRole($role);

        return $user->fresh(['roles']);
    }

    /**
     * Assign direct permission to user
     */
    public function assignPermission(string $userId, string $permissionId): User
    {
        $user = User::findOrFail($userId);
        $permission = Permission::findOrFail($permissionId);

        if (!$user->hasDirectPermission($permission)) {
            $user->givePermissionTo($permission);
        }

        return $user->fresh(['permissions']);
    }

    /**
     * Remove direct permission from user
     */
    public function removePermission(string $userId, string $permissionId): User
    {
        $user = User::findOrFail($userId);
        $permission = Permission::findOrFail($permissionId);

        $user->revokePermissionTo($permission);

        return $user->fresh(['permissions']);
    }

    /**
     * Get current user's roles and permissions
     */
    public function getCurrentUserPermissions(): array
    {
        $user = auth()->user();
        
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        $roles = $user->roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name ?? $role->name,
            ];
        });

        $permissions = $user->getAllPermissions()->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'display_name' => $permission->display_name ?? $permission->name,
                'group' => $permission->group,
            ];
        });

        // Build "can" object for easy checking
        $can = [];
        foreach ($permissions as $permission) {
            $can[$permission['name']] = true;
        }

        return [
            'roles' => $roles,
            'permissions' => $permissions,
            'can' => $can,
        ];
    }
}


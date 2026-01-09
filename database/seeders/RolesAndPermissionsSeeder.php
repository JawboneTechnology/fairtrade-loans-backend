<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Role;
use App\Models\Permission;


class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // List of permissions to be added
        $permissions = [];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles "admin", "manager", "employee", "hr", "supervisor"
        $supaAdmin  = Role::firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'web'],
            ['display_name' => 'Super Administrator', 'is_system_role' => true, 'priority' => 100, 'description' => 'Has all permissions and system access']
        );
        $admin      = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['display_name' => 'Administrator', 'is_system_role' => true, 'priority' => 90, 'description' => 'Has most permissions except delete operations']
        );
        $manager    = Role::firstOrCreate(
            ['name' => 'manager', 'guard_name' => 'web'],
            ['display_name' => 'Manager', 'is_system_role' => true, 'priority' => 70, 'description' => 'Can create, update, and view resources']
        );
        $supervisor = Role::firstOrCreate(
            ['name' => 'supervisor', 'guard_name' => 'web'],
            ['display_name' => 'Supervisor', 'is_system_role' => true, 'priority' => 60, 'description' => 'Can create, update, and view resources']
        );
        $hr         = Role::firstOrCreate(
            ['name' => 'hr', 'guard_name' => 'web'],
            ['display_name' => 'Human Resources', 'is_system_role' => true, 'priority' => 50, 'description' => 'Can create and view resources']
        );
        $employee   = Role::firstOrCreate(
            ['name' => 'employee', 'guard_name' => 'web'],
            ['display_name' => 'Employee', 'is_system_role' => true, 'priority' => 10, 'description' => 'Limited permissions for employees']
        );
        
        // Check if employer role exists
        $employer = Role::firstOrCreate(
            ['name' => 'employer', 'guard_name' => 'web'],
            ['display_name' => 'Employer', 'is_system_role' => true, 'priority' => 80, 'description' => 'Employer-specific permissions']
        );

        // Assign all permissions to super-admin
        $supaAdmin->givePermissionTo(Permission::all());

        // Assign all permissions except delete permissions to admin
        $adminPermissions = Permission::whereNotIn('name', [
            // Delete permissions
        ])->get();

        $admin->givePermissionTo($adminPermissions);

        // Assign create, update, and view permissions to manager
        $managerPermissions = Permission::whereIn('name', [
            // Create, update, and view permissions
        ])->get();

        $manager->givePermissionTo($managerPermissions);

        // Assign create, update, and view permissions to supervisor
        $supervisorPermissions = Permission::whereIn('name', [
            // Create, update, and view permissions
        ])->get();
        $supervisor->givePermissionTo($supervisorPermissions);

        // Assign create and view permissions to hr
        $hrPermissions = Permission::whereIn('name', [
            // Create, and view permissions
        ])->get();
        $hr->givePermissionTo($hrPermissions);

        // Assign create and view permissions to employee
        $employeePermissions = Permission::whereNotIn('name', [
            // Create, and view permissions
        ])->get();
        $employee->givePermissionTo($employeePermissions);
    }
}

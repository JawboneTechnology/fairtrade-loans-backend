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
        $supaAdmin  =       Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $admin      =       Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $manager    =       Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $supervisor =       Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        $hr         =       Role::firstOrCreate(['name' => 'hr', 'guard_name' => 'web']);
        $employee   =       Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);

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

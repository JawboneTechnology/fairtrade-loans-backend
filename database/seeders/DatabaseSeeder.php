<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Run all seeder
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdministratorSeeder::class,
            GenerateLoanTypesSeeder::class,
            NotificationSeeder::class,
            EmployeeSeeder::class,
            GrantTypesSeeder::class,
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        // Prevent UserRegistered event from firing
        Event::fake();

        try {
            DB::beginTransaction();

            $password = 'Welcome2day';
            $faker = \Faker\Factory::create();

            // Get the current highest employee number
            $lastEmployee = User::whereNotNull('employee_id')
                ->orderByRaw('CAST(SUBSTRING(employee_id, 9) AS UNSIGNED) DESC')
                ->first();

            $lastNumber = $lastEmployee ? (int) substr($lastEmployee->employee_id, -4) : 1000;

            for ($i = 1; $i <= 20; $i++) {
                $nextNumber = $lastNumber + $i;
                $employmentNumber = sprintf('EMP-%s%04d', now()->year, $nextNumber);

                $userData = [
                    "first_name"            => $faker->firstName,
                    "middle_name"           => $faker->lastName,
                    "last_name"             => $faker->lastName,
                    "phone_number"          => $faker->unique()->numerify('07########'),
                    "address"               => $faker->address,
                    "dob"                   => $faker->date('Y-m-d', '-30 years'),
                    "gender"                => $faker->randomElement(['male', 'female']),
                    "email"                 => $faker->unique()->safeEmail,
                    "national_id"           => $faker->unique()->numerify('##########'),
                    "salary"                => $faker->numberBetween(30000, 200000),
                    "passport_image"        => 'https://via.placeholder.com/150',
                    "loan_limit"            => $faker->numberBetween(100000, 500000),
                    "years_of_employment"   => $faker->numberBetween(1, 10),
                    "email_verified_at"     => now(),
                    "employee_id"           => $employmentNumber,
                    "password"              => Hash::make($password),
                ];

                $userData['id'] = (string) Str::uuid();
                $user = User::create($userData);
                $user->assignRole('employee');
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception("Error generating employees: " . $e->getMessage());
        }
    }
}

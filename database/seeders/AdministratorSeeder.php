<?php

namespace Database\Seeders;

use App\Models\User;
use App\Events\UserRegistered;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;

class AdministratorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            DB::beginTransaction();

            $employmentNumber = self::generate();
            $password = 'Welcome2day';

            $user = User::create([
                "first_name"            => 'Dennis',
                "middle_name"           => 'Otieno',
                "last_name"             => 'Otieno',
                "phone_number"          => '0725134449',
                "address"               => 'Donholm, Nairobi Kenya',
                "dob"                   => '1990-12-31',
                "gender"                => 'male',
                "email"                 => 'otienodennis29@gmail.com',
                "national_id"           => '0123456789',
                "salary"                => '1000000',
                "passport_image"        => 'http://127.0.0.1:8000/uploads/system_images/thumbnails/1744378094-600x600-dennis-otienojpeg.jpeg',
                "loan_limit"            => '10000000',
                "years_of_employment"   => "3",
                "email_verified_at"     => now(),
                "employee_id"           => $employmentNumber,
                "password"              => Hash::make($password),
            ]);

            $user->assignRole('super-admin');
            $user['token'] = $user->createToken('auth_token')->plainTextToken;

            Event::dispatch(new UserRegistered($user, $password));

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception("Error generating employment number: " . $e->getMessage());
        }
    }

    private static function generate(): string
    {
        $year = now()->year;

        $lastEmployee = User::whereNotNull('employee_id')
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        if (!$lastEmployee) {
            return sprintf('EMP-%s%04d', $year, 1001);
        }

        $nextNumber = 1;
        if ($lastEmployee) {
            $lastNumber = (int) substr($lastEmployee->employee_id, -4);
            $nextNumber = $lastNumber + 1;
        }

        return sprintf('EMP-%s%04d', $year, $nextNumber);
    }
}

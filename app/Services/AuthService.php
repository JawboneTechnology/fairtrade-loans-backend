<?php

namespace App\Services;

use App\Models\User;
use App\Models\PasswordReset;
use App\Events\UserRegistered;
use App\Jobs\SendResetCodeToUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;
use App\Http\Resources\EmployeeSignInResource;

class AuthService
{
    public function registerAdmin($data)
    {
        try {
            DB::beginTransaction();

            $employmentNumber = self::generate();
            $password = $data['password'];

            $user = User::create([
                "first_name"   => $data['first_name'],
                "last_name"    => $data['last_name'],
                "phone_number" => $data['phone_number'],
                "address"      => $data['address'],
                "dob"          => $data['dob'],
                "gender"       => $data['gender'],
                "email"        => $data['email'],
                "employee_id"  => $employmentNumber,
                "password"     => Hash::make($password),
            ]);

            $user->assignRole($data['role']);
            $user['token'] = $user->createToken('auth_token')->plainTextToken;

            Event::dispatch(new UserRegistered($user, $password));

            DB::commit();

            return $user;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception("Error generating employment number: " . $e->getMessage());
        }
    }

    public function handleExternalRegister(array $data)
    {
        try {
            DB::beginTransaction();

            $employmentNumber = self::generate();
            $password = $data['password'];

            $user = User::query()->create([
                "first_name"            => $data['first_name'],
                "middle_name"           => $data['middle_name'],
                "last_name"             => $data['last_name'],
                "phone_number"          => $data['phone_number'],
                "address"               => $data['address'],
                "dob"                   => $data['dob'],
                "gender"                => $data['gender'],
                "email"                 => $data['email'],
                "salary"                => $data['salary'],
                "national_id"           => $data['national_id'],
                "passport_image"        => $data['passport_image'],
                "employee_id"           => $employmentNumber,
                "old_employee_id"       => $data['old_employee_id'],
                "years_of_employment"   => $data['years_of_employment'],
                "password"              => Hash::make($password),
            ]);

            $user->assignRole('employee');
            $user['token'] = $user->createToken('auth_token')->plainTextToken;

            Event::dispatch(new UserRegistered($user, $password));

            DB::commit();

            return $user;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception("Error registering new user: " . $e->getMessage());
        }
    }

    public function loginSuperAdmin(array $data, string $roleName): array
    {
        try {
            $user = Auth::user();
            $user->role = $roleName;

            $data = $this->verifyUserAccount($user);

            return $data;
        } catch (\Exception $e) {
            throw new \Exception("Error logging in user: " . $e->getMessage());
        }
    }

    public function loginAdmin(array $data, string $roleName): array
    {
        try {
            $user = Auth::user();
            $user->role = $roleName;

            if (!$user->email_verified_at) {
                throw new \Exception("Account is not verified.");
            }

            return [
                'user' => new EmployeeSignInResource($user),
                'token' => $user->createToken('auth_token')->plainTextToken,
            ];
        } catch (\Exception $e) {
            throw new \Exception("Error logging in user: " . $e->getMessage());
        }
    }

    public function verifyUserAccount(User $user, string|null $code = null): array
    {
        try {
            if ($code) {
                $this->verifyResetCode($user, $code);
            }

            $requiredFields = [
                'first_name',
                'last_name',
                'phone_number',
                'address',
                'dob',
                'passport_image',
                'gender',
                'email',
                'years_of_employment',
                'employee_id',
                'national_id',
                'salary',
                'loan_limit',
            ];

            $isProfileComplete = true;
            foreach ($requiredFields as $field) {
                if (empty($user->$field)) {
                    $isProfileComplete = false;
                    break;
                }
            }

            return [
                'user' => new EmployeeSignInResource($user),
                'is_profile_complete' => $isProfileComplete,
                'token' =>  $isProfileComplete ? $user->createToken('auth_token')->plainTextToken : null,
            ];
        }  catch (\Exception $e) {
            throw new \Exception("Error verifying account: " . $e->getMessage());
        }
    }

    public function forgetPassword(string $email): void
    {
        try {
            DB::beginTransaction();

            $resetCode = self::generateUniqueSixDigitNumber();

            self::saveResetToken($resetCode, $email);

            SendResetCodeToUser::dispatch($resetCode, $email);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception("Error logging in user: " . $e->getMessage() . ' Traces: ' . $e->getTraceAsString());
        }
    }

    public function verifyResetCode(User $user, string $code): bool
    {
        try {
            $resetData = PasswordReset::where("email", $user->email)->first();

            if (!$resetData) {
                Log::error("=== NO RESET CODE FOUND ===");
                Log::error("Email: " . $user->email);
                throw new \Exception("No reset code found for this email.");
            }

            if ($resetData->reset_code !== (string) $code) {
                Log::error("=== INCORRECT RESET CODE PROVIDED ===");
                Log::error("Email: " . $user->email);
                throw new \Exception("The reset code is incorrect.");
            }

            if ($resetData->expires_at < now()) {
                Log::error("=== EXPIRED RESET CODE PROVIDED ===");
                Log::error("Email: " . $user->email);
                throw new \Exception("The reset code has expired.");
            }

            return true;
        } catch (\Exception $e) {
            Log::error("=== ERROR VERIFYING RESET CODE ===");
            Log::error("Error: " . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
            throw $e;
        }
    }

    public function updateUserPassword(User $user, string $password): void
    {
        try {
            DB::beginTransaction();

            $resetToken = PasswordReset::where('email', $user->email)->first();

            if (!$resetToken) {
                throw new \Exception("Unable to change password.");
            }

            if ($resetToken->expires_at < now()) {
                throw new \Exception("Expired reset code. Please try again with another reset code.");
            }

            $user->update([
                "password" => Hash::make($password),
                "updated_at" => now()
            ]);

            $resetToken->delete();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception("Error updating password: " . $e->getMessage() . ' Traces: ' . $e->getTraceAsString());
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

    private static function generateUniqueSixDigitNumber(): int
    {
        do {
            $number = mt_rand(100000, 999999);
        } while (PasswordReset::where('reset_code', $number)->exists());

        return $number;
    }

    private static function saveResetToken(string $resetCode, string $email): void
    {
        try {
            $resetToken = PasswordReset::where('email', $email)->first();

            if (!$resetToken) {
                PasswordReset::create([
                    "reset_code" => $resetCode,
                    "email"      => $email,
                    "expires_at" => now()->addMinutes(30),
                ]);
            } else {
                $resetToken->update([
                    "reset_code" => $resetCode,
                    "expires_at" => now()->addMinutes(30),
                    "updated_at" => now(),
                ]);
            }
        } catch (\Exception $e) {
            throw new \Exception("Error saving reset code: " . $e->getMessage() . ' Traces: ' . $e->getTraceAsString());
        }
    }

    public function changeUserPassword(User $user, array $request): void
    {
        try {
            DB::beginTransaction();

            if (!Hash::check($request['old_password'], $user->getAuthPassword())) {
                throw new \Exception("Incorrect password provided.");
            }

            if (!$this->verifyResetCode($user, $request['otp_code'])) {
                throw new \Exception("The reset code is incorrect.");
            }

            $user->update([
                "password" => Hash::make($request['password']),
                "updated_at" => now()
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception("Error updating password: " . $e->getMessage());
        }
    }
}

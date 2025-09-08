<?php

namespace App\Services;

use App\Jobs\SendResetCodeToUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\PasswordReset;

class PasswordResetService
{
    public function forgotPassword(User $user): void
    {
        // Generate 8-digit reset code
        $resetCode = self::generateUniqueSixDigitNumber();

        // Check if user has a password reset record
        $existingReset = DB::table('password_resets')->where('email', $user->email)->first();

        if ($existingReset) {
            // Update the existing record
            DB::table('password_resets')->where('email', $user->email)->update([
                'reset_code' => $resetCode,
                'expires_at' => now()->addMinutes(30),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            // Create a new record
            DB::table('password_resets')->insert([
                'email' => $user->email,
                'reset_code' => $resetCode,
                'expires_at' => now()->addMinutes(30),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Dispatch the job to send the email
        SendResetCodeToUser::dispatch($resetCode, $user->email);
    }

    public function verifyResetCode($user): void
    {
        $reset = PasswordReset::where('email', $user->email)->first();

        if ($user->email !== $reset->email || $user->reset_code !== $reset->reset_code) {
            throw new \Exception('Invalid reset code');
        }

        if (now() > $reset->expires_at) {
            throw new \Exception('Reset code has expired');
        }
    }

    public function resetPassword(User $user, array $data): void
    {
        $reset = PasswordReset::where('email', $data['email'])->where('reset_code', $data['reset_code'])->first();

        if (!$reset) {
            throw new \Exception('Invalid reset code');
        }

        if (now() > $reset->expires_at) {
            throw new \Exception('Reset code has expired');
        }

        $user->password = Hash::make($data['password']);
        $user->save();
    }

    private static function generateUniqueSixDigitNumber(): int
    {
        do {
            $number = mt_rand(100000, 999999);
        } while (PasswordReset::where('reset_code', $number)->exists());

        return $number;
    }
}

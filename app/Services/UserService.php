<?php

namespace App\Services;

use App\Models\PasswordReset;
use Yajra\DataTables\DataTables;
use App\Models\User;
use App\Jobs\SendNewUserEmail;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use App\Notifications\newPasswordNotification;
use \Illuminate\Database\Eloquent\Collection;

class UserService
{
    public function getUsers(): \Illuminate\Http\JsonResponse
    {
        $users = User::with('roles')->get();
        return DataTables::of($users)
            ->addColumn('role_name', function ($user) {
                return optional($user->roles->first())->name;
            })
            ->make(true);
    }

    public function deleteUser($user): bool
    {
        if ($user->delete()) {
            return true;
        } else {
            return false;
        }
    }

    public function createUser($data)
    {
        try {
            DB::beginTransaction();

            $password = $this->generateUniquePassword();
            $userId = Auth::id();
            $employmentNumber = self::generateUniqueSixDigitNumber();

            $user = User::create([
                'created_by' => $userId,
                'employee_id' => $employmentNumber,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name']?? null,
                'last_name' => $data['last_name'],
                'phone_number' => $data['phone_number'],
                'address' => $data['address'],
                'dob' => $data['dob'],
                'salary' => $data['salary'],
                'gender' => $data['gender'],
                'email' => $data['email'],
                'password' => bcrypt($password)
            ]);

            // Assign the role to the user
            $role = Role::where('name', $data['role'])->first();

            if (!$role) {
                throw new \Exception('Role not found');
            }

            $user->assignRole($role->name);

            // Create a verification url
            $verificationUrl = "http://127.0.0.1:8000/api/v1/verify-account?email={$user->email}";

            // Dispatch the job to send the email to the user
            SendNewUserEmail::dispatch($user, $password, $verificationUrl);

            DB::commit();

            return $user->load('roles') ?? $user;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error occurred while trying to register new user' . $e->getMessage());
        }
    }

    private function generateUniquePassword($length = 12): string
    {
        $upperCase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowerCase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $specialChars = '!@#$%^&*()-_=+[]{}<>?';

        $allChars = $upperCase . $lowerCase . $numbers . $specialChars;

        $shuffledChars = str_shuffle($allChars);

        $password = '';

        $password .= $upperCase[rand(0, strlen($upperCase) - 1)];
        $password .= $lowerCase[rand(0, strlen($lowerCase) - 1)];
        $password .= $numbers[rand(0, strlen($numbers) - 1)];
        $password .= $specialChars[rand(0, strlen($specialChars) - 1)];

        for ($i = 4; $i < $length; $i++) {
            $password .= $shuffledChars[rand(0, strlen($shuffledChars) - 1)];
        }

        return str_shuffle($password);
    }

    public function updateUser(User $user, array $data): User
    {
        User::where('id', $user->id)->update([
            'name'=> $data['name'],
        ]);

        // Update the role if provided
        if (isset($data['role'])) {
            $role = Role::where('name', $data['role'])->first();

            if (!$role) {
                throw new \Exception('Role not found');
            }

            $user->roles()->detach();
            $user->assignRole($role->name);
        }

        return $user->load('roles')?? $user;
    }

    public function setEmployeeSalary(User $user, $data): User
    {
        if ($user->salary && $user->email_verified_at) {
            throw new \Exception('Salary already set for this user and account already verified');
        }

        $user->salary = $data['salary'];
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private static function generateUniqueSixDigitNumber(): int
    {
        do {
            $number = mt_rand(100000, 999999);
        } while (PasswordReset::where('reset_code', $number)->exists());

        return $number;
    }

    public function searchSystemUsers(string $query, int $offset = 0, int $limit = 10): Collection
    {
        $loggedInUser = Auth::user();

        $users = User::with('roles')
            ->whereHas('roles', function ($q) {
                $q->where('name', 'employee');
            })
            ->where('id', '!=', $loggedInUser->id)
            ->when($query, function ($q) use ($query) {
                $q->where(function ($q) use ($query) {
                    $q->where('first_name', 'like', "%{$query}%")
                        ->orWhere('middle_name', 'like', "%{$query}%")
                        ->orWhere('last_name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('phone_number', 'like', "%{$query}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->select('id', 'first_name', 'middle_name', 'last_name', 'email', 'employee_id')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $users;
    }

    public function getEmployeeDetails(User $user): User
    {
        try {
            $user = $user->load('roles', 'loans')
                ->loadCount([
                    'loans as total_loans_count',
                    'loans as pending_loans_count' => fn($q) => $q->where('loan_status', 'pending'),
                    'loans as processing_loans_count' => fn($q) => $q->where('loan_status', 'processing'),
                    'loans as approved_loans_count' => fn($q) => $q->where('loan_status', 'approved'),
                    'loans as rejected_loans_count' => fn($q) => $q->where('loan_status', 'rejected'),
                    'loans as completed_loans_count' => fn($q) => $q->where('loan_status', 'completed'),
                    'loans as repaid_loans_count' => fn($q) => $q->where('loan_status', 'repaid'),
                    'loans as defaulted_loans_count' => fn($q) => $q->where('loan_status', 'defaulted'),
                    'loans as canceled_loans_count' => fn($q) => $q->where('loan_status', 'canceled'),
                ]);

            unset($user['loans']);

            return $user;
        } catch (\Exception $e) {
            throw new \Exception('Error occurred while trying to get user details: ' . $e->getMessage());
        }
    }
}

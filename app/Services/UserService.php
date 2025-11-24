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
        try {
            $draw = request()->input('draw', 1);
            $start = request()->input('start', 0);
            $length = request()->input('length', 10);
            $searchValue = request()->input('search.value', '');
            $orderColumnIndex = request()->input('order.0.column', 0);
            $orderDirection = request()->input('order.0.dir', 'desc');

            // Column mapping for sorting
            $columns = [
                0 => 'id',
                1 => 'employee_id',
                2 => 'first_name',
                3 => 'middle_name',
                4 => 'last_name',
                5 => 'email',
                6 => 'phone_number',
                7 => 'address',
                8 => 'dob',
                9 => 'salary',
                10 => 'gender',
                11 => 'loan_limit',
                12 => 'email_verified_at',
                13 => 'created_at',
                14 => 'updated_at',
            ];

            $orderColumn = $columns[$orderColumnIndex] ?? 'created_at';

            $query = User::with('roles');

            // Apply global search if provided
            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('first_name', 'like', "%{$searchValue}%")
                        ->orWhere('last_name', 'like', "%{$searchValue}%")
                        ->orWhere('email', 'like', "%{$searchValue}%")
                        ->orWhere('phone_number', 'like', "%{$searchValue}%")
                        ->orWhere('employee_id', 'like', "%{$searchValue}%");
                });
            }

            $totalRecords = User::count();
            $filteredRecords = $query->count();

            // Apply sorting
            $query->orderBy($orderColumn, $orderDirection);

            $users = $query->skip($start)
                ->take($length)
                ->get()
                ->map(function ($user) {
                    return [
                        'id'            => $user->id,
                        'employee_id'   => $user->employee_id,
                        'first_name'    => $user->first_name,
                        'middle_name'   => $user->middle_name,
                        'last_name'     => $user->last_name,
                        'email'         => $user->email,
                        'phone_number'  => $user->phone_number,
                        'address'       => $user->address,
                        'dob'           => $user->dob,
                        'salary'        => $user->salary,
                        'gender'        => $user->gender,
                        'loan_limit'    => $user->loan_limit,
                        'email_verified_at' => $user->email_verified_at,
                        'created_at'    => $user->created_at,
                        'updated_at'    => $user->updated_at,
                        'role_name'     => optional($user->roles->first())->name,
                        'roles'         => $user->roles,
                    ];
                });

            return response()->json([
                'draw' => (int) $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'draw' => (int) request()->input('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Exception Message: ' . $e->getMessage(),
            ], 500);
        }
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
                'created_by'    => $userId,
                'employee_id'   => $employmentNumber,
                'first_name'    => $data['first_name'],
                'middle_name'   => $data['middle_name']?? null,
                'last_name'     => $data['last_name'],
                'phone_number'  => $data['phone_number'],
                'address'       => $data['address'],
                'dob'           => $data['dob'],
                'salary'        => $data['salary'],
                'gender'        => $data['gender'],
                'email'         => $data['email'],
                'password'      => bcrypt($password)
            ]);

            // Assign the role to the user
            $role = Role::where('name', $data['role'])->first();

            if (!$role) {
                throw new \Exception('Role not found');
            }

            $user->assignRole($role->name);

            $baseUrl = env('APP_URL');

            // Create a verification url
            $verificationUrl = "{$baseUrl}/api/v1/verify-account?email={$user->email}";

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

    /**
     * Validate if user account can be deleted
     * Performs comprehensive pre-deletion checks
     *
     * @param User $user
     * @return array Returns validation result with success status and message/data
     */
    public function validateAccountDeletion(User $user): array
    {
        // Check 1: Prevent deletion of admin and super-admin accounts
        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            return [
                'can_delete' => false,
                'status_code' => 403,
                'message' => 'Cannot delete admin or super-admin accounts. Please remove admin privileges first.',
                'data' => null
            ];
        }

        // Check 2: Pending loans
        $pendingLoans = \App\Models\Loan::where('employee_id', $user->id)
            ->where('loan_status', 'pending')
            ->count();

        if ($pendingLoans > 0) {
            return [
                'can_delete' => false,
                'status_code' => 400,
                'message' => 'Cannot delete account. User has ' . $pendingLoans . ' pending loan(s) awaiting approval.',
                'data' => [
                    'reason' => 'pending_loans',
                    'pending_loans_count' => $pendingLoans,
                    'action_required' => 'Please approve or reject all pending loans before deleting the account.'
                ]
            ];
        }

        // Check 3: Processing loans
        $processingLoans = \App\Models\Loan::where('employee_id', $user->id)
            ->where('loan_status', 'processing')
            ->count();

        if ($processingLoans > 0) {
            return [
                'can_delete' => false,
                'status_code' => 400,
                'message' => 'Cannot delete account. User has ' . $processingLoans . ' loan(s) currently being processed.',
                'data' => [
                    'reason' => 'processing_loans',
                    'processing_loans_count' => $processingLoans,
                    'action_required' => 'Please wait for loan processing to complete or cancel the loans before deleting the account.'
                ]
            ];
        }

        // Check 4: Active loans with outstanding balance
        $activeLoans = \App\Models\Loan::where('employee_id', $user->id)
            ->where('loan_status', 'approved')
            ->where('loan_balance', '>', 0)
            ->get();

        if ($activeLoans->count() > 0) {
            $totalOutstanding = $activeLoans->sum('loan_balance');
            
            return [
                'can_delete' => false,
                'status_code' => 400,
                'message' => 'Cannot delete account. User has ' . $activeLoans->count() . ' active loan(s) with outstanding balance.',
                'data' => [
                    'reason' => 'active_loans_with_balance',
                    'active_loans_count' => $activeLoans->count(),
                    'total_outstanding_balance' => number_format($totalOutstanding, 2),
                    'loans' => $activeLoans->map(function ($loan) {
                        return [
                            'loan_number' => $loan->loan_number,
                            'loan_balance' => number_format($loan->loan_balance, 2),
                            'monthly_installment' => number_format($loan->monthly_installment, 2),
                        ];
                    }),
                    'action_required' => 'All loans must be fully paid before the account can be deleted.'
                ]
            ];
        }

        // Check 5: Pending grants
        $pendingGrants = \App\Models\Grant::where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();

        if ($pendingGrants > 0) {
            return [
                'can_delete' => false,
                'status_code' => 400,
                'message' => 'Cannot delete account. User has ' . $pendingGrants . ' pending grant application(s).',
                'data' => [
                    'reason' => 'pending_grants',
                    'pending_grants_count' => $pendingGrants,
                    'action_required' => 'Please approve or reject all pending grant applications before deleting the account.'
                ]
            ];
        }

        // Check 6: Active/approved grants
        $activeGrants = \App\Models\Grant::where('user_id', $user->id)
            ->whereIn('status', ['approved', 'paid'])
            ->count();

        if ($activeGrants > 0) {
            return [
                'can_delete' => false,
                'status_code' => 400,
                'message' => 'Cannot delete account. User has ' . $activeGrants . ' active grant(s).',
                'data' => [
                    'reason' => 'active_grants',
                    'active_grants_count' => $activeGrants,
                    'action_required' => 'All grants must be completed or closed before the account can be deleted.'
                ]
            ];
        }

        // All checks passed
        return [
            'can_delete' => true,
            'status_code' => 200,
            'message' => 'Account can be deleted',
            'data' => null
        ];
    }

    /**
     * Prepare deletion data for event dispatch
     *
     * @param User $user
     * @return array
     */
    public function prepareDeletionData(User $user): array
    {
        $userName = $user->first_name . ' ' . $user->last_name;
        $deletedBy = auth()->user() ? (auth()->user()->first_name . ' ' . auth()->user()->last_name) : null;

        return [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $userName,
            'employee_id' => $user->employee_id ?? 'N/A',
            'deleted_by' => $deletedBy,
            'deletion_initiated_at' => now()->toDateTimeString()
        ];
    }

    /**
     * Bulk import employees from CSV file
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param bool $sendEmails
     * @return array
     */
    public function bulkImportEmployees($file, bool $sendEmails = true): array
    {
        $results = [
            'total' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
            'created_users' => []
        ];

        try {
            // Open and read the CSV file
            $csvData = array_map('str_getcsv', file($file->getRealPath()));
            
            // Get headers from first row
            $headers = array_map('trim', $csvData[0]);
            
            // Remove header row
            unset($csvData[0]);

            // Validate required headers
            $requiredHeaders = ['first_name', 'last_name', 'email', 'phone_number', 'address', 'dob', 'salary', 'gender', 'role'];
            $missingHeaders = array_diff($requiredHeaders, $headers);
            
            if (!empty($missingHeaders)) {
                throw new \Exception('Missing required headers: ' . implode(', ', $missingHeaders));
            }

            $results['total'] = count($csvData);
            $baseUrl = env('APP_URL');

            // Process each row
            foreach ($csvData as $rowIndex => $row) {
                $actualRowNumber = $rowIndex + 1; // Adjust for 1-based indexing and header row
                
                try {
                    // Skip empty rows
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    // Map CSV row to associative array
                    $userData = array_combine($headers, $row);

                    // Validate required fields
                    foreach ($requiredHeaders as $field) {
                        if (empty($userData[$field])) {
                            throw new \Exception("Missing required field: {$field}");
                        }
                    }

                    // Check if user already exists
                    if (User::where('email', $userData['email'])->exists()) {
                        throw new \Exception("User with email {$userData['email']} already exists");
                    }

                    if (User::where('phone_number', $userData['phone_number'])->exists()) {
                        throw new \Exception("User with phone number {$userData['phone_number']} already exists");
                    }

                    // Create user using the existing createUser logic
                    DB::beginTransaction();

                    $password = $this->generateUniquePassword();
                    $userId = Auth::id();
                    $employmentNumber = self::generateUniqueSixDigitNumber();

                    $user = User::create([
                        'created_by'    => $userId,
                        'employee_id'   => $employmentNumber,
                        'first_name'    => trim($userData['first_name']),
                        'middle_name'   => isset($userData['middle_name']) ? trim($userData['middle_name']) : null,
                        'last_name'     => trim($userData['last_name']),
                        'phone_number'  => trim($userData['phone_number']),
                        'address'       => trim($userData['address']),
                        'dob'           => $userData['dob'],
                        'salary'        => $userData['salary'],
                        'gender'        => strtolower(trim($userData['gender'])),
                        'email'         => trim($userData['email']),
                        'password'      => bcrypt($password)
                    ]);

                    // Assign role
                    $role = Role::where('name', trim($userData['role']))->first();

                    if (!$role) {
                        throw new \Exception("Role '{$userData['role']}' not found");
                    }

                    $user->assignRole($role->name);

                    // Send email if enabled
                    if ($sendEmails) {
                        $verificationUrl = "{$baseUrl}/api/v1/verify-account?email={$user->email}";
                        SendNewUserEmail::dispatch($user, $password, $verificationUrl);
                    }

                    DB::commit();

                    $results['successful']++;
                    $results['created_users'][] = [
                        'row' => $actualRowNumber,
                        'employee_id' => $employmentNumber,
                        'name' => $user->first_name . ' ' . $user->last_name,
                        'email' => $user->email,
                        'role' => $role->name
                    ];

                } catch (\Exception $e) {
                    DB::rollBack();
                    
                    $results['failed']++;
                    $results['errors'][] = [
                        'row' => $actualRowNumber,
                        'data' => isset($userData) ? [
                            'email' => $userData['email'] ?? 'N/A',
                            'name' => ($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')
                        ] : 'Invalid data',
                        'error' => $e->getMessage()
                    ];
                }
            }

            return $results;

        } catch (\Exception $e) {
            throw new \Exception('Error processing CSV file: ' . $e->getMessage());
        }
    }

    /**
     * Get comprehensive employee statistics for dashboard
     *
     * @return array
     */
    public function getEmployeeStatistics(): array
    {
        try {
            // Total employees count
            $totalEmployees = User::count();
            $verifiedEmployees = User::whereNotNull('email_verified_at')->count();
            $unverifiedEmployees = User::whereNull('email_verified_at')->count();

            // Role breakdown
            $roleStats = DB::table('users')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_uuid')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->select('roles.name as role_name', DB::raw('count(*) as count'))
                ->where('model_has_roles.model_type', 'App\\Models\\User')
                ->groupBy('roles.name')
                ->get()
                ->pluck('count', 'role_name')
                ->toArray();

            // Gender breakdown
            $genderStats = User::select('gender', DB::raw('count(*) as count'))
                ->whereNotNull('gender')
                ->groupBy('gender')
                ->get()
                ->pluck('count', 'gender')
                ->toArray();

            // Salary statistics
            $salaryStats = [
                'total' => User::whereNotNull('salary')->sum('salary'),
                'average' => User::whereNotNull('salary')->avg('salary'),
                'min' => User::whereNotNull('salary')->min('salary'),
                'max' => User::whereNotNull('salary')->max('salary'),
                'employees_with_salary' => User::whereNotNull('salary')->count(),
            ];

            // Recent registrations (last 30 days)
            $recentRegistrations = User::where('created_at', '>=', now()->subDays(30))
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->limit(30)
                ->get()
                ->toArray();

            // This month vs last month
            $thisMonthCount = User::whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count();
            
            $lastMonthCount = User::whereYear('created_at', now()->subMonth()->year)
                ->whereMonth('created_at', now()->subMonth()->month)
                ->count();

            $registrationGrowth = $lastMonthCount > 0 
                ? round((($thisMonthCount - $lastMonthCount) / $lastMonthCount) * 100, 2)
                : 0;

            // Loan statistics
            $loanStats = [
                'employees_with_loans' => User::has('loans')->count(),
                'employees_without_loans' => User::doesntHave('loans')->count(),
                'total_loans' => \App\Models\Loan::count(),
                'active_loans' => \App\Models\Loan::where('loan_status', 'approved')
                    ->where('loan_balance', '>', 0)
                    ->count(),
                'pending_loans' => \App\Models\Loan::where('loan_status', 'pending')->count(),
                'completed_loans' => \App\Models\Loan::where('loan_status', 'completed')->count(),
                'total_loan_amount' => \App\Models\Loan::sum('loan_amount'),
                'total_outstanding' => \App\Models\Loan::where('loan_status', 'approved')->sum('loan_balance'),
            ];

            // Grant statistics
            $grantStats = [
                'employees_with_grants' => User::whereHas('grants')->count(),
                'total_grants' => \App\Models\Grant::count(),
                'pending_grants' => \App\Models\Grant::where('status', 'pending')->count(),
                'approved_grants' => \App\Models\Grant::where('status', 'approved')->count(),
                'paid_grants' => \App\Models\Grant::where('status', 'paid')->count(),
                'total_grant_amount' => \App\Models\Grant::whereIn('status', ['approved', 'paid'])->sum('amount'),
            ];

            // Age demographics (calculate from DOB)
            $ageGroups = User::whereNotNull('dob')
                ->get()
                ->map(function ($user) {
                    $age = \Carbon\Carbon::parse($user->dob)->age;
                    if ($age < 25) return '18-24';
                    if ($age < 35) return '25-34';
                    if ($age < 45) return '35-44';
                    if ($age < 55) return '45-54';
                    return '55+';
                })
                ->countBy()
                ->toArray();

            // Top 5 employees by salary
            $topBySalary = User::whereNotNull('salary')
                ->orderBy('salary', 'desc')
                ->limit(5)
                ->select('id', 'first_name', 'last_name', 'employee_id', 'salary', 'email')
                ->get()
                ->toArray();

            // Recent employees (last 10)
            $recentEmployees = User::with('roles')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->select('id', 'first_name', 'last_name', 'employee_id', 'email', 'email_verified_at', 'created_at')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'employee_id' => $user->employee_id,
                        'name' => $user->first_name . ' ' . $user->last_name,
                        'email' => $user->email,
                        'verified' => $user->email_verified_at !== null,
                        'role' => optional($user->roles->first())->name,
                        'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                        'days_ago' => $user->created_at->diffForHumans(),
                    ];
                })
                ->toArray();

            // Employment years distribution
            $employmentYears = User::whereNotNull('years_of_employment')
                ->select('years_of_employment', DB::raw('count(*) as count'))
                ->groupBy('years_of_employment')
                ->orderBy('years_of_employment')
                ->get()
                ->toArray();

            return [
                'overview' => [
                    'total_employees' => $totalEmployees,
                    'verified_employees' => $verifiedEmployees,
                    'unverified_employees' => $unverifiedEmployees,
                    'verification_rate' => $totalEmployees > 0 ? round(($verifiedEmployees / $totalEmployees) * 100, 2) : 0,
                ],
                'registration_trends' => [
                    'this_month' => $thisMonthCount,
                    'last_month' => $lastMonthCount,
                    'growth_percentage' => $registrationGrowth,
                    'recent_registrations' => $recentRegistrations,
                ],
                'roles' => [
                    'breakdown' => $roleStats,
                    'total_roles' => count($roleStats),
                ],
                'demographics' => [
                    'gender' => $genderStats,
                    'age_groups' => $ageGroups,
                ],
                'salary' => [
                    'total_payroll' => round($salaryStats['total'], 2),
                    'average_salary' => round($salaryStats['average'], 2),
                    'minimum_salary' => round($salaryStats['min'], 2),
                    'maximum_salary' => round($salaryStats['max'], 2),
                    'employees_with_salary' => $salaryStats['employees_with_salary'],
                ],
                'loans' => [
                    'employees_with_loans' => $loanStats['employees_with_loans'],
                    'employees_without_loans' => $loanStats['employees_without_loans'],
                    'total_loans' => $loanStats['total_loans'],
                    'active_loans' => $loanStats['active_loans'],
                    'pending_loans' => $loanStats['pending_loans'],
                    'completed_loans' => $loanStats['completed_loans'],
                    'total_loan_amount' => round($loanStats['total_loan_amount'], 2),
                    'total_outstanding' => round($loanStats['total_outstanding'], 2),
                    'loan_participation_rate' => $totalEmployees > 0 ? round(($loanStats['employees_with_loans'] / $totalEmployees) * 100, 2) : 0,
                ],
                'grants' => [
                    'employees_with_grants' => $grantStats['employees_with_grants'],
                    'total_grants' => $grantStats['total_grants'],
                    'pending_grants' => $grantStats['pending_grants'],
                    'approved_grants' => $grantStats['approved_grants'],
                    'paid_grants' => $grantStats['paid_grants'],
                    'total_grant_amount' => round($grantStats['total_grant_amount'], 2),
                ],
                'top_performers' => [
                    'by_salary' => $topBySalary,
                ],
                'recent_activity' => [
                    'recent_employees' => $recentEmployees,
                ],
                'employment' => [
                    'years_distribution' => $employmentYears,
                ],
                'generated_at' => now()->toDateTimeString(),
            ];

        } catch (\Exception $e) {
            throw new \Exception('Error generating employee statistics: ' . $e->getMessage());
        }
    }
}

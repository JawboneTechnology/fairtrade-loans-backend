<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Loan;
use App\Models\User;
use App\Events\LoanPaid;
use App\Jobs\SendSMSJob;
use App\Models\LoanType;
use App\Models\LoanLimit;
use App\Models\Guarantor;
use Illuminate\Support\Str;
use App\Models\Transaction;
use App\Events\LoanCanceled;
use App\Models\LoanDeduction;
use Yajra\DataTables\DataTables;
use App\Events\MiniStatementSent;
use App\Events\GuarantorNotified;
use Illuminate\Support\Facades\DB;
use \Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use App\Jobs\NotifyAdminNewLoanApplied;
use App\Jobs\NotifyApplicantLoanPlaced;
use App\Jobs\NotifyApplicantLoanCanceled;
use Illuminate\Database\Eloquent\Collection;

class LoanService
{
    public function calculateLoanLimit(User $employee)
    {
        DB::beginTransaction();

        try {
            $salary = $employee->salary;
            $existingLoans = Loan::where('employee_id', $employee->id)
                ->where('loan_status', '!=', 'completed')
                ->sum('loan_balance');

            $allowedInstallment = $salary * 0.3; // Allow 30% of salary for loan installments.
            $maxLoanAmount = $allowedInstallment * 12; // Assuming 12 months tenure.
            $finalLimit = max(0, $maxLoanAmount - $existingLoans);

            $employee->loan_limit = $finalLimit;
            $employee->save();

            DB::commit();

            return LoanLimit::updateOrCreate(
                ['employee_id' => $employee->id],
                ['max_loan_amount' => $finalLimit]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function applyForLoan(User $employee, array $request): void
    {
        DB::beginTransaction();

        try {
            // Check if employee has applied for a loan today
            $this->validateDailyApplicationLimit($employee);

            // Validate loan amount against the remaining limit
            $this->validateLoanAmount($employee, $request['amount']);

            // Fetch loan type and validate qualifications
            $loanType = LoanType::findOrFail($request['loan_type_id']);
            $this->validateEmployeeQualifications($employee, $loanType);

            // Calculate loan details
            $loanDetails = $this->calculateLoanDetails($request['amount'], $loanType, $request['tenure_months']);

            // Create the loan
            $loan = $this->createLoan($employee, $request, $loanType, $loanDetails);

            // Handle guarantors if required
            if ($loanType->requires_guarantors) {
                $this->handleGuarantors($loan, $request['guarantors'], $loanType, $loanDetails['total_payable'], $request['amount']);
            }

            // Notify admin and applicant
            $this->notifyAdminAndApplicant($loan);

            // Update the remaining loan limit
            $this->updateLoanLimit($employee);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function validateDailyApplicationLimit(User $employee): void
    {
        $today = now()->startOfDay();
        $tomorrow = now()->addDay()->startOfDay();

        $hasAppliedToday = Loan::where('employee_id', $employee->id)
            ->whereBetween('created_at', [$today, $tomorrow])
            ->exists();

        if ($hasAppliedToday) {
            throw new \Exception('You can only submit one loan application per day.');
        }
    }

    protected function validateLoanAmount(User $employee, float $amount): void
    {
        $loanLimit = $this->calculateLoanLimit($employee);
        $remainingLimit = $loanLimit->max_loan_amount;

        if ($amount > $remainingLimit) {
            throw new \Exception('Loan amount exceeds your remaining loan limit.');
        }
    }

    protected function validateEmployeeQualifications(User $employee, LoanType $loanType): void
    {
        $qualifications = $loanType->guarantor_qualifications;
        $backgroundCheck = new LoanBackgroundCheckService();
        $employeeCheck = $backgroundCheck->check($employee);

        if (isset($qualifications['min_credit_score']) && $employeeCheck['credit_score'] < $qualifications['min_credit_score']) {
            throw new \Exception('You do not meet the credit requirements to complete the loan application.');
        }

        if (isset($qualifications['min_employment_years']) && $employee->years_of_employment < $qualifications['min_employment_years']) {
            throw new \Exception('You do not meet the employment requirements to complete the loan application.');
        }
    }

    protected function calculateLoanDetails(float $amount, LoanType $loanType, int $tenureMonths): array
    {
        $interestRate = $loanType->interest_rate;
        $totalPayable = $amount + ($amount * $interestRate / 100);
        $monthlyInstallment = $totalPayable / $tenureMonths;

        return [
            'total_payable' => $totalPayable,
            'monthly_installment' => $monthlyInstallment,
            'interest_rate' => $interestRate,
        ];
    }

    protected function createLoan(User $employee, array $request, LoanType $loanType, array $loanDetails): Loan
    {
        $loanNumber = $this->generateLoanNumber();
        $loanStatus = $loanType->approval_type === 'automatic' ? 'processing' : 'pending';

        return Loan::create([
            'loan_number' => $loanNumber,
            'employee_id' => $employee->id,
            'loan_type_id' => $request['loan_type_id'],
            'loan_amount' => $request['amount'],
            'loan_balance' => $loanDetails['total_payable'],
            'interest_rate' => $loanDetails['interest_rate'],
            'next_due_date' => now()->addMonth(),
            'tenure_months' => $request['tenure_months'],
            'monthly_installment' => $loanDetails['monthly_installment'],
            'loan_status' => $loanStatus,
            'qualifications' => (new LoanBackgroundCheckService())->check($employee),
            'guarantors' => $request['guarantors'],
        ]);
    }

    protected function handleGuarantors(Loan $loan, array $guarantorIds, LoanType $loanType, float $totalPayable, $loanAmount): void
    {
        $loggedInUser = auth()->user();
        $requiredCount = $loanType->required_guarantors_count ?? 1;
        $notificationService = new NotificationService();

        if (count($guarantorIds) < $requiredCount) {
            throw new \Exception("This loan type requires at least {$requiredCount} guarantor(s).");
        }

        $liabilityPerGuarantor = $totalPayable / count($guarantorIds);
        $backgroundCheck = new LoanBackgroundCheckService();
        $guarantors = User::whereIn('id', $guarantorIds)->get();

        foreach ($guarantors as $guarantor) {

            $this->validateGuarantor($guarantor, $loanType, $backgroundCheck);

            $guarantorRecord = $loan->guarantors()->create([
                'status' => 'pending',
                'guarantor_id' => $guarantor->id,
                'loan_number' => $loan->loan_number,
                'guarantor_liability_amount' => round($liabilityPerGuarantor, 2),
            ]);

            $notification = $notificationService->create($guarantor, 'guarantor_request', [
                'loan_id' => $loan->id,
                'loan_number' => $loan->loan_number,
                'amount' => round($loanAmount, 2),
                'applicant_name' => $loggedInUser->first_name. ' ' .$loggedInUser->middle_name . ' ' .$loggedInUser->last_name,
                'action_url' => config('app.url').'/api/v1/guarantor/'.$guarantorRecord->id.'/respond'
            ]);

            Log::info('Notification created with ID', ['id' => $notification->id]);

            // Notify guarantor
            GuarantorNotified::dispatch($loan, $guarantor->id, $notification->id);
        }
    }

    protected function validateGuarantor(User $guarantor, LoanType $loanType, LoanBackgroundCheckService $backgroundCheck): void
    {
        $maxAllowedLoans = $loanType->required_guarantors_count;
        $guarantorName = $guarantor->first_name . ' ' . $guarantor->last_name;

        if ($guarantor->guaranteedLoans()->whereIn('status', ['pending', 'approved'])->count() > $maxAllowedLoans) {
            throw new \Exception("Guarantor (Name: {$guarantorName}) has reached the maximum number of loans they can guarantee.");
        }

        $guarantorMetrics = $backgroundCheck->check($guarantor, true);

        if ($guarantorMetrics['active_guarantees'] >= 3) {
            throw new \Exception('Guarantor has reached the maximum number of active guarantees.');
        }

        if (!$guarantorMetrics['is_qualified']) {
            throw new \Exception('Guarantor does not meet the required qualifications.');
        }
    }

    protected function notifyAdminAndApplicant(Loan $loan): void
    {
        NotifyAdminNewLoanApplied::dispatch($loan);
        NotifyApplicantLoanPlaced::dispatch($loan);
    }

    protected function updateLoanLimit(User $employee): void
    {
        $this->calculateLoanLimit($employee);
    }

    public function getCreditScore(User $user): array
    {
        $backgroundCheck = new LoanBackgroundCheckService();
        $employeeCheck = $backgroundCheck->check($user);

        return $employeeCheck;
    }

    public function processMonthlyDeductions(): JsonResponse
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
                1 => 'loan_id',
                2 => 'employee_id',
                3 => 'loan_number',
                4 => 'deduction_amount',
                5 => 'deduction_type',
                6 => 'deduction_date',
                7 => 'created_at',
                8 => 'updated_at',
            ];

            $orderColumn = $columns[$orderColumnIndex] ?? 'deduction_date';

            $query = LoanDeduction::with([
                    'loan:id,loan_number,loan_amount,loan_balance,loan_status,monthly_installment',
                    'employee:id,first_name,last_name,email,phone_number,employee_id'
                ])
                ->select([
                    'id',
                    'loan_id',
                    'employee_id',
                    'loan_number',
                    'deduction_amount',
                    'deduction_type',
                    'deduction_date',
                    'created_at',
                    'updated_at'
                ]);

            // Apply global search if provided
            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('loan_number', 'like', "%{$searchValue}%")
                        ->orWhere('deduction_amount', 'like', "%{$searchValue}%")
                        ->orWhere('deduction_type', 'like', "%{$searchValue}%")
                        ->orWhereHas('employee', function ($sq) use ($searchValue) {
                            $sq->where('first_name', 'like', "%{$searchValue}%")
                                ->orWhere('last_name', 'like', "%{$searchValue}%")
                                ->orWhere('email', 'like', "%{$searchValue}%")
                                ->orWhere('employee_id', 'like', "%{$searchValue}%");
                        })
                        ->orWhereHas('loan', function ($sq) use ($searchValue) {
                            $sq->where('loan_number', 'like', "%{$searchValue}%");
                        });
                });
            }

            $totalRecords = LoanDeduction::count();
            $filteredRecords = $query->count();

            // Apply sorting
            $query->orderBy($orderColumn, $orderDirection);

            $deductions = $query->skip($start)
                ->take($length)
                ->get()
                ->map(function ($deduction) {
                    return [
                        'id' => $deduction->id,
                        'loan_id' => $deduction->loan_id,
                        'employee_id' => $deduction->employee_id,
                        'loan_number' => $deduction->loan_number,
                        'deduction_amount' => number_format($deduction->deduction_amount, 2),
                        'deduction_type' => $deduction->deduction_type ?? 'Automatic',
                        'deduction_date' => $deduction->deduction_date ? \Carbon\Carbon::parse($deduction->deduction_date)->format('d M Y, h:i A') : null,
                        'created_at' => $deduction->created_at ? $deduction->created_at->format('d M Y, h:i A') : null,
                        'updated_at' => $deduction->updated_at ? $deduction->updated_at->format('d M Y, h:i A') : null,
                        'loan' => $deduction->loan ? [
                            'loan_number' => $deduction->loan->loan_number,
                            'loan_amount' => number_format($deduction->loan->loan_amount, 2),
                            'loan_balance' => number_format($deduction->loan->loan_balance, 2),
                            'loan_status' => $deduction->loan->loan_status,
                            'monthly_installment' => number_format($deduction->loan->monthly_installment, 2),
                        ] : null,
                        'employee' => $deduction->employee ? [
                            'employee_id' => $deduction->employee->employee_id,
                            'name' => $deduction->employee->first_name . ' ' . $deduction->employee->last_name,
                            'email' => $deduction->employee->email,
                            'phone_number' => $deduction->employee->phone_number,
                        ] : null,
                    ];
                });

            return response()->json([
                'draw' => (int) $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $deductions,
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

    public function approveLoan(string $loanId, array $data): Loan
    {
        $loan = Loan::findOrFail($loanId);
        // $userId = auth()->id();

        // Check if loan exists
        if (!$loan) {
            throw new \Exception('Loan not found. Please try again.');
        }

        // Ensure loan is still pending
        if ($loan->loan_status !== 'pending') {
            throw new \Exception('Loan already processed.');
        }

        // Update loan based on status
        if ($data['loan_status'] === 'approved') {
            $this->approve($loan, $data);
        } else {
            $this->reject($loan, $data);
        }

        return $loan;
    }

    public function saveLoanType(array $data): LoanType
    {
        return LoanType::create([
            'name' => $data['name'],
            'interest_rate' => $data['interest_rate'],
            'approval_type' => $data['approval_type'],
            'requires_guarantors' => $data['requires_guarantors'],
            'required_guarantors_count' => $data['required_guarantors_count'],
            'guarantor_qualifications' => $data['guarantor_qualifications'],
            'type' => $data['type'],
            'payment_type' => $data['payment_type'],
        ]);
    }

    private function approve(Loan $loan, array $data): void
    {
        $loan->update([
            'loan_status' => 'approved',
            'approved_amount' => $data['approved_amount'],
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'remarks' => $data['remarks'] ?? null,
        ]);

        // Deduct approved amount from employee loan limit
        $employee = $loan->employee;
        $employee->loan_limit -= $data['approved_amount'];
        $employee->save();


        // Dispatch a job to send SMS notification to the employee
        try {
            $recipientPhone = $employee->phone_number ?? null;

            if (!empty($recipientPhone)) {
                $message = "Dear {$employee->first_name}, your loan application (Loan No: {$loan->loan_number}) has been approved for KES " . number_format($data['approved_amount'], 2) . ". You will receive a notification once the money has been sent to your M-Pesa number.";

                Log::info('Dispatching SendSMSJob for loan approval', ['phone' => $recipientPhone, 'loan_id' => $loan->id]);

                // Queue the SMS job on the sms queue
                SendSMSJob::dispatch($recipientPhone, $message, $employee->id)->onQueue('sms');

                // Optional synchronous fallback for debugging
                if (env('FORCE_SEND_SMS_SYNC', false)) {
                    try {
                        app(\App\Services\SMSService::class)->sendSMS($recipientPhone, $message);
                        Log::info('Synchronous loan approval SMS sent (FORCE_SEND_SMS_SYNC enabled)', ['phone' => $recipientPhone]);
                    } catch (\Throwable $ex) {
                        Log::error('Synchronous loan approval SMS failed', ['error' => $ex->getMessage()]);
                    }
                }
            } else {
                Log::warning('Loan approval SMS not sent: no phone number for employee', ['loan_id' => $loan->id, 'employee_id' => $employee->id ?? null]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to dispatch loan approval SMS: ' . $e->getMessage(), ['loan_id' => $loan->id]);
        }
    }

    private function reject(Loan $loan, array $data): void
    {
        $loan->update([
            'loan_status' => 'rejected',
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'remarks' => $data['remarks'] ?? null,
        ]);

        // Dispatch email notification job for the rejected loan
        try {
            NotifyApplicantLoanCanceled::dispatch($loan);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch NotifyApplicantLoanCanceled job: ' . $e->getMessage(), ['loan_id' => $loan->id]);
        }

        // Send SMS to the employee informing them of rejection
        try {
            $employee = $loan->employee;
            $recipientPhone = $employee->phone_number ?? null;

            if (!empty($recipientPhone)) {
                $message = "Dear {$employee->first_name}, your loan application (Loan No: {$loan->loan_number}) has been rejected. Remarks: " . ($data['remarks'] ?? 'No remarks provided') . ".";

                Log::info('Dispatching SendSMSJob for loan rejection', ['phone' => $recipientPhone, 'loan_id' => $loan->id]);
                SendSMSJob::dispatch($recipientPhone, $message, $employee->id)->onQueue('sms');

                // Optional synchronous fallback for debugging
                if (env('FORCE_SEND_SMS_SYNC', false)) {
                    try {
                        app(\App\Services\SMSService::class)->sendSMS($recipientPhone, $message);
                        Log::info('Synchronous loan rejection SMS sent (FORCE_SEND_SMS_SYNC enabled)', ['phone' => $recipientPhone]);
                    } catch (\Throwable $ex) {
                        Log::error('Synchronous loan rejection SMS failed', ['error' => $ex->getMessage()]);
                    }
                }
            } else {
                Log::warning('Loan rejection SMS not sent: no phone number for employee', ['loan_id' => $loan->id, 'employee_id' => $employee->id ?? null]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to dispatch loan rejection SMS: ' . $e->getMessage(), ['loan_id' => $loan->id]);
        }
    }

    private function generateLoanNumber(): string
    {
        $currentYear = Carbon::now()->format('Y');

        $lastLoan = Loan::orderBy('id', 'desc')->first();

        $numericPart = 1;

        if ($lastLoan && preg_match('/FTL(\d+)\/\d{4}/', $lastLoan->loan_number, $matches)) {
            $numericPart = (int) $matches[1] + 1;
        }

        $formattedNumericPart = str_pad($numericPart, 5, '0', STR_PAD_LEFT);

        return "FTL{$formattedNumericPart}/{$currentYear}";
    }

    public function getLoanTypes(): Collection
    {
        return LoanType::all();
    }

    public function cancelAppliedLoan(Loan $loan): void
    {
        DB::beginTransaction();

        try {
            $loan->update([
                'loan_status' => 'canceled',
                'remarks' => 'Loan canceled by the applicant.',
                'updated_at' => now(),
            ]);

            LoanCanceled::dispatch($loan);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error canceling loan ID: {$loan->id} - " . $e->getMessage());
            throw $e;
        }
    }

    public function getUserLoanDetails(string $loanId): array
    {
        try {
            $userId = auth()->id();

            $loan = Loan::with([
                'loanType',
                'guarantors' => function($query) {
                    $query->select([ 'id', 'loan_id', 'guarantor_id', 'loan_number', 'guarantor_liability_amount', 'status', 'created_at', 'updated_at' ]);
                },
                'guarantors.user' => function($query) {
                    $query->select([ 'id', 'first_name', 'middle_name', 'last_name', 'phone_number', 'passport_image', 'employee_id', 'old_employee_id' ]);
                }
            ])->where('employee_id', $userId)->find($loanId);

            $guarantors = Guarantor::with(['user' => function($query) {
                $query->select([ 'id', 'first_name', 'middle_name', 'last_name', 'phone_number', 'passport_image', 'employee_id', 'old_employee_id' ]);
            }])->where('loan_id', $loanId)->select([ 'id', 'loan_id', 'guarantor_id','loan_number', 'guarantor_liability_amount', 'status', 'created_at', 'updated_at' ])->get();

            if (!$loan) {
                throw new \Exception('No loan found for user.');
            }

            unset($loan->loan_type);

            return [
                'loanType' => $loan->loanType,
                'loan' => $loan->makeHidden(['guarantors', 'loan_type']),
                'guarantors' => $guarantors,
            ];
        } catch (\Exception $e) {
            Log::error("Error getting user loan details: {$e->getMessage()}");
            throw $e;
        }
    }

    public function acceptManualPayment(int $employeeId, float $amount, string $paymentTypeId): array
    {
        DB::beginTransaction();

        try {
            $transactionReference = self::generateTransactionReference();

            $loan = Loan::where('employee_id', $employeeId)
                ->where('loan_status', 'approved')
                ->where('loan_balance', '>', 0)
                ->first();

            if ($amount <= 0) {
                throw new \Exception('Payment amount must be greater than zero.');
            }

            if ($amount > $loan->loan_balance) {
                throw new \Exception('Payment amount exceeds the remaining loan balance.');
            }

            $loan->loan_balance -= $amount;

            if ($loan->loan_balance <= 0) {
                $loan->loan_status = 'completed';
                $loan->loan_balance = 0;
            }

            $loan->save();

            $deduction = LoanDeduction::create([
                'loan_id' => $loan->id,
                'employee_id' => $employeeId,
                'deduction_amount' => $amount,
                'deduction_type' => $paymentTypeId,
                'deducted_at' => now(),
            ]);

            $transaction = Transaction::create([
                'loan_id' => $loan->id,
                'employee_id' => $employeeId,
                'amount' => $amount,
                'payment_type' => $paymentTypeId,
                'transaction_reference' => $transactionReference,
                'transaction_date' => now(),
            ]);

            Event::dispatch(new LoanPaid($loan, $deduction, $transaction));

            DB::commit();

            return [
                'deduction' => $deduction,
                'transaction' => $transaction,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private static function generateTransactionReference(): string
    {
        $maxAttempts = 5; // Maximum attempts to generate a unique reference
        $attempt = 0;

        do {
            $prefix = 'FT';
            $timestamp = now()->format('YmdHis');
            $uniqueId = Str::upper(Str::random(6));
            $referenceNumber = "{$prefix}-{$timestamp}-{$uniqueId}";

            // Check if the reference already exists in the database
            $exists = Transaction::where('transaction_reference', $referenceNumber)->exists();

            if (!$exists) {
                return $referenceNumber;
            }

            $attempt++;
        } while ($attempt < $maxAttempts);

        throw new \Exception('Failed to generate a unique transaction reference.');
    }

    public function getLoanTransactions(string $loanId): JsonResponse
    {
        $transactions = Transaction::where('loan_id', $loanId)
            ->orderBy('transaction_date', 'desc');

            return DataTables::of($transactions)->make(true);
    }

    public function generateMiniStatement(string $loanId, $canEmail): array
    {
        try {
            $loan = Loan::with(['transactions', 'deductions'])
                ->findOrFail($loanId);

            $transactions = $loan->transactions()
                ->orderBy('transaction_date', 'desc')
                ->get();

            $deductions = $loan->deductions()
                ->orderBy('deducted_at', 'desc')
                ->get();

            $statement = [
                'loan_details' => [
                    'loan_number' => $loan->loan_number,
                    'loan_amount' => $loan->loan_amount,
                    'loan_balance' => $loan->loan_balance,
                    'interest_rate' => $loan->interest_rate,
                    'tenure_months' => $loan->tenure_months,
                    'monthly_installment' => $loan->monthly_installment,
                    'next_due_date' => $loan->next_due_date,
                    'loan_status' => $loan->loan_status,
                ],
                'transactions' => $transactions->map(function ($transaction) {
                    return [
                        'transaction_date' => $transaction->transaction_date,
                        'amount' => $transaction->amount,
                        'payment_type' => $transaction->payment_type,
                        'transaction_reference' => $transaction->transaction_reference,
                    ];
                }),
                'deductions' => $deductions->map(function ($deduction) {
                    return [
                        'deduction_date' => $deduction->deducted_at,
                        'amount' => $deduction->deduction_amount,
                        'deduction_type' => $deduction->deduction_type,
                    ];
                }),
            ];

            $user = User::where('id', $loan->employee_id)->first();

            if (!$user) {
                throw new \Exception('Employee not found.');
            }

            if ($canEmail) {
                Event::dispatch(new MiniStatementSent($statement, $user));
            }

            return $statement;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function getUserPayments(User $user, int $start = 0, int $limit = 10): array
    {
        try {
            // Fetch transactions with pagination
            $transactions = Transaction::where('employee_id', $user->id)
                ->orderBy('transaction_date', 'desc')
                ->skip($start)
                ->take($limit)
                ->get();

            if ($transactions->isEmpty()) {
                return [];
            }

            // Map the transactions to the desired format
            return $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'transaction_date' => $transaction->transaction_date,
                    'amount' => $transaction->amount,
                    'payment_type' => $transaction->payment_type,
                    'transaction_reference' => $transaction->transaction_reference,
                ];
            })->toArray();

        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function getUserRecentLoan(User $user): array
    {
        try {
            // Fetch the most recent loan with deductions, transactions and loan type
            $loan = Loan::with(['deductions', 'transactions', 'loanType'])
                ->where('employee_id', $user->id)
                ->orderBy('applied_at', 'desc')
                ->first();

            if (!$loan) {
                return [];
            }

            // Calculate the total amount paid from both deductions and transactions
            $totalPaidFromDeductions = collect($loan->deductions)->sum('deduction_amount');
            $totalPaidFromTransactions = collect($loan->transactions)->sum('amount');
            $totalPaid = $totalPaidFromDeductions + $totalPaidFromTransactions;

            // Use loan_balance for accurate outstanding amount
            $outstanding = $loan->loan_balance;

            // Calculate the percentage complete based on loan amount
            $loanAmount = $loan->loan_amount > 0 ? $loan->loan_amount : 1; // Prevent division by zero
            $percentageComplete = ($totalPaid / $loanAmount) * 100;

            // Get the loan type name
            $loanTypeName = $loan->loanType->name;

            // Return the loan details with calculations
            return [
                'calculations' => [
                    'id' => $loan->id,
                    'loan_number' => $loan->loan_number,
                    'percentage_complete' => round($percentageComplete, 2), // Round to 2 decimal places
                    'outstanding_amount' => $outstanding,
                    'amount_paid' => $totalPaid,
                    'loan_amount' => $loan->loan_amount,
                    'loan_type_name' => $loanTypeName,
                    'applied_at' => $loan->applied_at,
                ],
            ];

        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function getUserAllLoansWithCalculations(User $user): array
    {
        try {
            // Fetch only active loans with deductions, transactions and loan type, ordered by most recent first
            $loans = Loan::with(['deductions', 'transactions', 'loanType'])
                ->where('employee_id', $user->id)
                // ->where('loan_status', 'active')
                ->orderBy('applied_at', 'desc')
                ->get();

            if ($loans->isEmpty()) {
                return [];
            }

            // Process each loan with calculations
            return $loans->map(function ($loan) {
                // Calculate the total amount paid from both deductions and transactions
                $totalPaidFromDeductions = collect($loan->deductions)->sum('deduction_amount');
                $totalPaidFromTransactions = collect($loan->transactions)->sum('amount');
                $totalPaid = $totalPaidFromDeductions + $totalPaidFromTransactions;

                // Use loan_balance for accurate outstanding amount
                $outstanding = $loan->loan_balance;

                // Calculate the percentage complete
                $loanAmount = $loan->loan_amount > 0 ? $loan->loan_amount : 1; // Prevent division by zero
                $percentageComplete = ($totalPaid / $loanAmount) * 100;

                // Get the loan type name
                $loanTypeName = $loan->loanType ? $loan->loanType->name : 'N/A';

                // Return the loan details with calculations
                return [
                    'id' => $loan->id,
                    'loan_number' => $loan->loan_number,
                    'percentage_complete' => round($percentageComplete, 2), // Round to 2 decimal places
                    'outstanding_amount' => $outstanding,
                    'amount_paid' => $totalPaid,
                    'loan_amount' => $loan->loan_amount,
                    'loan_type_name' => $loanTypeName,
                    'loan_status' => $loan->loan_status,
                    'applied_at' => $loan->applied_at,
                ];
            })->toArray();

        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function getUserLoans(User $user, int $start = 0, int $limit = 10)
    {
        try {

            $loans = Loan::where('employee_id', $user->id)
                ->orderBy('applied_at', 'desc')
                ->get();

            return $loans->map(function ($loan) {
                return [
                    'loan_id' => $loan->id,
                    'loan_number' => $loan->loan_number,
                    'loan_amount' => $loan->loan_amount,
                    'loan_balance' => $loan->loan_balance,
                    'interest_rate' => $loan->interest_rate,
                    'tenure_months' => $loan->tenure_months,
                    'monthly_installment' => $loan->monthly_installment,
                    'next_due_date' => $loan->next_due_date,
                    'loan_status' => $loan->loan_status,
                ];
            });

        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function getUserPersonalLoans($employeeId): JsonResponse
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
                1 => 'loan_number',
                2 => 'loan_type_id',
                3 => 'loan_amount',
                4 => 'loan_balance',
                5 => 'interest_rate',
                6 => 'tenure_months',
                7 => 'monthly_installment',
                8 => 'loan_status',
                9 => 'next_due_date',
                10 => 'approved_amount',
                11 => 'approved_at',
                12 => 'applied_at',
                13 => 'created_at',
                14 => 'updated_at',
            ];

            $orderColumn = $columns[$orderColumnIndex] ?? 'applied_at';

            $query = Loan::with(['loanType', 'employee:id,first_name,last_name,email,phone_number'])
                ->where('employee_id', $employeeId);

            // Apply global search if provided
            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('loan_number', 'like', "%{$searchValue}%")
                        ->orWhere('loan_amount', 'like', "%{$searchValue}%")
                        ->orWhere('loan_balance', 'like', "%{$searchValue}%")
                        ->orWhere('loan_status', 'like', "%{$searchValue}%")
                        ->orWhere('approved_amount', 'like', "%{$searchValue}%")
                        ->orWhereHas('loanType', function ($sq) use ($searchValue) {
                            $sq->where('name', 'like', "%{$searchValue}%");
                        });
                });
            }

            $totalRecords = Loan::where('employee_id', $employeeId)->count();
            $filteredRecords = $query->count();

            // Apply sorting
            $query->orderBy($orderColumn, $orderDirection);

            $loans = $query->skip($start)
                ->take($length)
                ->get()
                ->map(function ($loan) {
                    return [
                        'id' => $loan->id,
                        'loan_number' => $loan->loan_number,
                        'loan_type_id' => $loan->loan_type_id,
                        'loan_type_name' => optional($loan->loanType)->name,
                        'loan_amount' => $loan->loan_amount,
                        'loan_balance' => $loan->loan_balance,
                        'interest_rate' => $loan->interest_rate,
                        'tenure_months' => $loan->tenure_months,
                        'monthly_installment' => $loan->monthly_installment,
                        'loan_status' => $loan->loan_status,
                        'next_due_date' => $loan->next_due_date,
                        'approved_amount' => $loan->approved_amount,
                        'approved_by' => $loan->approved_by,
                        'approved_at' => $loan->approved_at,
                        'applied_at' => $loan->applied_at,
                        'remarks' => $loan->remarks,
                        'guarantors' => $loan->guarantors,
                        'qualifications' => $loan->qualifications,
                        'created_at' => $loan->created_at,
                        'updated_at' => $loan->updated_at,
                        'loan_type' => $loan->loanType,
                        'employee' => $loan->employee ? [
                            'id' => $loan->employee->id,
                            'name' => $loan->employee->first_name . ' ' . $loan->employee->last_name,
                            'email' => $loan->employee->email,
                            'phone_number' => $loan->employee->phone_number,
                        ] : null,
                    ];
                });

            return response()->json([
                'draw' => (int) $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $loans,
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

    public function getLoanPersonalDeductions($employeeId): JsonResponse
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
                1 => 'loan_id',
                2 => 'employee_id',
                3 => 'deduction_amount',
                4 => 'deduction_type',
                5 => 'deduction_date',
                6 => 'created_at',
                7 => 'updated_at',
            ];

            $orderColumn = $columns[$orderColumnIndex] ?? 'deduction_date';

            $query = LoanDeduction::with(['loan:id,loan_number,loan_amount,loan_balance,loan_status', 'employee:id,first_name,last_name,email,phone_number'])
                ->where('employee_id', $employeeId);

            // Apply global search if provided
            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('deduction_amount', 'like', "%{$searchValue}%")
                        ->orWhere('deduction_type', 'like', "%{$searchValue}%")
                        ->orWhereHas('loan', function ($sq) use ($searchValue) {
                            $sq->where('loan_number', 'like', "%{$searchValue}%");
                        });
                });
            }

            $totalRecords = LoanDeduction::where('employee_id', $employeeId)->count();
            $filteredRecords = $query->count();

            // Apply sorting
            $query->orderBy($orderColumn, $orderDirection);

            $deductions = $query->skip($start)
                ->take($length)
                ->get()
                ->map(function ($deduction) {
                    return [
                        'id' => $deduction->id,
                        'loan_id' => $deduction->loan_id,
                        'employee_id' => $deduction->employee_id,
                        'deduction_amount' => $deduction->deduction_amount,
                        'deduction_type' => $deduction->deduction_type,
                        'deduction_date' => $deduction->deduction_date,
                        'created_at' => $deduction->created_at,
                        'updated_at' => $deduction->updated_at,
                        'loan' => $deduction->loan ? [
                            'loan_number' => $deduction->loan->loan_number,
                            'loan_amount' => $deduction->loan->loan_amount,
                            'loan_balance' => $deduction->loan->loan_balance,
                            'loan_status' => $deduction->loan->loan_status,
                        ] : null,
                        'employee' => $deduction->employee ? [
                            'name' => $deduction->employee->first_name . ' ' . $deduction->employee->last_name,
                            'email' => $deduction->employee->email,
                            'phone_number' => $deduction->employee->phone_number,
                        ] : null,
                    ];
                });

            return response()->json([
                'draw' => (int) $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $deductions,
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

    public function getLoans(): JsonResponse
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
                1 => 'loan_number',
                2 => 'employee_id',
                3 => 'loan_type_id',
                4 => 'loan_amount',
                5 => 'loan_balance',
                6 => 'interest_rate',
                7 => 'tenure_months',
                8 => 'monthly_installment',
                9 => 'loan_status',
                10 => 'next_due_date',
                11 => 'approved_amount',
                12 => 'approved_at',
                13 => 'applied_at',
                14 => 'created_at',
                15 => 'updated_at',
            ];

            $orderColumn = $columns[$orderColumnIndex] ?? 'applied_at';

            $query = Loan::with(['loanType', 'employee:id,first_name,last_name,email,phone_number,employee_id']);

            // Apply global search if provided
            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('loan_number', 'like', "%{$searchValue}%")
                        ->orWhere('loan_amount', 'like', "%{$searchValue}%")
                        ->orWhere('loan_balance', 'like', "%{$searchValue}%")
                        ->orWhere('loan_status', 'like', "%{$searchValue}%")
                        ->orWhere('approved_amount', 'like', "%{$searchValue}%")
                        ->orWhereHas('loanType', function ($sq) use ($searchValue) {
                            $sq->where('name', 'like', "%{$searchValue}%");
                        })
                        ->orWhereHas('employee', function ($sq) use ($searchValue) {
                            $sq->where('first_name', 'like', "%{$searchValue}%")
                                ->orWhere('last_name', 'like', "%{$searchValue}%")
                                ->orWhere('email', 'like', "%{$searchValue}%")
                                ->orWhere('employee_id', 'like', "%{$searchValue}%");
                        });
                });
            }

            $totalRecords = Loan::count();
            $filteredRecords = $query->count();

            // Apply sorting
            $query->orderBy($orderColumn, $orderDirection);

            $loans = $query->skip($start)
                ->take($length)
                ->get()
                ->map(function ($loan) {
                    return [
                        'id'                  => $loan->id,
                        'loan_number'         => $loan->loan_number,
                        'employee_id'         => $loan->employee_id,
                        'loan_type_id'        => $loan->loan_type_id,
                        'loan_type_name'      => optional($loan->loanType)->name,
                        'loan_amount'         => $loan->loan_amount,
                        'loan_balance'        => $loan->loan_balance,
                        'interest_rate'       => $loan->interest_rate,
                        'tenure_months'       => $loan->tenure_months,
                        'monthly_installment' => $loan->monthly_installment,
                        'loan_status'         => $loan->loan_status,
                        'next_due_date'       => $loan->next_due_date,
                        'approved_amount'     => $loan->approved_amount,
                        'approved_by'         => $loan->approved_by,
                        'approved_at'         => $loan->approved_at,
                        'applied_at'          => $loan->applied_at,
                        'remarks'             => $loan->remarks,
                        'guarantors'          => $loan->guarantors,
                        'qualifications'      => $loan->qualifications,
                        'created_at'          => $loan->created_at,
                        'updated_at'          => $loan->updated_at,
                        'loan_type'           => $loan->loanType,
                        'employee'            => $loan->employee ? [
                            'id'            => $loan->employee->id,
                            'employee_id'   => $loan->employee->employee_id,
                            'name'          => $loan->employee->first_name . ' ' . $loan->employee->last_name,
                            'email'         => $loan->employee->email,
                            'phone_number'  => $loan->employee->phone_number,
                        ] : null,
                    ];
                });

            return response()->json([
                'draw' => (int) $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $loans,
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

    public function getUserLoanStats(): array
    {
        $userId = auth()->user()->id;
        return (new \App\Models\Loan)->getUserLoanStats($userId);
    }

    /**
     * Send installment reminders to users with active loans
     * This runs twice - once as early reminder, once as due date reminder
     */
    public function sendInstallmentReminders(): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'errors' => []];

        try {
            // Get loans that need installment reminders
            // Send reminders 7 days before due date and 1 day before due date
            $reminderDates = [
                now()->addDays(7)->startOfDay(),  // 7 days before
                now()->addDays(1)->startOfDay(),  // 1 day before
            ];

            foreach ($reminderDates as $index => $reminderDate) {
                $reminderType = $index === 0 ? 'early' : 'final';
                
                $loans = Loan::with(['employee'])
                    ->where('loan_status', 'approved')
                    ->where('loan_balance', '>', 0)
                    ->whereDate('next_due_date', '=', $reminderDate)
                    ->get();

                foreach ($loans as $loan) {
                    try {
                        $this->sendInstallmentReminderSMS($loan, $reminderType);
                        
                        // Create loan notification record
                        \App\Models\LoanNotification::create([
                            'loan_id' => $loan->id,
                            'user_id' => $loan->employee_id,
                            'notification_type' => 'installment_reminder',
                            'channel' => 'sms',
                            'phone_number' => $loan->employee->phone_number,
                            'amount' => $loan->monthly_installment,
                            'status' => 'sent',
                            'sent_at' => now(),
                            'metadata' => [
                                'reminder_type' => $reminderType,
                                'due_date' => $loan->next_due_date->toDateString(),
                                'days_until_due' => now()->diffInDays($loan->next_due_date, false),
                            ],
                        ]);
                        
                        $results['sent']++;
                    } catch (\Exception $e) {
                        $errorMessage = 'Failed to send installment reminder SMS: ' . $e->getMessage();
                        $results['errors'][] = $errorMessage;
                        
                        // Create failed notification record
                        \App\Models\LoanNotification::create([
                            'loan_id' => $loan->id,
                            'user_id' => $loan->employee_id,
                            'notification_type' => 'installment_reminder',
                            'channel' => 'sms',
                            'phone_number' => $loan->employee->phone_number ?? '',
                            'amount' => $loan->monthly_installment,
                            'status' => 'failed',
                            'failure_reason' => $errorMessage,
                            'metadata' => [
                                'reminder_type' => $reminderType,
                                'error_details' => $e->getMessage(),
                            ],
                        ]);
                        
                        Log::error('Failed to send installment reminder SMS', [
                            'loan_id' => $loan->id,
                            'loan_number' => $loan->loan_number,
                            'reminder_type' => $reminderType,
                            'error' => $e->getMessage()
                        ]);
                        $results['failed']++;
                    }
                }
            }

            Log::info('Installment reminders processing completed', $results);
            return $results;

        } catch (\Exception $e) {
            Log::error('Error in sendInstallmentReminders: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send overdue notifications to users with overdue loans
     */
    public function sendOverdueNotifications(): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'errors' => []];

        try {
            // Get overdue loans (past due date)
            $overdueLoans = Loan::with(['employee'])
                ->where('loan_status', 'approved')
                ->where('loan_balance', '>', 0)
                ->where('next_due_date', '<', now()->startOfDay())
                ->get();

            foreach ($overdueLoans as $loan) {
                try {
                    $this->sendOverdueNotificationSMS($loan);
                    
                    // Create loan notification record
                    \App\Models\LoanNotification::create([
                        'loan_id' => $loan->id,
                        'user_id' => $loan->employee_id,
                        'notification_type' => 'overdue_notification',
                        'channel' => 'sms',
                        'phone_number' => $loan->employee->phone_number,
                        'amount' => $loan->monthly_installment,
                        'status' => 'sent',
                        'sent_at' => now(),
                        'metadata' => [
                            'due_date' => $loan->next_due_date->toDateString(),
                            'days_overdue' => now()->diffInDays($loan->next_due_date, false),
                            'outstanding_balance' => $loan->loan_balance,
                        ],
                    ]);
                    
                    $results['sent']++;
                } catch (\Exception $e) {
                    $errorMessage = 'Failed to send overdue notification SMS: ' . $e->getMessage();
                    $results['errors'][] = $errorMessage;
                    
                    // Create failed notification record
                    \App\Models\LoanNotification::create([
                        'loan_id' => $loan->id,
                        'user_id' => $loan->employee_id,
                        'notification_type' => 'overdue_notification',
                        'channel' => 'sms',
                        'phone_number' => $loan->employee->phone_number ?? '',
                        'amount' => $loan->monthly_installment,
                        'status' => 'failed',
                        'failure_reason' => $errorMessage,
                        'metadata' => [
                            'error_details' => $e->getMessage(),
                            'outstanding_balance' => $loan->loan_balance,
                        ],
                    ]);
                    
                    Log::error('Failed to send overdue notification SMS', [
                        'loan_id' => $loan->id,
                        'loan_number' => $loan->loan_number,
                        'error' => $e->getMessage()
                    ]);
                    $results['failed']++;
                }
            }

            Log::info('Overdue notifications processing completed', $results);
            return $results;

        } catch (\Exception $e) {
            Log::error('Error in sendOverdueNotifications: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send installment reminder SMS to a loan holder
     */
    private function sendInstallmentReminderSMS(Loan $loan, string $reminderType): void
    {
        $employee = $loan->employee;
        
        if (empty($employee->phone_number)) {
            Log::warning('No phone number for installment reminder', [
                'loan_id' => $loan->id,
                'employee_id' => $employee->id
            ]);
            return;
        }

        $daysUntilDue = now()->diffInDays($loan->next_due_date, false);
        $employeeName = $employee->first_name;

        if ($reminderType === 'early') {
            $message = "Dear {$employeeName}, your loan installment of KES " . number_format($loan->monthly_installment, 2) . 
                      " for loan {$loan->loan_number} is due in {$daysUntilDue} days on " . 
                      $loan->next_due_date->format('M j, Y') . ". Please prepare for payment.";
        } else {
            $message = "Dear {$employeeName}, REMINDER: Your loan installment of KES " . number_format($loan->monthly_installment, 2) . 
                      " for loan {$loan->loan_number} is due tomorrow (" . 
                      $loan->next_due_date->format('M j, Y') . "). Please ensure timely payment.";
        }

        // Dispatch SMS job
        SendSMSJob::dispatch($employee->phone_number, $message, $employee->id)->onQueue('sms');

        // Optional synchronous fallback for debugging
        if (env('FORCE_SEND_SMS_SYNC', false)) {
            try {
                app(\App\Services\SMSService::class)->sendSMS($employee->phone_number, $message);
                Log::info('Synchronous installment reminder SMS sent', [
                    'phone' => $employee->phone_number,
                    'reminder_type' => $reminderType
                ]);
            } catch (\Throwable $ex) {
                Log::error('Synchronous installment reminder SMS failed', [
                    'error' => $ex->getMessage(),
                    'reminder_type' => $reminderType
                ]);
            }
        }

        Log::info('Installment reminder SMS dispatched', [
            'loan_id' => $loan->id,
            'loan_number' => $loan->loan_number,
            'phone' => $employee->phone_number,
            'reminder_type' => $reminderType,
            'due_date' => $loan->next_due_date,
            'amount' => $loan->monthly_installment
        ]);
    }

    /**
     * Send overdue notification SMS to a loan holder
     */
    private function sendOverdueNotificationSMS(Loan $loan): void
    {
        $employee = $loan->employee;
        
        if (empty($employee->phone_number)) {
            Log::warning('No phone number for overdue notification', [
                'loan_id' => $loan->id,
                'employee_id' => $employee->id
            ]);
            return;
        }

        $daysOverdue = now()->diffInDays($loan->next_due_date, false);
        $employeeName = $employee->first_name;

        $message = "Dear {$employeeName}, your loan installment of KES " . number_format($loan->monthly_installment, 2) . 
                  " for loan {$loan->loan_number} is now {$daysOverdue} days OVERDUE. " . 
                  "Outstanding balance: KES " . number_format($loan->loan_balance, 2) . 
                  ". Please pay immediately to avoid penalties.";

        // Dispatch SMS job
        SendSMSJob::dispatch($employee->phone_number, $message, $employee->id)->onQueue('sms');

        // Optional synchronous fallback for debugging
        if (env('FORCE_SEND_SMS_SYNC', false)) {
            try {
                app(\App\Services\SMSService::class)->sendSMS($employee->phone_number, $message);
                Log::info('Synchronous overdue notification SMS sent', [
                    'phone' => $employee->phone_number,
                    'days_overdue' => $daysOverdue
                ]);
            } catch (\Throwable $ex) {
                Log::error('Synchronous overdue notification SMS failed', [
                    'error' => $ex->getMessage(),
                    'days_overdue' => $daysOverdue
                ]);
            }
        }

        Log::info('Overdue notification SMS dispatched', [
            'loan_id' => $loan->id,
            'loan_number' => $loan->loan_number,
            'phone' => $employee->phone_number,
            'days_overdue' => $daysOverdue,
            'amount_due' => $loan->monthly_installment,
            'balance' => $loan->loan_balance
        ]);
    }

    /**
     * Get comprehensive loan statistics for dashboard
     *
     * @return array
     */
    public function getLoanStatistics(): array
    {
        try {
            // Total loans overview
            $totalLoans = Loan::count();
            $activeLoans = Loan::where('loan_status', 'approved')->where('loan_balance', '>', 0)->count();
            $pendingLoans = Loan::where('loan_status', 'pending')->count();
            $processingLoans = Loan::where('loan_status', 'processing')->count();
            $completedLoans = Loan::where('loan_status', 'completed')->count();
            $rejectedLoans = Loan::where('loan_status', 'rejected')->count();
            $canceledLoans = Loan::where('loan_status', 'canceled')->count();

            // Financial overview
            $totalDisbursed = Loan::whereIn('loan_status', ['approved', 'completed'])->sum('loan_amount');
            $totalOutstanding = Loan::where('loan_status', 'approved')->sum('loan_balance');
            $totalRepaid = Loan::sum('loan_amount') - Loan::sum('loan_balance');
            $averageLoanAmount = Loan::avg('loan_amount');
            $largestLoan = Loan::max('loan_amount');
            $smallestLoan = Loan::where('loan_amount', '>', 0)->min('loan_amount');

            // Repayment stats
            $totalDeductions = \App\Models\LoanDeduction::count();
            $totalDeductionAmount = \App\Models\LoanDeduction::sum('deduction_amount');
            $totalTransactions = \App\Models\Transaction::where('payment_type', 'mpesa')->count();
            $totalTransactionAmount = \App\Models\Transaction::where('payment_type', 'mpesa')->sum('amount');

            // Loan type breakdown
            $loanTypeStats = Loan::join('loan_types', 'loans.loan_type_id', '=', 'loan_types.id')
                ->select('loan_types.name as loan_type', 
                    DB::raw('count(*) as count'),
                    DB::raw('sum(loans.loan_amount) as total_amount'),
                    DB::raw('sum(loans.loan_balance) as outstanding'))
                ->groupBy('loan_types.name', 'loan_types.id')
                ->get()
                ->map(function ($item) {
                    return [
                        'loan_type' => $item->loan_type,
                        'count' => $item->count,
                        'total_amount' => round($item->total_amount, 2),
                        'outstanding' => round($item->outstanding, 2)
                    ];
                })
                ->toArray();

            // Status distribution
            $statusDistribution = [
                'pending' => $pendingLoans,
                'processing' => $processingLoans,
                'approved' => $activeLoans,
                'completed' => $completedLoans,
                'rejected' => $rejectedLoans,
                'canceled' => $canceledLoans
            ];

            // Monthly trends (last 12 months)
            $monthlyTrends = Loan::where('approved_at', '>=', now()->subMonths(12))
                ->whereNotNull('approved_at')
                ->select(
                    DB::raw('DATE_FORMAT(approved_at, "%Y-%m") as month'),
                    DB::raw('count(*) as count'),
                    DB::raw('sum(loan_amount) as total_amount')
                )
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get()
                ->toArray();

            // This month vs last month
            $thisMonthLoans = Loan::whereYear('approved_at', now()->year)
                ->whereMonth('approved_at', now()->month)
                ->whereNotNull('approved_at')
                ->count();
            
            $lastMonthLoans = Loan::whereYear('approved_at', now()->subMonth()->year)
                ->whereMonth('approved_at', now()->subMonth()->month)
                ->whereNotNull('approved_at')
                ->count();

            $loanGrowth = $lastMonthLoans > 0 
                ? round((($thisMonthLoans - $lastMonthLoans) / $lastMonthLoans) * 100, 2)
                : 0;

            // Overdue loans
            $overdueLoans = Loan::where('loan_status', 'approved')
                ->where('loan_balance', '>', 0)
                ->where('next_due_date', '<', now())
                ->count();

            $overdueTotalAmount = Loan::where('loan_status', 'approved')
                ->where('loan_balance', '>', 0)
                ->where('next_due_date', '<', now())
                ->sum('loan_balance');

            // Top loans by amount
            $topLoans = Loan::with(['employee', 'loanType'])
                ->orderBy('loan_amount', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($loan) {
                    return [
                        'id' => $loan->id,
                        'loan_number' => $loan->loan_number,
                        'employee_name' => $loan->employee->first_name . ' ' . $loan->employee->last_name,
                        'employee_id' => $loan->employee->employee_id,
                        'loan_type' => $loan->loanType->name ?? 'N/A',
                        'loan_amount' => round($loan->loan_amount, 2),
                        'loan_balance' => round($loan->loan_balance, 2),
                        'loan_status' => $loan->loan_status,
                        'approved_at' => $loan->approved_at ? $loan->approved_at->format('Y-m-d') : null
                    ];
                })
                ->toArray();

            // Recent loans (last 10)
            $recentLoans = Loan::with(['employee', 'loanType'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($loan) {
                    return [
                        'id' => $loan->id,
                        'loan_number' => $loan->loan_number,
                        'employee_name' => $loan->employee->first_name . ' ' . $loan->employee->last_name,
                        'employee_id' => $loan->employee->employee_id,
                        'loan_type' => $loan->loanType->name ?? 'N/A',
                        'loan_amount' => round($loan->loan_amount, 2),
                        'loan_status' => $loan->loan_status,
                        'created_at' => $loan->created_at->format('Y-m-d H:i:s'),
                        'days_ago' => $loan->created_at->diffForHumans()
                    ];
                })
                ->toArray();

            // Interest statistics
            // Calculate total interest: loan_amount × (interest_rate / 100) × (tenure_months / 12)
            // This assumes interest_rate is annual percentage
            $totalInterest = Loan::selectRaw('SUM(loan_amount * (interest_rate / 100) * (tenure_months / 12)) as total_interest')
                ->value('total_interest') ?? 0;
            $averageInterestRate = Loan::avg('interest_rate');

            // Performance metrics
            $repaymentRate = $totalDisbursed > 0 
                ? round(($totalRepaid / $totalDisbursed) * 100, 2)
                : 0;

            $defaultRate = $totalLoans > 0 
                ? round(($overdueLoans / $totalLoans) * 100, 2)
                : 0;

            $approvalRate = ($totalLoans - $pendingLoans) > 0
                ? round((($totalLoans - $pendingLoans - $rejectedLoans) / ($totalLoans - $pendingLoans)) * 100, 2)
                : 0;

            // Guarantor stats
            $totalGuarantors = DB::table('loan_guarantors')->distinct('guarantor_id')->count('guarantor_id');
            $pendingGuarantors = DB::table('loan_guarantors')->where('status', 'pending')->count();
            $approvedGuarantors = DB::table('loan_guarantors')->where('status', 'approved')->count();

            // Deduction type breakdown
            $deductionTypeStats = \App\Models\LoanDeduction::select('deduction_type', 
                    DB::raw('count(*) as count'),
                    DB::raw('sum(deduction_amount) as total_amount'))
                ->groupBy('deduction_type')
                ->get()
                ->pluck('count', 'deduction_type')
                ->toArray();

            return [
                'overview' => [
                    'total_loans' => $totalLoans,
                    'active_loans' => $activeLoans,
                    'pending_loans' => $pendingLoans,
                    'processing_loans' => $processingLoans,
                    'completed_loans' => $completedLoans,
                    'rejected_loans' => $rejectedLoans,
                    'canceled_loans' => $canceledLoans,
                    'overdue_loans' => $overdueLoans,
                ],
                'financial' => [
                    'total_disbursed' => round($totalDisbursed, 2),
                    'total_outstanding' => round($totalOutstanding, 2),
                    'total_repaid' => round($totalRepaid, 2),
                    'average_loan_amount' => round($averageLoanAmount, 2),
                    'largest_loan' => round($largestLoan, 2),
                    'smallest_loan' => round($smallestLoan, 2),
                    'total_interest' => round($totalInterest, 2),
                    'average_interest_rate' => round($averageInterestRate, 2),
                    'overdue_amount' => round($overdueTotalAmount, 2),
                ],
                'repayments' => [
                    'total_deductions' => $totalDeductions,
                    'total_deduction_amount' => round($totalDeductionAmount, 2),
                    'total_mpesa_transactions' => $totalTransactions,
                    'total_mpesa_amount' => round($totalTransactionAmount, 2),
                    'deduction_types' => $deductionTypeStats,
                ],
                'loan_types' => [
                    'breakdown' => $loanTypeStats,
                    'total_types' => count($loanTypeStats),
                ],
                'status_distribution' => $statusDistribution,
                'trends' => [
                    'this_month' => $thisMonthLoans,
                    'last_month' => $lastMonthLoans,
                    'growth_percentage' => $loanGrowth,
                    'monthly_trends' => $monthlyTrends,
                ],
                'performance' => [
                    'repayment_rate' => $repaymentRate,
                    'default_rate' => $defaultRate,
                    'approval_rate' => $approvalRate,
                ],
                'guarantors' => [
                    'total_guarantors' => $totalGuarantors,
                    'pending_guarantors' => $pendingGuarantors,
                    'approved_guarantors' => $approvedGuarantors,
                ],
                'top_loans' => $topLoans,
                'recent_activity' => [
                    'recent_loans' => $recentLoans,
                ],
                'generated_at' => now()->toDateTimeString(),
            ];

        } catch (\Exception $e) {
            throw new \Exception('Error generating loan statistics: ' . $e->getMessage());
        }
    }
}


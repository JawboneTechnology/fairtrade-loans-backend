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

    public function processMonthlyDeductions(): void
    {
        $today = now()->startOfDay();

        $loans = Loan::where('loan_status', 'approved')
            ->where('loan_balance', '>', 0)
            ->where('next_due_date', '<=', $today)
            ->get();

        DB::transaction(function () use ($loans) {
            foreach ($loans as $loan) {
                $deductionAmount = $loan->monthly_installment;

                // Deduct the installment from the remaining amount
                $loan->loan_balance -= $deductionAmount;

                // Check if the loan is fully paid
                if ($loan->loan_balance <= 0) {
                    $loan->loan_status = 'completed';
                    $loan->loan_balance = 0;
                } else {
                    $loan->next_due_date = now()->addMonth();
                }

                $loan->save();

                LoanDeduction::create([
                    'loan_id' => $loan->id,
                    'employee_id' => $loan->employee_id,
                    'deduction_amount' => $deductionAmount,
                ]);

                // Queue an SMS notification
                $this->queueDeductionSMS($loan);
            }
        });
    }

    protected function queueDeductionSMS(Loan $loan): void
    {
        $recipient = $loan->employee->phone_number;
        $message = "Dear {$loan->employee->first_name}, your loan deduction of KES {$loan->monthly_installment} has been processed. Remaining balance: KES {$loan->remaining_amount}.";

        // Dispatch the SMS job
        SendSMSJob::dispatch($recipient, $message);
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

    public function getLoanTransactions(int $loanId): JsonResponse
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
        $loans = Loan::with('loanType')->where('employee_id', $employeeId)->get();

        return DataTables::of($loans)
            ->addColumn('loan_type_name', function ($loan) {
                return optional($loan->loanType)->name;
            })
            ->make(true);
    }

    public function getLoanPersonalDeductions($employeeId): JsonResponse
    {
        $deductions = LoanDeduction::where(['employee_id' => $employeeId])->get();

        return DataTables::of($deductions)->make(true);
    }

    public function getLoans(): JsonResponse
    {
        $loans = Loan::with('loanType')->get();

        return DataTables::of($loans)
            ->addColumn('loan_type_name', function ($loan) {
                return optional($loan->loanType)->name;
            })
            ->make(true);
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
}


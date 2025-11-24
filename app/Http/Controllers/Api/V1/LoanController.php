<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\AcceptLoanRequest;
use App\Models\Guarantor;
use App\Models\Notification;
use App\Models\User;
use App\Models\Loan;
use App\Models\LoanType;
use Illuminate\Http\Request;
use App\Services\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use \Illuminate\Contracts\View\View;
use App\Events\LoanApplicantNotified;
use Illuminate\Foundation\Application;
use \Illuminate\Contracts\View\Factory;
use App\Http\Requests\ApplyLoanRequest;
use App\Http\Requests\ApproveLoanRequest;
use App\Http\Requests\CreateLoanTypeRequest;
use App\Http\Requests\GuarantorResponseRequest;

class LoanController extends Controller
{
    //
    protected LoanService $loanService;

    public function __construct(LoanService $loanService)
    {
        $this->loanService = $loanService;
    }

    public function calculateLoanLimit($employeeId): JsonResponse
    {
        try {
            $employee = User::findOrFail($employeeId);
            $loanLimit = $this->loanService->calculateLoanLimit($employee);

            return response()->json([
                'success' => true,
                'message' => 'User Loan Limit Retried successfully.',
                'data' => ['loan_limit' => $loanLimit->max_loan_amount],
            ], 200);
        } catch (\Exception $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'data' => null
            ], 400);
        }
    }

    public function applyForLoan(ApplyLoanRequest $request, $employeeId): JsonResponse
    {
        try {
            $employee = User::findOrFail($employeeId);

            $this->loanService->applyForLoan($employee, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Loan application submitted successfully.',
                'data' => null,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $e->getTrace()
            ], 500);
        }
    }

    public function createLoanType(CreateLoanTypeRequest $request): JsonResponse
    {
        try {
            $loan = $this->loanService->saveLoanType($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Loan application submitted successfully.',
                'data' => $loan,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Get Monthly Deductions for DataTables
     * Supports server-side processing with pagination, searching, and sorting
     */
    public function processDeductions(): JsonResponse
    {
        return $this->loanService->processMonthlyDeductions();
    }

    /**
     * Process Employee Deduction
     * Creates a deduction record and triggers notifications
     */
    public function processEmployeeDeduction(\App\Http\Requests\ProcessDeductionRequest $request, $employeeId): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Validate employee matches the one in request
            if ($employeeId !== $request->employee_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee ID mismatch',
                    'data' => null
                ], 400);
            }

            // Fetch loan and user
            $loan = Loan::with('loanType')->findOrFail($request->loan_id);
            $user = User::findOrFail($request->employee_id);

            // Verify loan belongs to employee
            if ($loan->employee_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Loan does not belong to this employee',
                    'data' => null
                ], 400);
            }

            // Verify loan number matches
            if ($loan->loan_number !== $request->loan_number) {
                return response()->json([
                    'success' => false,
                    'message' => 'Loan number mismatch',
                    'data' => null
                ], 400);
            }

            // Check if deduction amount exceeds loan balance
            if ($request->amount > $loan->loan_balance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Deduction amount exceeds loan balance',
                    'data' => [
                        'requested_amount' => $request->amount,
                        'loan_balance' => $loan->loan_balance
                    ]
                ], 400);
            }

            // Create deduction record
            $deduction = \App\Models\LoanDeduction::create([
                'loan_id' => $loan->id,
                'employee_id' => $user->id,
                'loan_number' => $loan->loan_number,
                'deduction_amount' => $request->amount,
                'deduction_type' => $request->deduction_type,
                'deduction_date' => now(),
            ]);

            // Update loan balance
            $oldBalance = $loan->loan_balance;
            $newBalance = max(0, $oldBalance - $request->amount);
            $loan->loan_balance = $newBalance;

            // Update loan status if fully paid
            if ($newBalance <= 0) {
                $loan->loan_status = 'completed';
            }

            $loan->save();
            $loan->refresh(); // Refresh to get updated values

            // Dispatch event for notifications
            event(new \App\Events\DeductionProcessed(
                $deduction,
                $loan,
                $user,
                $newBalance,
                $request->deduction_type
            ));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Deduction processed successfully. Notifications are being sent.',
                'data' => [
                    'deduction_id' => $deduction->id,
                    'loan_number' => $loan->loan_number,
                    'deduction_amount' => number_format($deduction->deduction_amount, 2),
                    'old_balance' => number_format($oldBalance, 2),
                    'new_balance' => number_format($newBalance, 2),
                    'loan_status' => $loan->loan_status,
                    'deduction_type' => $request->deduction_type,
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            
            Log::error('=== DEDUCTION PROCESSING - RECORD NOT FOUND ===');
            Log::error('Employee ID: ' . $employeeId);
            Log::error('Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Loan or employee not found',
                'data' => null
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('=== ERROR PROCESSING EMPLOYEE DEDUCTION ===');
            Log::error('Employee ID: ' . $employeeId);
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process deduction: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    public function getLoanStatus($loanId): JsonResponse
    {
        try {
            $loan = Loan::findOrFail($loanId);

            return response()->json([
                'success' => true,
                'message' => 'Loan status retrieved successfully.',
                'data' => $loan,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 400);
        }
    }

    public function approveLoan(ApproveLoanRequest $request, $loanId): JsonResponse
    {
        try {
            $loan = $this->loanService->approveLoan($loanId, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Loan processed successfully.',
                'data' => $loan
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    public function getCreditScores(Request $request): JsonResponse
    {
        try {
            $employee = User::where('employee_id', $request['employeeId'])->firstOrFail();

            $creditScore = $this->loanService->getCreditScore($employee);

            return response()->json([
                'success' => true,
                'message' => 'User credit score retrieved successfully.',
                'data' => $creditScore,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() . $e->getLine(),
                'data' => null
            ], 500);
        }
    }

    public function guarantorResponse(GuarantorResponseRequest $request): Factory|Application|View|JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            $loan = Loan::findOrFail($data['loan_id']);

            $guarantor = $loan->guarantors()
                ->where('guarantor_id', $data['guarantor_id'])
                ->first();

            if (!$guarantor) {
                Log::error("guarantor not found");
            }

            if ($guarantor->status === 'accepted' || $guarantor->status === 'declined') {
                return view('guarantor.already_responded');
            }

            $updated = DB::table('loan_guarantors')
                ->where('loan_id', $data['loan_id'])
                ->where('guarantor_id', $data['guarantor_id'])
                ->update([
                    'status' => $data['response'],
                    'response' => $validated['reason'] ?? null,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                throw new \Exception('Guarantor is not associated with this loan or already responded');
            }

            // Update specific notification by ID
            Notification::where('id', $data['notification_id'])->update([
                'read_at' => now(),
                'is_read' => 1,
            ]);

            DB::commit();

            if ($data['response'] === 'accepted') {
                 LoanApplicantNotified::dispatch($loan, $data['response'], $data['guarantor_id']);
                return view('guarantor.success');
            } else {
                 LoanApplicantNotified::dispatch($loan, $data['response'], $data['guarantor_id']);
                return view('guarantor.declined');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing guarantor response: ' . $e->getMessage() . ' Line: ' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    public function getLoanTypes(): JsonResponse
    {
        try {
            $loanTypes = $this->loanService->getLoanTypes();

            return response()->json([
                'success' => true,
                'message' => 'Loan types retrieved successfully.',
                'data' => $loanTypes,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 400);
        }
    }

    public function getUserLoanType(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            $loanTypes = LoanType::withCount(['loans' => function($query) use ($userId) {
                $query->where('employee_id', $userId);
            }])->get();

            return response()->json([
                'success' => true,
                'message' => 'Loan types retrieved successfully.',
                'data' => $loanTypes,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    public function getUserLoanDetails($loanId): JsonResponse
    {
        try {
            $data = $this->loanService->getUserLoanDetails($loanId);

            return response()->json([
                'success' => true,
                'message' => 'Loan details retrieved successfully.',
                'data'    => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() . '-' . $e->getTraceAsString(),
                'data'    => null
            ], 500);
        }
    }

    /**
     * Get Employee Loans
     * Returns simplified loan information for an employee
     */
    public function getEmployeeLoans($employeeId): JsonResponse
    {
        try {
            $loans = Loan::with('loanType:id,name')
                ->where('employee_id', $employeeId)
                ->select([
                    'id',
                    'loan_number',
                    'loan_type_id',
                    'loan_balance',
                    'interest_rate',
                    'loan_status',
                    'monthly_installment',
                    'loan_amount',
                    'approved_at',
                    'applied_at'
                ])
                ->orderBy('applied_at', 'desc')
                ->get()
                ->map(function ($loan) {
                    return [
                        'id' => $loan->id,
                        'loan_number' => $loan->loan_number,
                        'loan_type' => optional($loan->loanType)->name,
                        'loan_balance' => number_format($loan->loan_balance, 2),
                        'interest_rate' => $loan->interest_rate,
                        'loan_status' => $loan->loan_status,
                        'monthly_installment' => number_format($loan->monthly_installment, 2),
                        'loan_amount' => number_format($loan->loan_amount, 2),
                        'approved_at' => $loan->approved_at ? \Carbon\Carbon::parse($loan->approved_at)->format('d M Y') : null,
                        'applied_at' => $loan->applied_at ? \Carbon\Carbon::parse($loan->applied_at)->format('d M Y') : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Employee loans retrieved successfully.',
                'data' => $loans,
            ], 200);
        } catch (\Exception $e) {
            Log::error('=== ERROR FETCHING EMPLOYEE LOANS ===');
            Log::error('Employee ID: ' . $employeeId);
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch employee loans: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    public function getUserGuaranteedLoans(): JsonResponse
    {
        try {
            $user = auth()->user();

            $loansAsGuarantor = $user->guaranteedLoans()->with('loanType')->get();

            return response()->json([
                'success' => true,
                'message' => 'Guaranteed loans retrieved successfully.',
                'data' => $loansAsGuarantor,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null
            ], 500);
        }
    }

    public function cancelLoan(Loan $loan): JsonResponse
    {
        try {
            if ($loan->loan_status !== 'pending') {
                return response()->json([
                    'message' => 'Loan cannot be canceled because it is not in a pending state.',
                ], 400);
            }

            $this->loanService->cancelAppliedLoan($loan);

            return response()->json([
                'message' => 'Loan canceled successfully.',
                'loan' => $loan,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while canceling the loan. Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function testLoanAcceptance(Request $request): JsonResponse
    {
        try {
            $loan = Loan::findOrFail($request->loanId);
            $guarantors = Guarantor::where('loan_id', $loan->id)->get();
            $statuses = $guarantors->pluck('status');

            return response()->json([
                'success' => true,
                'message' => "Message sent successfully.",
                'data' => $statuses,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    public function acceptPayment(AcceptLoanRequest $request): JsonResponse
    {
        try {
            $transaction = $this->loanService->acceptManualPayment($request->employee_id, $request->amount, $request->payment_type);

            return response()->json([
                'success' => true,
                'message' => 'Payment accepted successfully.',
                'loan' => $transaction,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to accept payment.',
                'error' => $e->getMessage() . ' - ' . $e->getFile() .' - ' . $e->getLine() .' - '. $e->getTraceAsString(),
            ], 400);
        }
    }

    public function getLoanTransactions($loanId): JsonResponse
    {
        return $this->loanService->getLoanTransactions($loanId);
    }

    public function getMiniStatement(Request $request, $loanId): JsonResponse
    {
        try {
            $canEmail = $request->input('send_email', false);

            $miniStatement = $this->loanService->generateMiniStatement($loanId, $canEmail);

            return response()->json([
                'success' => true,
                'message' => 'Minimum statement generated successfully.',
                'data' => $miniStatement,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate mini statement.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getUserLoanPayments(Request $request): JsonResponse
    {
        try {
            $authUser = auth()->user();

            $user = User::findOrFail($authUser->id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null,
                ], 404);
            }

            $start = $request->input('start', 0);
            $limit = $request->input('limit', 10);

            $payments = $this->loanService->getUserPayments($user, $start, $limit);

            return response()->json([
                'success' => true,
                'message' => 'User payments fetched successfully.',
                'data' => [
                    'payments' => $payments,
                    'nextStart' => $start + $limit,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user payments.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getRecentLoan(): JsonResponse
    {
        try {
            $authUser = auth()->user();

            $user = User::findOrFail($authUser->id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null,
                ], 404);
            }

            $loans = $this->loanService->getUserAllLoansWithCalculations($user);

            return response()->json([
                'success' => true,
                'message' => 'User active loans fetched successfully.',
                'data' => [
                    'loans' => $loans,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user loans.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getUserLoans(Request $request): JsonResponse
    {
        try {
            $authUserId = auth()->id();

            $user = User::findOrFail($authUserId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null,
                ], 404);
            }

            $start = $request->input('start', 0);
            $limit = $request->input('limit', 10);

            $loans = $this->loanService->getUserLoans($user, $start, $limit);

            return response()->json([
                'success' => true,
                'message' => 'Loan fetched successfully.',
                'data' => [
                    'loans' => $loans,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user loans.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getPersonalLoans(Request $request, $employeeId): JsonResponse
    {
        return $this->loanService->getUserPersonalLoans($employeeId);
    }

    public function getLoanPersonalDeductions(Request $request, $employeeId): JsonResponse
    {
        return $this->loanService->getLoanPersonalDeductions($employeeId);
    }

    public function getLoans(Request $request): JsonResponse
    {
        return $this->loanService->getLoans();
    }

    public function getUserLoanStats(): JsonResponse
    {
        try {
            $stats = $this->loanService->getUserLoanStats();
            return response()->json([
                'success' => true,
                'message' => 'User Loan stats fetched successfully.',
                'data' => $stats,
            ]);
        } catch (\Exception $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Get Loan Deductions for DataTables (Admin)
     * Supports server-side processing with pagination, searching, and sorting
     */
    public function getLoanDeductionsForDataTables(Request $request): JsonResponse
    {
        try {
            // DataTables parameters
            $draw = $request->input('draw', 1);
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $searchValue = $request->input('search.value', '');
            $orderColumnIndex = $request->input('order.0.column', 0);
            $orderDirection = $request->input('order.0.dir', 'desc');
            
            // Additional filters
            $loanId = $request->input('loan_id');
            $employeeId = $request->input('employee_id');
            $deductionType = $request->input('deduction_type'); // Salary, Manual, etc.
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            $minAmount = $request->input('min_amount');
            $maxAmount = $request->input('max_amount');

            // Column mapping for sorting
            $columns = [
                0 => 'id',
                1 => 'loan_id',
                2 => 'employee_id',
                3 => 'deduction_amount',
                4 => 'deduction_type',
                5 => 'deducted_at',
                6 => 'created_at'
            ];
            
            $orderColumn = $columns[$orderColumnIndex] ?? 'created_at';

            // Base query with relationships
            $query = \App\Models\LoanDeduction::with([
                    'loan:id,loan_number,loan_amount,loan_balance,loan_status',
                    'employee:id,first_name,last_name,email,phone_number,employee_id'
                ])
                ->select([
                    'id',
                    'loan_id',
                    'employee_id',
                    'deduction_amount',
                    'deduction_type',
                    'deducted_at',
                    'created_at',
                    'updated_at'
                ]);

            // Apply loan_id filter
            if ($loanId) {
                $query->where('loan_id', $loanId);
            }

            // Apply employee_id filter
            if ($employeeId) {
                $query->where('employee_id', $employeeId);
            }

            // Apply deduction_type filter
            if ($deductionType) {
                $query->where('deduction_type', $deductionType);
            }

            // Apply date range filter
            if ($dateFrom) {
                $query->whereDate('deducted_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('deducted_at', '<=', $dateTo);
            }

            // Apply amount range filter
            if ($minAmount) {
                $query->where('deduction_amount', '>=', $minAmount);
            }
            if ($maxAmount) {
                $query->where('deduction_amount', '<=', $maxAmount);
            }

            // Get total records before filtering
            $recordsTotal = \App\Models\LoanDeduction::count();

            // Apply search filter
            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('deduction_amount', 'like', "%{$searchValue}%")
                      ->orWhere('deduction_type', 'like', "%{$searchValue}%")
                      ->orWhere('id', 'like', "%{$searchValue}%")
                      ->orWhereHas('employee', function ($q) use ($searchValue) {
                          $q->where('first_name', 'like', "%{$searchValue}%")
                            ->orWhere('last_name', 'like', "%{$searchValue}%")
                            ->orWhere('email', 'like', "%{$searchValue}%")
                            ->orWhere('employee_id', 'like', "%{$searchValue}%");
                      })
                      ->orWhereHas('loan', function ($q) use ($searchValue) {
                          $q->where('loan_number', 'like', "%{$searchValue}%");
                      });
                });
            }

            // Get filtered count
            $recordsFiltered = $query->count();

            // Apply sorting
            $query->orderBy($orderColumn, $orderDirection);

            // Apply pagination
            $deductions = $query->skip($start)->take($length)->get();

            // Format data for DataTables
            $data = $deductions->map(function ($deduction) {
                return [
                    'id' => $deduction->id,
                    'loan_id' => $deduction->loan_id,
                    'loan' => $deduction->loan ? [
                        'id' => $deduction->loan->id,
                        'loan_number' => $deduction->loan->loan_number,
                        'loan_amount' => number_format($deduction->loan->loan_amount, 2),
                        'loan_balance' => number_format($deduction->loan->loan_balance, 2),
                        'loan_status' => $deduction->loan->loan_status,
                    ] : null,
                    'employee_id' => $deduction->employee_id,
                    'employee' => $deduction->employee ? [
                        'id' => $deduction->employee->id,
                        'name' => $deduction->employee->first_name . ' ' . $deduction->employee->last_name,
                        'email' => $deduction->employee->email,
                        'phone_number' => $deduction->employee->phone_number,
                        'employee_id' => $deduction->employee->employee_id,
                    ] : null,
                    'deduction_amount' => number_format($deduction->deduction_amount, 2),
                    'deduction_amount_raw' => $deduction->deduction_amount,
                    'deduction_type' => $deduction->deduction_type,
                    'deduction_type_badge' => $this->getDeductionTypeBadge($deduction->deduction_type),
                    'deducted_at' => $deduction->deducted_at ? date('Y-m-d H:i:s', strtotime($deduction->deducted_at)) : null,
                    'deducted_at_formatted' => $deduction->deducted_at ? date('d M Y, h:i A', strtotime($deduction->deducted_at)) : 'N/A',
                    'created_at' => $deduction->created_at->format('Y-m-d H:i:s'),
                    'created_at_formatted' => $deduction->created_at->format('d M Y, h:i A'),
                    'updated_at' => $deduction->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            // DataTables response format
            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
                'success' => true
            ]);

        } catch (\Exception $e) {
            Log::error('=== ERROR FETCHING LOAN DEDUCTIONS FOR DATATABLES ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return response()->json([
                'draw' => intval($request->input('draw', 1)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'success' => false,
                'error' => 'Failed to fetch loan deductions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get deduction type badge HTML/CSS class for DataTables
     */
    private function getDeductionTypeBadge(string $deductionType): array
    {
        $badges = [
            'Salary' => [
                'text' => 'Salary Deduction',
                'class' => 'badge-primary',
                'color' => '#007bff'
            ],
            'Manual' => [
                'text' => 'Manual Payment',
                'class' => 'badge-success',
                'color' => '#28a745'
            ],
            'Automatic' => [
                'text' => 'Automatic',
                'class' => 'badge-info',
                'color' => '#17a2b8'
            ],
            'Adjustment' => [
                'text' => 'Adjustment',
                'class' => 'badge-warning',
                'color' => '#ffc107'
            ]
        ];

        return $badges[$deductionType] ?? [
            'text' => ucfirst($deductionType),
            'class' => 'badge-secondary',
            'color' => '#6c757d'
        ];
    }

    /**
     * Get User Transactions for DataTables (Admin)
     * Supports server-side processing with pagination, searching, and sorting
     */
    public function getUserTransactionsForDataTables(Request $request, string $employeeId): JsonResponse
    {
        try {
            // DataTables parameters
            $draw = $request->input('draw', 1);
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $searchValue = $request->input('search.value', '');
            $orderColumnIndex = $request->input('order.0.column', 0);
            $orderDirection = $request->input('order.0.dir', 'desc');
            
            // Additional filters
            $loanId = $request->input('loan_id');
            $paymentType = $request->input('payment_type'); // Mobile_Money, Bank_Transfer, etc.
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            $minAmount = $request->input('min_amount');
            $maxAmount = $request->input('max_amount');

            // Column mapping for sorting
            $columns = [
                0 => 'id',
                1 => 'transaction_reference',
                2 => 'employee_id',
                3 => 'loan_id',
                4 => 'amount',
                5 => 'payment_type',
                6 => 'transaction_date',
                7 => 'created_at'
            ];
            
            $orderColumn = $columns[$orderColumnIndex] ?? 'created_at';

            // Base query with relationships
            $query = \App\Models\Transaction::with([
                    'loan:id,loan_number,loan_amount,loan_balance,loan_status',
                    'employee:id,first_name,last_name,email,phone_number,employee_id'
                ])
                ->select([
                    'id',
                    'loan_id',
                    'employee_id',
                    'amount',
                    'payment_type',
                    'transaction_reference',
                    'transaction_date',
                    'created_at',
                    'updated_at'
                ]);

            // Apply loan_id filter
            if ($loanId) {
                $query->where('loan_id', $loanId);
            }

            // Apply employee_id filter
            if ($employeeId) {
                $query->where('employee_id', $employeeId);
            }

            // Apply payment_type filter
            if ($paymentType) {
                $query->where('payment_type', $paymentType);
            }

            // Apply date range filter
            if ($dateFrom) {
                $query->whereDate('transaction_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('transaction_date', '<=', $dateTo);
            }

            // Apply amount range filter
            if ($minAmount) {
                $query->where('amount', '>=', $minAmount);
            }
            if ($maxAmount) {
                $query->where('amount', '<=', $maxAmount);
            }

            // Get total records before filtering
            $recordsTotal = \App\Models\Transaction::count();

            // Apply search filter
            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('transaction_reference', 'like', "%{$searchValue}%")
                      ->orWhere('amount', 'like', "%{$searchValue}%")
                      ->orWhere('payment_type', 'like', "%{$searchValue}%")
                      ->orWhere('id', 'like', "%{$searchValue}%")
                      ->orWhereHas('employee', function ($q) use ($searchValue) {
                          $q->where('first_name', 'like', "%{$searchValue}%")
                            ->orWhere('last_name', 'like', "%{$searchValue}%")
                            ->orWhere('email', 'like', "%{$searchValue}%")
                            ->orWhere('employee_id', 'like', "%{$searchValue}%");
                      })
                      ->orWhereHas('loan', function ($q) use ($searchValue) {
                          $q->where('loan_number', 'like', "%{$searchValue}%");
                      });
                });
            }

            // Get filtered count
            $recordsFiltered = $query->count();

            // Apply sorting
            $query->orderBy($orderColumn, $orderDirection);

            // Apply pagination
            $transactions = $query->skip($start)->take($length)->get();

            // Format data for DataTables
            $data = $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'transaction_reference' => $transaction->transaction_reference ?? 'N/A',
                    'loan_id' => $transaction->loan_id,
                    'loan' => $transaction->loan ? [
                        'id' => $transaction->loan->id,
                        'loan_number' => $transaction->loan->loan_number,
                        'loan_amount' => number_format($transaction->loan->loan_amount, 2),
                        'loan_balance' => number_format($transaction->loan->loan_balance, 2),
                        'loan_status' => $transaction->loan->loan_status,
                    ] : null,
                    'employee_id' => $transaction->employee_id,
                    'employee' => $transaction->employee ? [
                        'id' => $transaction->employee->id,
                        'name' => $transaction->employee->first_name . ' ' . $transaction->employee->last_name,
                        'email' => $transaction->employee->email,
                        'phone_number' => $transaction->employee->phone_number,
                        'employee_id' => $transaction->employee->employee_id,
                    ] : null,
                    'amount' => number_format($transaction->amount, 2),
                    'amount_raw' => $transaction->amount,
                    'payment_type' => $transaction->payment_type,
                    'payment_type_badge' => $this->getPaymentTypeBadge($transaction->payment_type),
                    'transaction_date' => $transaction->transaction_date ? date('Y-m-d H:i:s', strtotime($transaction->transaction_date)) : null,
                    'transaction_date_formatted' => $transaction->transaction_date ? date('d M Y, h:i A', strtotime($transaction->transaction_date)) : 'N/A',
                    'created_at' => $transaction->created_at->format('Y-m-d H:i:s'),
                    'created_at_formatted' => $transaction->created_at->format('d M Y, h:i A'),
                    'updated_at' => $transaction->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            // DataTables response format
            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
                'success' => true
            ]);

        } catch (\Exception $e) {
            Log::error('=== ERROR FETCHING USER TRANSACTIONS FOR DATATABLES ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return response()->json([
                'draw' => intval($request->input('draw', 1)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'success' => false,
                'error' => 'Failed to fetch transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment type badge HTML/CSS class for DataTables
     */
    private function getPaymentTypeBadge(string $paymentType): array
    {
        $badges = [
            'Mobile_Money' => [
                'text' => 'Mobile Money',
                'class' => 'badge-success',
                'color' => '#28a745'
            ],
            'Bank_Transfer' => [
                'text' => 'Bank Transfer',
                'class' => 'badge-primary',
                'color' => '#007bff'
            ],
            'Cash' => [
                'text' => 'Cash',
                'class' => 'badge-warning',
                'color' => '#ffc107'
            ],
            'Cheque' => [
                'text' => 'Cheque',
                'class' => 'badge-info',
                'color' => '#17a2b8'
            ],
            'Card' => [
                'text' => 'Card Payment',
                'class' => 'badge-secondary',
                'color' => '#6c757d'
            ]
        ];

        return $badges[$paymentType] ?? [
            'text' => str_replace('_', ' ', $paymentType),
            'class' => 'badge-secondary',
            'color' => '#6c757d'
        ];
    }

    /**
     * Get loan statistics for dashboard
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLoanStatistics()
    {
        try {
            $statistics = $this->loanService->getLoanStatistics();

            return response()->json([
                'success' => true,
                'message' => 'Loan statistics retrieved successfully.',
                'data' => $statistics
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve loan statistics: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve loan statistics: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}

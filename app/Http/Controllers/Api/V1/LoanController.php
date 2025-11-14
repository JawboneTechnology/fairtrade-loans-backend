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

    public function processDeductions(): void
    {
        $this->loanService->processMonthlyDeductions();
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
}

<?php

namespace App\Services;

use App\Models\LoanType;
use App\Models\User;
use App\Models\Loan;
use Illuminate\Support\Facades\Log;

class LoanBackgroundCheckService
{
    /**
     * Check a user's past loan history and return a credit score and other metrics.
     *
     * @param User $user
     * @param bool $isGuarantor
     * @return array
     */
    public function check(User $user, bool $isGuarantor = false): array
    {
        // Retrieve the user's loans with statuses that matter.
        $loans = Loan::where('employee_id', $user->id)
            ->whereIn('loan_status', ['repaid', 'defaulted'])
            ->get();

        // Default metrics for users with no loan history
        if ($loans->isEmpty()) {
            if ($isGuarantor) {
                return [
                    'active_guarantees' => 0,
                    'is_qualified'      => true,
                ];
            }
            return [
                'total_loans'          => 0,
                'defaults'             => 0,
                'successfulRepayments' => 0,
                'credit_score'         => 100,
            ];
        }

        // Get the loan type for the first loan (if available)
        $loanType = $loans->first() ? LoanType::find($loans->first()->loan_type_id) : null;

        if (!$loanType) {
            Log::error("Loan type not found for user ID: {$user->id}");
            return [
                'total_loans'          => 0,
                'defaults'             => 0,
                'successfulRepayments' => 0,
                'credit_score'         => 100,
            ];
        }

        $totalLoans = $loans->count();
        $defaults = $loans->where('loan_status', 'defaulted')->count();
        $successfulRepayments = $loans->where('loan_status', 'repaid')->count();

        // Compute a credit score with enhanced logic.
        $score = $this->calculateCreditScore($totalLoans, $successfulRepayments, $defaults);

        // Additional metrics for guarantors (if applicable).
        $metrics = [
            'total_loans'          => $totalLoans,
            'defaults'             => $defaults,
            'successfulRepayments' => $successfulRepayments,
            'credit_score'         => $score,
        ];

        // Add guarantor-specific checks if the user is a guarantor.
        if ($isGuarantor) {
            $metrics['active_guarantees'] = $this->getActiveGuaranteesCount($user);
            $metrics['is_qualified'] = $this->isGuarantorQualified($user, $score, $loanType);
        }

        return $metrics;
    }

    /**
     * Calculate a credit score based on loan history.
     *
     * @param int $totalLoans
     * @param int $successfulRepayments
     * @param int $defaults
     * @return int
     */
    protected function calculateCreditScore(int $totalLoans, int $successfulRepayments, int $defaults): int
    {
        if ($totalLoans === 0) {
            return 100; // Default score for users with no loan history.
        }

        $repaymentRate = $successfulRepayments / $totalLoans;
        $defaultRate = $defaults / $totalLoans;

        // Adjust the score based on repayment and default rates.
        $score = ($repaymentRate - $defaultRate) * 100;

        // Ensure the score is within a valid range (0 to 100).
        return max(0, min(100, (int) round($score)));
    }

    /**
     * Get the number of active guarantees for a guarantor.
     *
     * @param User $user
     * @return int
     */
    protected function getActiveGuaranteesCount(User $user): int
    {
        return $user->guaranteedLoans()
            ->wherePivotIn('status', ['pending', 'approved'])
            ->count();
    }

    /**
     * Check if a guarantor meets the minimum qualifications.
     *
     * @param User $user
     * @param int $creditScore
     * @param LoanType $loanType
     * @return bool
     */
    protected function isGuarantorQualified(User $user, int $creditScore, LoanType $loanType): bool
    {
        $qualifications = $loanType->guarantor_qualifications;

        // Ensure we have an array. If the field is stored as JSON and not auto-casted, decode it.
        if (!is_array($qualifications)) {
            $qualifications = json_decode($qualifications, true);
            if (!is_array($qualifications)) {
                Log::error("Invalid guarantor qualifications for loan type ID: {$loanType->id}");
                return false;
            }
        }

        // Check if the required keys exist.
        if (!isset($qualifications['min_credit_score'], $qualifications['employment_years'])) {
            Log::error("Missing required keys in guarantor qualifications for loan type ID: {$loanType->id}");
            return false;
        }

        $minCreditScore = $qualifications['min_credit_score'];
        $minEmploymentYears = $qualifications['employment_years'];

        return $creditScore >= $minCreditScore &&
            $user->years_of_employment >= $minEmploymentYears;
    }
}

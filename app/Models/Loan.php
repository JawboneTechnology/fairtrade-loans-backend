<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Loan extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'employee_id',
        'loan_number',
        'loan_type_id',
        'loan_amount',
        'loan_balance',
        'interest_rate',
        'tenure_months',
        'monthly_installment',
        'loan_status',
        'next_due_date',
        'guarantors',
        'approved_amount',
        'approved_by',
        'approved_at',
        'remarks',
        'qualifications',
        'applied_at',
    ];

    protected $casts = [
        'qualifications'  => 'array', // This will auto-convert JSON to an array.
        'guarantors'      => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function employee(): belongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function loanType(): BelongsTo
    {
        return $this->belongsTo(LoanType::class);
    }

    public function guarantors(): hasMany
    {
        return $this->hasMany(Guarantor::class);
    }

    public function guaranteedBy()
    {
        return $this->belongsToMany(User::class, 'loan_guarantors', 'loan_id', 'guarantor_id')
            ->using(Guarantor::class)
            ->withPivot(['loan_number', 'guarantor_liability_amount', 'status'])
            ->withTimestamps();
    }

    /**
     * Relationship to the Transaction model.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Relationship to the LoanDeduction model.
     */
    public function deductions(): HasMany
    {
        return $this->hasMany(LoanDeduction::class);
    }

    public function getLoanStats(): array
    {
        $totalStats = [
            'total_loans' => $this->count(),
            'total_approved' => $this->whereNotNull('approved_at')->count(),
            'total_pending' => $this->whereNull('approved_at')->count(),
            'total_amount' => $this->sum('loan_amount'),
            'total_approved_amount' => $this->sum('approved_amount'),
            'total_balance' => $this->sum('loan_balance'),
        ];

        // Status breakdown
        $statusStats = $this->select('loan_status')
            ->selectRaw('count(*) as count')
            ->selectRaw('sum(loan_amount) as total_amount')
            ->groupBy('loan_status')
            ->get()
            ->pluck(null, 'loan_status');

        // Loan type breakdown
        $typeStats = $this->with('loanType:id,name')
            ->select('loan_type_id')
            ->selectRaw('count(*) as count')
            ->selectRaw('sum(loan_amount) as total_amount')
            ->selectRaw('avg(interest_rate) as avg_interest')
            ->groupBy('loan_type_id')
            ->get();

        // Monthly application trend (last 12 months)
        $monthlyTrend = $this->selectRaw("
            DATE_FORMAT(applied_at, '%Y-%m') as month,
            COUNT(*) as count,
            SUM(loan_amount) as amount,
            SUM(CASE WHEN approved_at IS NOT NULL THEN 1 ELSE 0 END) as approved_count
        ")
            ->where('applied_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Top applicants
        $topApplicants = $this->with('employee:id,first_name,last_name')
            ->select('employee_id')
            ->selectRaw('count(*) as loan_count')
            ->selectRaw('sum(loan_amount) as total_amount')
            ->groupBy('employee_id')
            ->orderByDesc('loan_count')
            ->limit(5)
            ->get();

        return [
            'totals' => $totalStats,
            'statuses' => $statusStats,
            'types' => $typeStats,
            'monthly_trend' => $monthlyTrend,
            'top_applicants' => $topApplicants,
        ];
    }

    public function getUserLoanStats(string $employeeId): array
    {
        $userLoans = $this->where('employee_id', $employeeId);

        $stats = [
            // Basic counts
            'total_loans' => $userLoans->count(),
            'approved_loans' => $userLoans->whereNotNull('approved_at')->count(),
            'pending_loans' => $userLoans->whereNull('approved_at')->count(),
            'rejected_loans' => $userLoans->where('loan_status', 'rejected')->count(),

            // Amount metrics
            'total_requested' => $userLoans->sum('loan_amount'),
            'total_approved' => $userLoans->sum('approved_amount'),
            'total_balance' => $userLoans->sum('loan_balance'),
            'avg_loan_amount' => $userLoans->avg('loan_amount'),

            // Status breakdown
            'status_distribution' => $userLoans->select('loan_status')
                ->selectRaw('count(*) as count')
                ->groupBy('loan_status')
                ->get()
                ->pluck('count', 'loan_status'),

            // Loan type breakdown
            'type_distribution' => $userLoans->with('loanType:id,name')
                ->select('loan_type_id')
                ->selectRaw('count(*) as count')
                ->selectRaw('sum(loan_amount) as total_amount')
                ->groupBy('loan_type_id')
                ->get(),

            // Current active loans
            'active_loans' => $userLoans->where('loan_balance', '>', 0)
                ->where('loan_status', 'approved')
                ->count(),

            // Guarantor info
            'times_guaranteed' => $this->whereHas('guaranteedBy', function($q) use ($employeeId) {
                $q->where('guarantor_id', $employeeId);
            })->count(),

            // Recent loans (last 5)
            'recent_loans' => $userLoans->with('loanType:id,name')
                ->latest('applied_at')
                ->limit(5)
                ->get()
                ->makeHidden(['qualifications', 'guarantors']), // Hide sensitive fields
        ];

        return $stats;
    }
}

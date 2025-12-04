<?php

namespace App\Repositories;

use App\Models\Grant;
use App\Events\GrantApplied;
use App\Events\GrantApproved;
use App\Events\GrantRejected;
use App\Interfaces\GrantRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Yajra\DataTables\DataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class GrantRepository implements GrantRepositoryInterface
{
    public function getAllGrants(): Collection
    {
        return Grant::query()->with(['user', 'dependent'])->get();
    }

    public function getGrantsTable(): JsonResponse
    {
        try {
            $draw = request()->input('draw', 1);
            $start = request()->input('start', 0);
            $length = request()->input('length', 10);
            $searchValue = request()->input('search.value', '');
            $orderColumnIndex = request()->input('order.0.column', 0);
            $orderDirection = request()->input('order.0.dir', 'desc');

            // Additional filters
            $status = request()->input('status');
            $grantTypeId = request()->input('grant_type_id');
            $userId = request()->input('user_id');
            $dateFrom = request()->input('date_from');
            $dateTo = request()->input('date_to');
            $minAmount = request()->input('min_amount');
            $maxAmount = request()->input('max_amount');

            // Column mapping for sorting
            $columns = [
                0 => 'id',
                1 => 'grant_number',
                2 => 'user_id',
                3 => 'grant_type_id',
                4 => 'dependent_id',
                5 => 'amount',
                6 => 'status',
                7 => 'approval_date',
                8 => 'disbursement_date',
                9 => 'created_at',
                10 => 'updated_at',
            ];

            $orderColumn = $columns[$orderColumnIndex] ?? 'created_at';

            // Base query with relationships
            $query = Grant::with([
                'user:id,first_name,middle_name,last_name,email,phone_number,employee_id',
                'dependent:id,first_name,last_name,relationship',
                'grantType:id,name,grant_code,description,max_amount'
            ]);

            // Apply status filter
            if ($status) {
                $query->where('status', $status);
            }

            // Apply grant_type_id filter
            if ($grantTypeId) {
                $query->where('grant_type_id', $grantTypeId);
            }

            // Apply user_id filter
            if ($userId) {
                $query->where('user_id', $userId);
            }

            // Apply date range filter
            if ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            // Apply amount range filter
            if ($minAmount) {
                $query->where('amount', '>=', $minAmount);
            }
            if ($maxAmount) {
                $query->where('amount', '<=', $maxAmount);
            }

            // Get total records before filtering
            $totalRecords = Grant::count();

            // Apply global search if provided
            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('amount', 'like', "%{$searchValue}%")
                        ->orWhere('reason', 'like', "%{$searchValue}%")
                        ->orWhere('status', 'like', "%{$searchValue}%")
                        ->orWhere('admin_notes', 'like', "%{$searchValue}%")
                        ->orWhereHas('user', function ($sq) use ($searchValue) {
                            $sq->where('first_name', 'like', "%{$searchValue}%")
                                ->orWhere('last_name', 'like', "%{$searchValue}%")
                                ->orWhere('email', 'like', "%{$searchValue}%")
                                ->orWhere('employee_id', 'like', "%{$searchValue}%");
                        })
                        ->orWhereHas('dependent', function ($sq) use ($searchValue) {
                            $sq->where('first_name', 'like', "%{$searchValue}%")
                                ->orWhere('last_name', 'like', "%{$searchValue}%");
                        })
                        ->orWhereHas('grantType', function ($sq) use ($searchValue) {
                            $sq->where('name', 'like', "%{$searchValue}%")
                                ->orWhere('grant_code', 'like', "%{$searchValue}%");
                        });
                });
            }

            // Get filtered count
            $filteredRecords = $query->count();

            // Apply sorting
            $query->orderBy($orderColumn, $orderDirection);

            // Apply pagination
            $grants = $query->skip($start)
                ->take($length)
                ->get()
                ->map(function ($grant) {
                    return [
                        'id' => $grant->id,
                        'grant_type_id' => $grant->grant_type_id,
                        'grant_type' => $grant->grantType ? [
                            'id' => $grant->grantType->id,
                            'name' => $grant->grantType->name,
                            'grant_code' => $grant->grantType->grant_code,
                            'description' => $grant->grantType->description,
                            'max_amount' => $grant->grantType->max_amount,
                        ] : null,
                        'user_id' => $grant->user_id,
                        'user' => $grant->user ? [
                            'id' => $grant->user->id,
                            'name' => trim($grant->user->first_name . ' ' . ($grant->user->middle_name ?? '') . ' ' . $grant->user->last_name),
                            'first_name' => $grant->user->first_name,
                            'middle_name' => $grant->user->middle_name,
                            'last_name' => $grant->user->last_name,
                            'email' => $grant->user->email,
                            'phone_number' => $grant->user->phone_number,
                            'employee_id' => $grant->user->employee_id,
                        ] : null,
                        'dependent_id' => $grant->dependent_id,
                        'dependent' => $grant->dependent ? [
                            'id' => $grant->dependent->id,
                            'name' => trim($grant->dependent->first_name . ' ' . $grant->dependent->last_name),
                            'first_name' => $grant->dependent->first_name,
                            'last_name' => $grant->dependent->last_name,
                            'relationship' => $grant->dependent->relationship,
                        ] : null,
                        'amount' => number_format($grant->amount, 2),
                        'amount_raw' => $grant->amount,
                        'reason' => $grant->reason,
                        'status' => $grant->status,
                        'status_badge' => $this->getStatusBadge($grant->status),
                        'admin_notes' => $grant->admin_notes,
                        'approval_date' => $grant->approval_date ? $grant->approval_date->format('Y-m-d') : null,
                        'approval_date_formatted' => $grant->approval_date ? $grant->approval_date->format('d M Y') : null,
                        'cancelled_date' => $grant->cancelled_date ? $grant->cancelled_date->format('Y-m-d') : null,
                        'cancelled_date_formatted' => $grant->cancelled_date ? $grant->cancelled_date->format('d M Y') : null,
                        'disbursement_date' => $grant->disbursement_date ? $grant->disbursement_date->format('Y-m-d') : null,
                        'disbursement_date_formatted' => $grant->disbursement_date ? $grant->disbursement_date->format('d M Y') : null,
                        'created_at' => $grant->created_at->format('Y-m-d H:i:s'),
                        'created_at_formatted' => $grant->created_at->format('d M Y, h:i A'),
                        'updated_at' => $grant->updated_at->format('Y-m-d H:i:s'),
                        'updated_at_formatted' => $grant->updated_at->format('d M Y, h:i A'),
                    ];
                });

            return response()->json([
                'draw' => (int) $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $grants,
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('=== ERROR FETCHING GRANTS FOR DATATABLES ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            return response()->json([
                'draw' => (int) request()->input('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'success' => false,
                'error' => 'Failed to fetch grants: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get status badge HTML/CSS class for DataTables
     */
    private function getStatusBadge(string $status): array
    {
        $badges = [
            'pending' => [
                'text' => 'Pending',
                'class' => 'badge-warning',
                'color' => '#ffc107'
            ],
            'approved' => [
                'text' => 'Approved',
                'class' => 'badge-success',
                'color' => '#28a745'
            ],
            'rejected' => [
                'text' => 'Rejected',
                'class' => 'badge-danger',
                'color' => '#dc3545'
            ],
            'paid' => [
                'text' => 'Paid',
                'class' => 'badge-info',
                'color' => '#17a2b8'
            ],
            'cancelled' => [
                'text' => 'Cancelled',
                'class' => 'badge-secondary',
                'color' => '#6c757d'
            ]
        ];

        return $badges[$status] ?? [
            'text' => ucfirst($status),
            'class' => 'badge-secondary',
            'color' => '#6c757d'
        ];
    }

    public function getGrantById($grantId): Grant
    {
        return Grant::query()
        ->with([
            'user' => function($query) {
                $query->select('id', 'first_name', 'middle_name', 'last_name', 'email', 'employee_id');
            },
            'dependent' => function($query) {
                $query->select('id', 'first_name', 'last_name', 'phone', 'relationship', 'gender');
            },
            'grantType' => function($query) {
                $query->select('id', 'grant_code', 'name', 'description', 'max_amount');
            }
        ])
        ->findOrFail($grantId);
    }

    public function getUserGrants($userId): Collection
    {
        return Grant::query()->with('dependent')->where('user_id', $userId)->get();
    }

    public function createGrant(array $grantDetails): Grant
    {
        $grant = Grant::query()->create($grantDetails);

        GrantApplied::dispatch($grant);

        return $grant;
    }

    public function updateGrant($grant, array $newDetails): Grant
    {
        $grant->update($newDetails);
        return $grant;
    }

    public function deleteGrant(Grant $grant, bool $force): Grant
    {
        if ($force) {
            $grant->forceDelete();
        } else {
            $grant->delete();
        }

        return $grant;
    }

    public function changeGrantStatus($grantId, $status, $adminNotes = null): bool
    {
        $grant = Grant::query()->find($grantId);

        if (!$grant) {
            return false;
        }

        // Validate status if needed
        // if (!in_array($status, ['approved', 'paid', ...])) {
        //     return false;
        // }

        $updateData = ['status' => $status];

        if ($status === 'approved') {
            $updateData['approval_date'] = now();
        } elseif ($status === 'paid') {
            $updateData['disbursement_date'] = now();
        } elseif ($status === 'cancelled') {
            $updateData['cancelled_date'] = now();
        }

        if ($adminNotes) {
            $updateData['admin_notes'] = $adminNotes;
        }

        $success = $grant->update($updateData);

        // Dispatch events for grant status changes
        if ($success) {
            try {
                if ($status === 'approved') {
                    GrantApproved::dispatch($grant->fresh(), $adminNotes);
                    Log::info('GrantApproved event dispatched', [
                        'grant_id' => $grant->id
                    ]);
                } elseif ($status === 'rejected') {
                    GrantRejected::dispatch($grant->fresh(), $adminNotes);
                    Log::info('GrantRejected event dispatched', [
                        'grant_id' => $grant->id
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to dispatch grant status event: ' . $e->getMessage(), [
                    'grant_id' => $grant->id,
                    'status' => $status,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $success;
    }

    public function findOrFailGrant($grantId): Grant
    {
        return Grant::query()->with(["user", "dependent", "grantType"])->findOrFail($grantId);
    }

    /**
     * Get comprehensive grant statistics for dashboard
     *
     * @return array
     */
    public function getGrantStatistics(): array
    {
        try {
            // Total grants overview
            $totalGrants = Grant::count();
            $pendingGrants = Grant::where('status', 'pending')->count();
            $approvedGrants = Grant::where('status', 'approved')->count();
            $rejectedGrants = Grant::where('status', 'rejected')->count();
            $paidGrants = Grant::where('status', 'paid')->count();
            $cancelledGrants = Grant::where('status', 'cancelled')->count();

            // Financial overview
            $totalRequested = Grant::sum('amount');
            $totalApproved = Grant::where('status', 'approved')->sum('amount');
            $totalPaid = Grant::where('status', 'paid')->sum('amount');
            $totalPending = Grant::where('status', 'pending')->sum('amount');
            $totalRejected = Grant::where('status', 'rejected')->sum('amount');
            $averageGrantAmount = Grant::avg('amount');
            $largestGrant = Grant::max('amount');
            $smallestGrant = Grant::where('amount', '>', 0)->min('amount');

            // Grant type breakdown
            $grantTypeStats = Grant::join('grant_types', 'grants.grant_type_id', '=', 'grant_types.id')
                ->select(
                    'grant_types.name as grant_type',
                    'grant_types.grant_code',
                    DB::raw('count(*) as count'),
                    DB::raw('sum(grants.amount) as total_amount'),
                    DB::raw('sum(CASE WHEN grants.status = "approved" THEN grants.amount ELSE 0 END) as approved_amount'),
                    DB::raw('sum(CASE WHEN grants.status = "paid" THEN grants.amount ELSE 0 END) as paid_amount')
                )
                ->groupBy('grant_types.name', 'grant_types.id', 'grant_types.grant_code')
                ->get()
                ->map(function ($item) {
                    return [
                        'grant_type' => $item->grant_type,
                        'grant_code' => $item->grant_code,
                        'count' => $item->count,
                        'total_amount' => round($item->total_amount, 2),
                        'approved_amount' => round($item->approved_amount, 2),
                        'paid_amount' => round($item->paid_amount, 2),
                    ];
                })
                ->toArray();

            // Status distribution
            $statusDistribution = [
                'pending' => $pendingGrants,
                'approved' => $approvedGrants,
                'rejected' => $rejectedGrants,
                'paid' => $paidGrants,
                'cancelled' => $cancelledGrants,
            ];

            // Monthly trends (last 12 months) - based on created_at
            $monthlyTrends = Grant::where('created_at', '>=', now()->subMonths(12))
                ->select(
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                    DB::raw('count(*) as count'),
                    DB::raw('sum(amount) as total_amount'),
                    DB::raw('sum(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved_count'),
                    DB::raw('sum(CASE WHEN status = "paid" THEN 1 ELSE 0 END) as paid_count')
                )
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get()
                ->toArray();

            // This month vs last month
            $thisMonthGrants = Grant::whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count();

            $lastMonthGrants = Grant::whereYear('created_at', now()->subMonth()->year)
                ->whereMonth('created_at', now()->subMonth()->month)
                ->count();

            $grantGrowth = $lastMonthGrants > 0
                ? round((($thisMonthGrants - $lastMonthGrants) / $lastMonthGrants) * 100, 2)
                : 0;

            // Approval trends (based on approval_date)
            $approvalTrends = Grant::where('approval_date', '>=', now()->subMonths(12))
                ->whereNotNull('approval_date')
                ->select(
                    DB::raw('DATE_FORMAT(approval_date, "%Y-%m") as month'),
                    DB::raw('count(*) as count'),
                    DB::raw('sum(amount) as total_amount')
                )
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get()
                ->toArray();

            // Disbursement trends (based on disbursement_date)
            $disbursementTrends = Grant::where('disbursement_date', '>=', now()->subMonths(12))
                ->whereNotNull('disbursement_date')
                ->select(
                    DB::raw('DATE_FORMAT(disbursement_date, "%Y-%m") as month'),
                    DB::raw('count(*) as count'),
                    DB::raw('sum(amount) as total_amount')
                )
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get()
                ->toArray();

            // Top grants by amount
            $topGrants = Grant::with(['user', 'grantType', 'dependent'])
                ->orderBy('amount', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($grant) {
                    return [
                        'id' => $grant->id,
                        'user_name' => $grant->user ? trim($grant->user->first_name . ' ' . ($grant->user->middle_name ?? '') . ' ' . $grant->user->last_name) : 'N/A',
                        'employee_id' => $grant->user->employee_id ?? 'N/A',
                        'grant_type' => $grant->grantType->name ?? 'N/A',
                        'grant_code' => $grant->grantType->grant_code ?? 'N/A',
                        'dependent_name' => $grant->dependent ? trim($grant->dependent->first_name . ' ' . $grant->dependent->last_name) : null,
                        'amount' => round($grant->amount, 2),
                        'status' => $grant->status,
                        'approval_date' => $grant->approval_date ? $grant->approval_date->format('Y-m-d') : null,
                        'disbursement_date' => $grant->disbursement_date ? $grant->disbursement_date->format('Y-m-d') : null,
                        'created_at' => $grant->created_at->format('Y-m-d'),
                    ];
                })
                ->toArray();

            // Recent grants (last 10)
            $recentGrants = Grant::with(['user', 'grantType', 'dependent'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($grant) {
                    return [
                        'id' => $grant->id,
                        'user_name' => $grant->user ? trim($grant->user->first_name . ' ' . ($grant->user->middle_name ?? '') . ' ' . $grant->user->last_name) : 'N/A',
                        'employee_id' => $grant->user->employee_id ?? 'N/A',
                        'grant_type' => $grant->grantType->name ?? 'N/A',
                        'amount' => round($grant->amount, 2),
                        'status' => $grant->status,
                        'created_at' => $grant->created_at->format('Y-m-d H:i:s'),
                        'days_ago' => $grant->created_at->diffForHumans(),
                    ];
                })
                ->toArray();

            // Performance metrics
            $approvalRate = ($totalGrants - $pendingGrants) > 0
                ? round((($approvedGrants + $paidGrants) / ($totalGrants - $pendingGrants)) * 100, 2)
                : 0;

            $rejectionRate = ($totalGrants - $pendingGrants) > 0
                ? round(($rejectedGrants / ($totalGrants - $pendingGrants)) * 100, 2)
                : 0;

            $disbursementRate = $totalApproved > 0
                ? round(($totalPaid / $totalApproved) * 100, 2)
                : 0;

            // Grants with dependents vs without
            $grantsWithDependents = Grant::whereNotNull('dependent_id')->count();
            $grantsWithoutDependents = Grant::whereNull('dependent_id')->count();

            // Average time to approval (in days)
            $avgApprovalTime = Grant::whereNotNull('approval_date')
                ->whereNotNull('created_at')
                ->selectRaw('AVG(DATEDIFF(approval_date, created_at)) as avg_days')
                ->value('avg_days') ?? 0;

            // Average time to disbursement (in days)
            $avgDisbursementTime = Grant::whereNotNull('disbursement_date')
                ->whereNotNull('approval_date')
                ->selectRaw('AVG(DATEDIFF(disbursement_date, approval_date)) as avg_days')
                ->value('avg_days') ?? 0;

            // Pending grants awaiting approval
            $pendingApproval = Grant::where('status', 'pending')
                ->where('created_at', '<=', now()->subDays(7))
                ->count();

            // Approved grants awaiting disbursement
            $pendingDisbursement = Grant::where('status', 'approved')
                ->whereNull('disbursement_date')
                ->count();

            return [
                'overview' => [
                    'total_grants' => $totalGrants,
                    'pending_grants' => $pendingGrants,
                    'approved_grants' => $approvedGrants,
                    'rejected_grants' => $rejectedGrants,
                    'paid_grants' => $paidGrants,
                    'cancelled_grants' => $cancelledGrants,
                    'pending_approval' => $pendingApproval,
                    'pending_disbursement' => $pendingDisbursement,
                ],
                'financial' => [
                    'total_requested' => round($totalRequested, 2),
                    'total_approved' => round($totalApproved, 2),
                    'total_paid' => round($totalPaid, 2),
                    'total_pending' => round($totalPending, 2),
                    'total_rejected' => round($totalRejected, 2),
                    'average_grant_amount' => round($averageGrantAmount, 2),
                    'largest_grant' => round($largestGrant, 2),
                    'smallest_grant' => round($smallestGrant, 2),
                ],
                'grant_types' => [
                    'breakdown' => $grantTypeStats,
                    'total_types' => count($grantTypeStats),
                ],
                'status_distribution' => $statusDistribution,
                'trends' => [
                    'this_month' => $thisMonthGrants,
                    'last_month' => $lastMonthGrants,
                    'growth_percentage' => $grantGrowth,
                    'monthly_trends' => $monthlyTrends,
                    'approval_trends' => $approvalTrends,
                    'disbursement_trends' => $disbursementTrends,
                ],
                'performance' => [
                    'approval_rate' => $approvalRate,
                    'rejection_rate' => $rejectionRate,
                    'disbursement_rate' => $disbursementRate,
                    'avg_approval_time_days' => round($avgApprovalTime, 2),
                    'avg_disbursement_time_days' => round($avgDisbursementTime, 2),
                ],
                'dependents' => [
                    'grants_with_dependents' => $grantsWithDependents,
                    'grants_without_dependents' => $grantsWithoutDependents,
                ],
                'top_grants' => $topGrants,
                'recent_activity' => [
                    'recent_grants' => $recentGrants,
                ],
                'generated_at' => now()->toDateTimeString(),
            ];

        } catch (\Exception $e) {
            Log::error('Error generating grant statistics: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            throw new \Exception('Error generating grant statistics: ' . $e->getMessage());
        }
    }

    /**
     * Get comprehensive grant details for administrators
     * Includes all related data: user, dependent, grant type, and timeline information
     */
    public function getAdminGrantDetails(string $grantId): array
    {
        try {
            $grant = Grant::with([
                'user:id,first_name,middle_name,last_name,email,phone_number,employee_id,old_employee_id,salary,passport_image,created_at',
                'dependent:id,first_name,last_name,phone,relationship,gender,date_of_birth,created_at',
                'grantType:id,name,grant_code,description,max_amount,requires_dependent,is_active,created_at',
            ])->findOrFail($grantId);

            // Format grant data
            $grantData = [
                'id' => $grant->id,
                'amount' => number_format($grant->amount, 2),
                'amount_raw' => $grant->amount,
                'reason' => $grant->reason,
                'status' => $grant->status,
                'status_badge' => $this->getStatusBadge($grant->status),
                'admin_notes' => $grant->admin_notes,
                'approval_date' => $grant->approval_date ? $grant->approval_date->format('Y-m-d') : null,
                'approval_date_formatted' => $grant->approval_date ? $grant->approval_date->format('d M Y') : null,
                'cancelled_date' => $grant->cancelled_date ? $grant->cancelled_date->format('Y-m-d') : null,
                'cancelled_date_formatted' => $grant->cancelled_date ? $grant->cancelled_date->format('d M Y') : null,
                'disbursement_date' => $grant->disbursement_date ? $grant->disbursement_date->format('Y-m-d') : null,
                'disbursement_date_formatted' => $grant->disbursement_date ? $grant->disbursement_date->format('d M Y') : null,
                'created_at' => $grant->created_at->format('Y-m-d H:i:s'),
                'created_at_formatted' => $grant->created_at->format('d M Y, h:i A'),
                'updated_at' => $grant->updated_at->format('Y-m-d H:i:s'),
                'updated_at_formatted' => $grant->updated_at->format('d M Y, h:i A'),
            ];

            // Calculate timeline information
            $timeline = [];
            
            if ($grant->created_at) {
                $timeline[] = [
                    'event' => 'Application Submitted',
                    'date' => $grant->created_at->format('Y-m-d H:i:s'),
                    'date_formatted' => $grant->created_at->format('d M Y, h:i A'),
                    'days_ago' => $grant->created_at->diffForHumans(),
                ];
            }

            if ($grant->approval_date) {
                $approvalDays = $grant->created_at ? $grant->created_at->diffInDays($grant->approval_date) : null;
                $timeline[] = [
                    'event' => 'Approved',
                    'date' => $grant->approval_date->format('Y-m-d'),
                    'date_formatted' => $grant->approval_date->format('d M Y'),
                    'days_ago' => $grant->approval_date->diffForHumans(),
                    'processing_days' => $approvalDays,
                ];
            }

            if ($grant->disbursement_date) {
                $disbursementDays = $grant->approval_date ? $grant->approval_date->diffInDays($grant->disbursement_date) : null;
                $timeline[] = [
                    'event' => 'Disbursed',
                    'date' => $grant->disbursement_date->format('Y-m-d'),
                    'date_formatted' => $grant->disbursement_date->format('d M Y'),
                    'days_ago' => $grant->disbursement_date->diffForHumans(),
                    'processing_days' => $disbursementDays,
                ];
            }

            if ($grant->cancelled_date) {
                $timeline[] = [
                    'event' => 'Cancelled',
                    'date' => $grant->cancelled_date->format('Y-m-d'),
                    'date_formatted' => $grant->cancelled_date->format('d M Y'),
                    'days_ago' => $grant->cancelled_date->diffForHumans(),
                ];
            }

            // Calculate processing times
            $processingTimes = [
                'application_to_approval_days' => null,
                'approval_to_disbursement_days' => null,
                'total_processing_days' => null,
            ];

            if ($grant->approval_date && $grant->created_at) {
                $processingTimes['application_to_approval_days'] = $grant->created_at->diffInDays($grant->approval_date);
            }

            if ($grant->disbursement_date && $grant->approval_date) {
                $processingTimes['approval_to_disbursement_days'] = $grant->approval_date->diffInDays($grant->disbursement_date);
            }

            if ($grant->disbursement_date && $grant->created_at) {
                $processingTimes['total_processing_days'] = $grant->created_at->diffInDays($grant->disbursement_date);
            }

            // Format user/applicant data
            $userData = $grant->user ? [
                'id' => $grant->user->id,
                'name' => trim($grant->user->first_name . ' ' . ($grant->user->middle_name ?? '') . ' ' . $grant->user->last_name),
                'first_name' => $grant->user->first_name,
                'middle_name' => $grant->user->middle_name,
                'last_name' => $grant->user->last_name,
                'email' => $grant->user->email,
                'phone_number' => $grant->user->phone_number,
                'employee_id' => $grant->user->employee_id,
                'old_employee_id' => $grant->user->old_employee_id,
                'salary' => $grant->user->salary ? number_format($grant->user->salary, 2) : null,
                'passport_image' => $grant->user->passport_image,
                'account_created_at' => $grant->user->created_at->format('Y-m-d H:i:s'),
            ] : null;

            // Format dependent data
            $dependentData = $grant->dependent ? [
                'id' => $grant->dependent->id,
                'name' => trim($grant->dependent->first_name . ' ' . $grant->dependent->last_name),
                'first_name' => $grant->dependent->first_name,
                'last_name' => $grant->dependent->last_name,
                'phone' => $grant->dependent->phone,
                'relationship' => $grant->dependent->relationship,
                'gender' => $grant->dependent->gender,
                'date_of_birth' => $grant->dependent->date_of_birth ? $grant->dependent->date_of_birth->format('Y-m-d') : null,
                'date_of_birth_formatted' => $grant->dependent->date_of_birth ? $grant->dependent->date_of_birth->format('d M Y') : null,
                'created_at' => $grant->dependent->created_at->format('Y-m-d H:i:s'),
            ] : null;

            // Format grant type data
            $grantTypeData = $grant->grantType ? [
                'id' => $grant->grantType->id,
                'name' => $grant->grantType->name,
                'grant_code' => $grant->grantType->grant_code,
                'description' => $grant->grantType->description,
                'max_amount' => $grant->grantType->max_amount ? number_format($grant->grantType->max_amount, 2) : null,
                'max_amount_raw' => $grant->grantType->max_amount,
                'requires_dependent' => $grant->grantType->requires_dependent ?? false,
                'is_active' => $grant->grantType->is_active ?? true,
                'created_at' => $grant->grantType->created_at->format('Y-m-d H:i:s'),
            ] : null;

            // Status history summary
            $statusHistory = [
                'current_status' => $grant->status,
                'has_been_approved' => !is_null($grant->approval_date),
                'has_been_disbursed' => !is_null($grant->disbursement_date),
                'has_been_cancelled' => !is_null($grant->cancelled_date),
                'is_pending' => $grant->status === 'pending',
                'is_approved' => $grant->status === 'approved',
                'is_rejected' => $grant->status === 'rejected',
                'is_paid' => $grant->status === 'paid',
                'is_cancelled' => $grant->status === 'cancelled',
            ];

            // Get user's other grants for context
            $userOtherGrants = Grant::where('user_id', $grant->user_id)
                ->where('id', '!=', $grant->id)
                ->with('grantType:id,name,grant_code')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($otherGrant) {
                    return [
                        'id' => $otherGrant->id,
                        'grant_type' => $otherGrant->grantType->name ?? 'N/A',
                        'grant_code' => $otherGrant->grantType->grant_code ?? 'N/A',
                        'amount' => number_format($otherGrant->amount, 2),
                        'status' => $otherGrant->status,
                        'created_at' => $otherGrant->created_at->format('Y-m-d'),
                        'approval_date' => $otherGrant->approval_date ? $otherGrant->approval_date->format('Y-m-d') : null,
                    ];
                });

            return [
                'grant' => $grantData,
                'user' => $userData,
                'dependent' => $dependentData,
                'grant_type' => $grantTypeData,
                'timeline' => $timeline,
                'processing_times' => $processingTimes,
                'status_history' => $statusHistory,
                'user_other_grants' => $userOtherGrants,
                'user_total_grants' => Grant::where('user_id', $grant->user_id)->count(),
                'user_total_approved' => Grant::where('user_id', $grant->user_id)->where('status', 'approved')->orWhere('status', 'paid')->count(),
                'user_total_amount' => number_format(Grant::where('user_id', $grant->user_id)->sum('amount'), 2),
            ];
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error("Grant not found for admin details: {$e->getMessage()}");
            throw new \Exception('Grant not found.');
        } catch (\Exception $e) {
            Log::error("Error getting admin grant details: {$e->getMessage()}");
            Log::error($e->getTraceAsString());
            throw $e;
        }
    }
}

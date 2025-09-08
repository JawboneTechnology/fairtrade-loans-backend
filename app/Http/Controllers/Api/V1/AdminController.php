<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function dashboard()
    {
        // Fetch data for the admin dashboard
        $totalLoans = Loan::count();
        $pendingLoans = Loan::where('loan_status', 'pending')->count();
        $activeLoans = Loan::where('loan_status', 'approved')->count();
        $recentLoans = Loan::with('employee')->latest()->take(5)->get();

        // Pass data to the view
        return view('admin.dashboard', [
            'totalLoans' => $totalLoans,
            'pendingLoans' => $pendingLoans,
            'activeLoans' => $activeLoans,
            'recentLoans' => $recentLoans,
        ]);
    }

    public function getAdmins(): \Illuminate\Http\JsonResponse
    {
        $admins = User::query()->whereHas('roles', function($query) {
            $query->where('name', 'super-admin');
            $query->orWhere('name', 'admin');
        })->get();

        return response()->json($admins);
    }
}

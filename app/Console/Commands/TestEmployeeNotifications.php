<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Loan;
use App\Models\Grant;
use App\Models\Transaction;
use App\Models\LoanDeduction;
use App\Models\MpesaTransaction;
use App\Events\LoanApproved;
use App\Events\LoanRejected;
use App\Events\LoanPaid;
use App\Events\LoanCanceled;
use App\Events\GrantApproved;
use App\Events\GrantRejected;
use App\Events\PaymentSuccessful;
use App\Events\DeductionProcessed;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TestEmployeeNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:test-employee 
                            {--user-id= : User ID to test notifications for}
                            {--email= : User email to test notifications for}
                            {--type= : Specific notification type to test (loan_approved, loan_rejected, grant_approved, grant_rejected, payment_received, deduction_processed, loan_application_submitted, loan_paid, loan_canceled)}
                            {--all : Test all notification types}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test employee notifications by triggering events or creating test notifications';

    protected NotificationService $notificationService;

    /**
     * Create a new command instance.
     */
    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user-id');
        $email = $this->option('email');
        $type = $this->option('type');
        $testAll = $this->option('all');

        try {
            // Find user
            if ($userId) {
                $user = User::find($userId);
            } elseif ($email) {
                $user = User::where('email', $email)->first();
            } else {
                // Get first employee user as default
                $user = User::whereHas('roles', function($q) {
                    $q->where('name', 'employee');
                })->first() ?? User::first();
            }

            if (!$user) {
                $this->error('User not found. Please provide a valid user-id or email.');
                return 1;
            }

            $this->info("Testing employee notifications for: {$user->first_name} {$user->last_name} ({$user->email})");
            $this->newLine();

            if ($testAll) {
                $this->testAllNotificationTypes($user);
            } elseif ($type) {
                $this->testSpecificNotificationType($user, $type);
            } else {
                $this->displayMenu($user);
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Error testing notifications: " . $e->getMessage());
            Log::error('Test employee notifications error: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Display interactive menu
     */
    protected function displayMenu(User $user): void
    {
        $this->info('Available notification types:');
        $this->line('1. loan_application_submitted');
        $this->line('2. loan_approved');
        $this->line('3. loan_rejected');
        $this->line('4. loan_paid');
        $this->line('5. loan_canceled');
        $this->line('6. grant_approved');
        $this->line('7. grant_rejected');
        $this->line('8. payment_received');
        $this->line('9. deduction_processed');
        $this->line('10. All types');
        $this->newLine();

        $choice = $this->choice('Select notification type to test', [
            'loan_application_submitted',
            'loan_approved',
            'loan_rejected',
            'loan_paid',
            'loan_canceled',
            'grant_approved',
            'grant_rejected',
            'payment_received',
            'deduction_processed',
            'all'
        ], 'all');

        if ($choice === 'all') {
            $this->testAllNotificationTypes($user);
        } else {
            $this->testSpecificNotificationType($user, $choice);
        }
    }

    /**
     * Test all notification types
     */
    protected function testAllNotificationTypes(User $user): void
    {
        $types = [
            'loan_application_submitted',
            'loan_approved',
            'loan_rejected',
            'loan_paid',
            'loan_canceled',
            'grant_approved',
            'grant_rejected',
            'payment_received',
            'deduction_processed',
        ];

        $this->info('Testing all notification types...');
        $this->newLine();

        foreach ($types as $type) {
            $this->line("Testing: {$type}");
            try {
                $this->testSpecificNotificationType($user, $type, false);
                $this->info("  ✅ {$type} - Success");
            } catch (\Exception $e) {
                $this->error("  ❌ {$type} - Failed: " . $e->getMessage());
            }
            $this->newLine();
        }

        $unreadCount = $this->notificationService->getUnreadCount($user);
        $this->info("Total unread notifications: {$unreadCount}");
    }

    /**
     * Test specific notification type
     */
    protected function testSpecificNotificationType(User $user, string $type, bool $showDetails = true): void
    {
        if ($showDetails) {
            $this->info("Testing notification type: {$type}");
            $this->newLine();
        }

        switch ($type) {
            case 'loan_application_submitted':
                $this->testLoanApplicationSubmitted($user);
                break;
            case 'loan_approved':
                $this->testLoanApproved($user);
                break;
            case 'loan_rejected':
                $this->testLoanRejected($user);
                break;
            case 'loan_paid':
                $this->testLoanPaid($user);
                break;
            case 'loan_canceled':
                $this->testLoanCanceled($user);
                break;
            case 'grant_approved':
                $this->testGrantApproved($user);
                break;
            case 'grant_rejected':
                $this->testGrantRejected($user);
                break;
            case 'payment_received':
                $this->testPaymentReceived($user);
                break;
            case 'deduction_processed':
                $this->testDeductionProcessed($user);
                break;
            default:
                $this->error("Unknown notification type: {$type}");
                return;
        }

        if ($showDetails) {
            $unreadCount = $this->notificationService->getUnreadCount($user);
            $this->info("✅ Notification created successfully!");
            $this->line("Unread notifications: {$unreadCount}");
        }
    }

    /**
     * Test loan application submitted notification
     */
    protected function testLoanApplicationSubmitted(User $user): void
    {
        $loan = Loan::where('employee_id', $user->id)->first();
        
        if (!$loan) {
            // Create a mock notification directly
            $this->notificationService->create($user, 'loan_application_submitted', [
                'loan_id' => 'test-loan-id',
                'loan_number' => 'LOAN-TEST-' . now()->format('YmdHis'),
                'amount' => '50,000.00',
                'loan_type' => 'Test Loan Type',
                'guarantors' => 'Test Guarantor 1, Test Guarantor 2',
                'action_url' => config('app.url') . '/loans/test',
            ]);
            $this->line("  Created mock loan application notification (no actual loan found)");
        } else {
            // Dispatch the actual event if we have a loan
            \App\Jobs\NotifyApplicantLoanPlaced::dispatch($loan);
            $this->line("  Dispatched NotifyApplicantLoanPlaced job for loan: {$loan->loan_number}");
        }
    }

    /**
     * Test loan approved notification
     */
    protected function testLoanApproved(User $user): void
    {
        $loan = Loan::where('employee_id', $user->id)->first();
        
        if (!$loan) {
            $this->notificationService->create($user, 'loan_approved', [
                'loan_id' => 'test-loan-id',
                'loan_number' => 'LOAN-TEST-' . now()->format('YmdHis'),
                'amount' => '50,000.00',
                'approved_amount' => '45,000.00',
                'remarks' => 'Test approval remarks',
                'action_url' => config('app.url') . '/loans/test',
            ]);
            $this->line("  Created mock loan approved notification");
        } else {
            LoanApproved::dispatch($loan, 45000.00, 'Test approval remarks');
            $this->line("  Dispatched LoanApproved event for loan: {$loan->loan_number}");
        }
    }

    /**
     * Test loan rejected notification
     */
    protected function testLoanRejected(User $user): void
    {
        $loan = Loan::where('employee_id', $user->id)->first();
        
        if (!$loan) {
            $this->notificationService->create($user, 'loan_rejected', [
                'loan_id' => 'test-loan-id',
                'loan_number' => 'LOAN-TEST-' . now()->format('YmdHis'),
                'amount' => '50,000.00',
                'remarks' => 'Test rejection remarks - insufficient documentation',
                'action_url' => config('app.url') . '/loans/test',
            ]);
            $this->line("  Created mock loan rejected notification");
        } else {
            LoanRejected::dispatch($loan, 'Test rejection remarks');
            $this->line("  Dispatched LoanRejected event for loan: {$loan->loan_number}");
        }
    }

    /**
     * Test loan paid notification
     */
    protected function testLoanPaid(User $user): void
    {
        $loan = Loan::where('employee_id', $user->id)->first();
        
        if (!$loan) {
            $this->notificationService->create($user, 'loan_paid', [
                'loan_id' => 'test-loan-id',
                'loan_number' => 'LOAN-TEST-' . now()->format('YmdHis'),
                'amount' => '50,000.00',
                'action_url' => config('app.url') . '/loans/test',
            ]);
            $this->line("  Created mock loan paid notification");
        } else {
            // We need deduction and transaction for LoanPaid event
            $deduction = LoanDeduction::where('loan_id', $loan->id)->first();
            $transaction = Transaction::where('loan_id', $loan->id)->first();
            
            if ($deduction && $transaction) {
                LoanPaid::dispatch($loan, $deduction, $transaction);
                $this->line("  Dispatched LoanPaid event for loan: {$loan->loan_number}");
            } else {
                $this->notificationService->create($user, 'loan_paid', [
                    'loan_id' => $loan->id,
                    'loan_number' => $loan->loan_number,
                    'amount' => number_format($loan->loan_amount, 2),
                    'action_url' => config('app.url') . '/loans/' . $loan->id,
                ]);
                $this->line("  Created loan paid notification (no deduction/transaction found)");
            }
        }
    }

    /**
     * Test loan canceled notification
     */
    protected function testLoanCanceled(User $user): void
    {
        $loan = Loan::where('employee_id', $user->id)->first();
        
        if (!$loan) {
            $this->notificationService->create($user, 'loan_canceled', [
                'loan_id' => 'test-loan-id',
                'loan_number' => 'LOAN-TEST-' . now()->format('YmdHis'),
                'amount' => '50,000.00',
                'action_url' => config('app.url') . '/loans/test',
            ]);
            $this->line("  Created mock loan canceled notification");
        } else {
            LoanCanceled::dispatch($loan);
            $this->line("  Dispatched LoanCanceled event for loan: {$loan->loan_number}");
        }
    }

    /**
     * Test grant approved notification
     */
    protected function testGrantApproved(User $user): void
    {
        $grant = Grant::where('user_id', $user->id)->first();
        
        if (!$grant) {
            $this->notificationService->create($user, 'grant_approved', [
                'grant_id' => 'test-grant-id',
                'grant_number' => 'GRANT-TEST-' . now()->format('YmdHis'),
                'amount' => '25,000.00',
                'admin_notes' => 'Test grant approval notes',
                'action_url' => config('app.url') . '/grants/test',
            ]);
            $this->line("  Created mock grant approved notification");
        } else {
            GrantApproved::dispatch($grant, 'Test grant approval notes');
            $this->line("  Dispatched GrantApproved event for grant: {$grant->id}");
        }
    }

    /**
     * Test grant rejected notification
     */
    protected function testGrantRejected(User $user): void
    {
        $grant = Grant::where('user_id', $user->id)->first();
        
        if (!$grant) {
            $this->notificationService->create($user, 'grant_rejected', [
                'grant_id' => 'test-grant-id',
                'grant_number' => 'GRANT-TEST-' . now()->format('YmdHis'),
                'amount' => '25,000.00',
                'remarks' => 'Test grant rejection remarks - eligibility criteria not met',
                'action_url' => config('app.url') . '/grants/test',
            ]);
            $this->line("  Created mock grant rejected notification");
        } else {
            GrantRejected::dispatch($grant, 'Test grant rejection remarks');
            $this->line("  Dispatched GrantRejected event for grant: {$grant->id}");
        }
    }

    /**
     * Test payment received notification
     */
    protected function testPaymentReceived(User $user): void
    {
        $loan = Loan::where('employee_id', $user->id)->first();
        $transaction = MpesaTransaction::where('account_reference', 'LIKE', '%' . ($loan ? $loan->loan_number : 'TEST') . '%')->first();
        
        if (!$loan || !$transaction) {
            $this->notificationService->create($user, 'payment_received', [
                'transaction_id' => 'TEST-TXN-' . now()->format('YmdHis'),
                'loan_id' => 'test-loan-id',
                'loan_number' => 'LOAN-TEST-' . now()->format('YmdHis'),
                'amount' => '5,000.00',
                'new_balance' => '45,000.00',
                'payment_method' => 'M-Pesa',
                'action_url' => config('app.url') . '/loans/test',
            ]);
            $this->line("  Created mock payment received notification");
        } else {
            PaymentSuccessful::dispatch($transaction, $loan, $user, 45000.00, 'M-Pesa');
            $this->line("  Dispatched PaymentSuccessful event for loan: {$loan->loan_number}");
        }
    }

    /**
     * Test deduction processed notification
     */
    protected function testDeductionProcessed(User $user): void
    {
        $loan = Loan::where('employee_id', $user->id)->first();
        $deduction = LoanDeduction::where('loan_id', $loan?->id)->first();
        
        if (!$loan || !$deduction) {
            $this->notificationService->create($user, 'deduction_processed', [
                'deduction_id' => 'test-deduction-id',
                'loan_id' => 'test-loan-id',
                'loan_number' => 'LOAN-TEST-' . now()->format('YmdHis'),
                'amount' => '3,000.00',
                'new_balance' => '42,000.00',
                'deduction_type' => 'Salary',
                'action_url' => config('app.url') . '/loans/test',
            ]);
            $this->line("  Created mock deduction processed notification");
        } else {
            DeductionProcessed::dispatch($deduction, $loan, $user, 42000.00, $deduction->deduction_type ?? 'Salary');
            $this->line("  Dispatched DeductionProcessed event for loan: {$loan->loan_number}");
        }
    }
}


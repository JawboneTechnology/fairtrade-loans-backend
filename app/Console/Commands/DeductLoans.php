<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LoanService;

class DeductLoans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:deduct';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process loan deductions for all active loans';

    protected $loanService;

    public function __construct(LoanService $loanService)
    {
        parent::__construct();
        $this->loanService = $loanService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->loanService->processDeductions();
        $this->info('Loan deductions processed successfully.');
        $logger->info('Loan deductions processed successfully.');
    }
}

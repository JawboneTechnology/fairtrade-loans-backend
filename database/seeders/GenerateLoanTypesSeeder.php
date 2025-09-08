<?php

namespace Database\Seeders;

use App\Models\LoanType;
use Illuminate\Database\Seeder;

class GenerateLoanTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $loanTypes = [
            [
                'name' => 'Personal Loan',
                'interest_rate' => 12.5,
                'approval_type' => 'automatic',
                'requires_guarantors' => false,
                'required_guarantors_count' => 0,
                'guarantor_qualifications' => null,
                'type' => 'loan', // loan/grant
                'payment_type' => 'self_payment',
            ],
            [
                'name' => 'Business Loan',
                'interest_rate' => 15.0,
                'approval_type' => 'manual',
                'requires_guarantors' => true,
                'required_guarantors_count' => 2,
                'guarantor_qualifications' => [
                    "min_credit_score" => 60,
                    "employment_years" => 4
                ],
                'type' => 'loan', // loan/grant
                'payment_type' => 'deduction_from_payroll',
            ],
            [
                'name' => 'Emergency Loan',
                'interest_rate' => 8.0,
                'approval_type' => 'automatic',
                'requires_guarantors' => false,
                'required_guarantors_count' => 0,
                'guarantor_qualifications' => null,
                'type' => 'loan', // loan/grant
                'payment_type' => 'self_payment',
            ]
        ];

        foreach ($loanTypes as $loanType) {
            LoanType::create($loanType);
        }
    }
}

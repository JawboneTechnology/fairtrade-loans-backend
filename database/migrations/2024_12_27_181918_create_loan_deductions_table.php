<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loan_deductions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('loan_id');
            $table->uuid('employee_id');
            $table->decimal('deduction_amount', 10, 2);
            $table->enum('deduction_type', ['Manual', 'Automatic', 'Bank_Transfer', 'Mobile_Money', 'Online_Payment', 'Cheque', 'Cash', 'Partial_Payments', 'Early_Repayments', 'Penalty_Payments', 'Refunds'])->default('Manual');
            $table->timestamp('deducted_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_deductions');
    }
};

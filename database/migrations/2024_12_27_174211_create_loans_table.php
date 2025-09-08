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
        Schema::create('loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('loan_number', 255);

            // Use uuid() instead of foreignId() for employee_id
            $table->uuid('employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // If loan_type_id should be a UUID as well, adjust accordingly.
            // Otherwise, if it's an auto-incrementing integer in the loan_types table, then it's fine.
            $table->foreignId('loan_type_id')->nullable();

            $table->decimal('loan_amount', 10, 2);
            $table->decimal('loan_balance', 10, 2)->default(0);
            $table->date('next_due_date')->nullable();
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->integer('tenure_months');
            $table->decimal('monthly_installment', 10, 2)->default(0);
            $table->enum('loan_status', ['pending', 'processing', 'approved', 'rejected', 'completed', 'repaid', 'defaulted', 'canceled'])->default('pending');
            $table->decimal('approved_amount', 10, 2)->nullable();

            // Change approved_by to uuid so it matches users.id
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->json('guarantors')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamp('applied_at')->useCurrent();
            $table->json('qualifications')->nullable();
            $table->timestamps();

            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};

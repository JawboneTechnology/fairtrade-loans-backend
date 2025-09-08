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
        Schema::create('loan_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->enum('approval_type', ['manual', 'automatic'])->default('manual');
            $table->boolean('requires_guarantors')->default(false);
            $table->json('guarantor_qualifications')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->enum('type', ['loan', 'grant'])->default('loan');
            $table->string('required_guarantors_count')->nullable();
            $table->enum('payment_type', ['deduction_from_payroll', 'self_payment'])->default('deduction_from_payroll');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_types');
    }
};

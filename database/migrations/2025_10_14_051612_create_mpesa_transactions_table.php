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
        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->uuid('transaction_id')->primary();
            $table->string('checkout_request_id')->nullable();
            $table->string('merchant_request_id')->nullable();
            $table->string('mpesa_receipt_number')->nullable();
            $table->string('phone_number');
            $table->decimal('amount', 10, 2);
            $table->string('account_reference')->nullable();
            $table->text('transaction_description')->nullable();
            $table->enum('transaction_type', ['STK_PUSH', 'C2B', 'B2C', 'B2B'])->default('STK_PUSH');
            $table->enum('status', ['PENDING', 'SUCCESS', 'FAILED', 'CANCELLED'])->default('PENDING');
            $table->integer('result_code')->nullable();
            $table->text('result_desc')->nullable();
            $table->timestamp('transaction_date')->nullable();
            $table->json('callback_data')->nullable(); // Store full callback response
            $table->uuid('user_id')->nullable(); // Link to user who initiated the transaction
            $table->uuid('loan_id')->nullable(); // Link to loan if it's a loan payment
            $table->timestamps();
            
            // Foreign key constraints (assuming you have users and loans tables with UUID primary keys)
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('loan_id')->references('id')->on('loans')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mpesa_transactions');
    }
};

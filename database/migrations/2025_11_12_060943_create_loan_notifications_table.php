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
        Schema::create('loan_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('loan_id');
            $table->uuid('user_id');
            $table->string('notification_type'); // 'early_reminder', 'final_reminder', 'overdue', 'manual'
            $table->string('channel'); // 'sms', 'email', 'both'
            $table->string('phone_number')->nullable();
            $table->text('message');
            $table->enum('status', ['pending', 'sent', 'failed', 'delivered'])->default('pending');
            $table->decimal('amount_due', 15, 2);
            $table->decimal('outstanding_balance', 15, 2);
            $table->date('due_date');
            $table->integer('days_until_due')->nullable(); // negative for overdue
            $table->string('loan_number');
            $table->json('metadata')->nullable(); // Additional data like SMS provider response
            $table->timestamp('sent_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('command_triggered_by')->nullable(); // 'scheduler', 'manual', 'api'
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('loan_id')->references('id')->on('loans')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for efficient querying
            $table->index(['loan_id', 'notification_type']);
            $table->index(['user_id', 'sent_at']);
            $table->index(['status', 'created_at']);
            $table->index(['notification_type', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_notifications');
    }
};

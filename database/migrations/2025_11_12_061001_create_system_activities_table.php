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
        Schema::create('system_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('activity_type'); // 'command_executed', 'batch_notification', 'scheduled_task', 'system_error'
            $table->string('command_name')->nullable(); // e.g., 'loans:notify-installments'
            $table->string('triggered_by'); // 'scheduler', 'manual', 'api', 'admin'
            $table->uuid('triggered_by_user_id')->nullable(); // If triggered by a specific user
            $table->text('description');
            $table->json('summary_data')->nullable(); // Counts, statistics, etc.
            $table->enum('status', ['started', 'completed', 'failed', 'partially_failed'])->default('started');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->integer('success_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->text('error_details')->nullable();
            $table->json('affected_entities')->nullable(); // IDs of loans, users, etc. affected
            $table->string('server_info')->nullable(); // Server name, IP, etc.
            $table->timestamps();

            // Foreign key
            $table->foreign('triggered_by_user_id')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['activity_type', 'created_at']);
            $table->index(['command_name', 'status']);
            $table->index(['triggered_by', 'started_at']);
            $table->index(['status', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_activities');
    }
};

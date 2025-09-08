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
        Schema::create('grants', function (Blueprint $table) {
            $table->uuid('id')->primary()->unique();
            $table->uuid('grant_type_id');
            $table->uuid('user_id');
            $table->uuid('dependent_id')->nullable(); // Changed to nullable
            $table->decimal('amount', 10, 2);
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid', 'cancelled'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->date('approval_date')->nullable();
            $table->date('cancelled_date')->nullable();
            $table->date('disbursement_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('dependent_id')->references('id')->on('dependants')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('grant_type_id')->references('id')->on('grant_types')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grants');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loan_guarantors', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));

            // Use uuid() instead of foreignId() for loan_id and guarantor_id
            $table->uuid('loan_id');
            $table->uuid('guarantor_id');

            $table->string('loan_number', 255);
            $table->decimal('guarantor_liability_amount', 10, 2)->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected', 'accepted', 'declined'])->default('pending');
            $table->string('response', 255)->nullable();
            $table->timestamps();

            // Add the foreign key constraints manually
            $table->foreign('loan_id')
                ->references('id')
                ->on('loans')
                ->cascadeOnDelete();

            $table->foreign('guarantor_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_guarantors');
    }
};

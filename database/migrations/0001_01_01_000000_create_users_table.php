<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * @
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('created_by')->nullable();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('phone_number')->unique();
            $table->string('address');
            $table->date('dob');
            $table->string('national_id')->nullable();
            $table->string('passport_image')->nullable();
            $table->enum('gender', ['male', 'female', 'other']);
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('years_of_employment')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('old_employee_id')->nullable();

            // Change employer_id to uuid to match the id type
            $table->uuid('employer_id')->nullable();
            $table->foreign('employer_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->decimal('salary', 10, 2)->nullable();
            $table->decimal('loan_limit', 10, 2)->default(0);
            $table->string('verification_code')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};

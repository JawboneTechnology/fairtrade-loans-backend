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
        if (Schema::hasColumn('password_resets', 'expired_at')) {
            DB::statement('ALTER TABLE `password_resets` CHANGE COLUMN `expired_at` `expires_at` TIMESTAMP NULL');
        } elseif (!Schema::hasColumn('password_resets', 'expires_at')) {
            Schema::table('password_resets', function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->after('reset_code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('password_resets', 'expires_at') && !Schema::hasColumn('password_resets', 'expired_at')) {
            DB::statement('ALTER TABLE `password_resets` CHANGE COLUMN `expires_at` `expired_at` TIMESTAMP NULL');
        }
    }
};

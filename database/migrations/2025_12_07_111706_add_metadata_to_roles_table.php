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
        Schema::table('roles', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('display_name')->nullable()->after('name');
            $table->integer('priority')->default(0)->after('guard_name');
            $table->boolean('is_system_role')->default(false)->after('priority');
            $table->json('metadata')->nullable()->after('is_system_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['description', 'display_name', 'priority', 'is_system_role', 'metadata']);
        });
    }
};

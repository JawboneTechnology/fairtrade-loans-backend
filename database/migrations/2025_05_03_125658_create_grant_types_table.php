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
        Schema::create('grant_types', function (Blueprint $table) {
            $table->uuid('id')->primary()->unique();
            $table->string('grant_code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('max_amount', 10, 2)->nullable(); // NULL means no limit
            $table->boolean('requires_dependent')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grant_types');
    }
};

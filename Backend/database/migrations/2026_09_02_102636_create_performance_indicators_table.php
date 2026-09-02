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
        Schema::create('performance_indicators', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('category', 50)->nullable();
            $table->decimal('target', 8, 2)->nullable();
            $table->decimal('weight', 5, 2)->default(0);
            $table->string('measurement_type', 30)->default('score');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_indicators');
    }
};

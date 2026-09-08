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
        Schema::create('position_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('minimum_experience_years', 5, 2)->default(0);
            $table->decimal('minimum_performance_score', 5, 2)->nullable();
            $table->decimal('minimum_attendance_percentage', 5, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('position_id');
            $table->index([
                'position_id',
                'is_active',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('position_requirements');
    }
};

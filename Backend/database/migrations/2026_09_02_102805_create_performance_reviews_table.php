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
        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('performance_period_id')->constrained('performance_periods')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('review_type', 30)->default('manager');
            $table->string('status', 30)->default('draft');
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->date('review_date')->nullable();
            $table->text('comments')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'performance_period_id']);
            $table->index('reviewer_id');
            $table->index('review_type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
    }
};

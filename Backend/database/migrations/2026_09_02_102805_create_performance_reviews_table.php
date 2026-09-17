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
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->string('rating', 50)->nullable();
            $table->text('comments')->nullable();
            $table->string('status', 30);
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->unique(
                [
                    'employee_id',
                    'performance_period_id',
                ],
                'performance_review_employee_period_unique',
            );
            $table->index('reviewer_id');
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

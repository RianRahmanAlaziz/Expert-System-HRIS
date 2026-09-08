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
        Schema::create('promotion_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('current_position_id')->constrained('positions')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('target_position_id')->constrained('positions')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('assessed_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();

            $table->date('assessment_date');
            $table->string('status', 30);
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->string('recommendation', 30)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('employee_id');
            $table->index('current_position_id');
            $table->index('target_position_id');
            $table->index('assessed_by');
            $table->index('assessment_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_assessments');
    }
};

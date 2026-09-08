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
        Schema::create('promotion_assessment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_assessment_id')->constrained('promotion_assessments')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('criterion_type', 50);
            $table->string('criterion_code', 100);
            $table->string('criterion_name', 150);
            $table->decimal('score', 5, 2)->nullable();
            $table->decimal('weight', 5, 2)->default(0);
            $table->boolean('is_passed')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('promotion_assessment_id');
            $table->unique(
                ['promotion_assessment_id', 'criterion_code'],
                'pai_assessment_criterion_unique',
            );

            $table->index('criterion_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_assessment_items');
    }
};

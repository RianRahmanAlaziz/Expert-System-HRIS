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
        Schema::create('performance_review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_review_id')->constrained('performance_reviews')->cascadeOnDelete();
            $table->foreignId('performance_indicator_id')->constrained('performance_indicators')->restrictOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->text('comment')->nullable();

            $table->timestamps();

            $table->unique(
                ['performance_review_id', 'performance_indicator_id'],
                'review_indicator_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_review_items');
    }
};

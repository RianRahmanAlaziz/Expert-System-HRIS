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
        Schema::create('consultation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expert_consultation_id')->unique()->constrained('expert_consultations')->cascadeOnDelete();
            $table->string('recommendation');
            $table->decimal('score', 5, 2)->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->text('reason')->nullable();

            $table->json('input_snapshot')->nullable();
            $table->json('matched_rules')->nullable();
            $table->json('suggested_actions')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultation_results');
    }
};

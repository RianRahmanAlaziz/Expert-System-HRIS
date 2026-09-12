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
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('expert_consultation_id')->nullable()->constrained('expert_consultations')->nullOnDelete();
            $table->string('type', 50);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 30)->default('medium');
            $table->string('status', 30)->default('pending');
            $table->timestamp('recommended_at')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'type']);
            $table->index(['status', 'priority']);
            $table->index('recommended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};

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
        Schema::create('career_path_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('career_path_id')->constrained('career_paths')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('sequence');

            $table->timestamps();

            $table->unique(
                ['career_path_id', 'position_id'],
                'cpp_career_path_position_unique',
            );

            $table->index(
                ['career_path_id', 'sequence'],
                'cpp_career_path_sequence_idx',
            );

            $table->index('position_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('career_path_positions');
    }
};

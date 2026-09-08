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
        Schema::create('position_requirement_competencies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('position_requirement_id');

            $table->foreignId('competency_id');

            $table->foreignId('required_level_id')
                ->nullable();

            $table->decimal('minimum_score', 5, 2)
                ->nullable();

            $table->decimal('weight', 5, 2)
                ->default(0);

            $table->boolean('is_required')
                ->default(true);

            $table->timestamps();

            /*
             * Foreign keys.
             *
             * Explicit names are used because MySQL has a
             * 64-character identifier limit.
             */
            $table->foreign(
                'position_requirement_id',
                'prc_requirement_fk',
            )
                ->references('id')
                ->on('position_requirements')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign(
                'competency_id',
                'prc_competency_fk',
            )
                ->references('id')
                ->on('competencies')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign(
                'required_level_id',
                'prc_level_fk',
            )
                ->references('id')
                ->on('competency_levels')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
             * One competency can only appear once
             * in the same position requirement.
             */
            $table->unique(
                [
                    'position_requirement_id',
                    'competency_id',
                ],
                'prc_requirement_competency_unique',
            );

            $table->index(
                'competency_id',
                'prc_competency_idx',
            );

            $table->index(
                'required_level_id',
                'prc_level_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('position_requirement_competencies');
    }
};

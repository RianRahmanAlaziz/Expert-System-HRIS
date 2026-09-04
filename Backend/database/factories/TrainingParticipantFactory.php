<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Training;
use App\Models\TrainingParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingParticipant>
 */
class TrainingParticipantFactory extends Factory
{
    protected $model = TrainingParticipant::class;

    public function definition(): array
    {
        return [
            'training_id' => Training::factory(),
            'employee_id' => Employee::factory(),
            'status' => 'registered',
            'score' => null,
            'registered_at' => now(),
            'completed_at' => null,
            'certificate_path' => null,
        ];
    }
}

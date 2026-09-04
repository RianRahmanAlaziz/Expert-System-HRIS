<?php

namespace Database\Factories;

use App\Models\Training;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Training>
 */
class TrainingFactory extends Factory
{
    protected $model = Training::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween(
            'now',
            '+3 months'
        );

        $endDate = fake()->dateTimeBetween(
            $startDate,
            '+3 months'
        );

        return [
            'code' => 'TRN-' . fake()->unique()->numberBetween(1, 999999),
            'name' => fake()->sentence(3),
            'category' => fake()->randomElement([
                'Leadership',
                'Technical',
                'Communication',
                'Management',
            ]),
            'description' => fake()->optional()->paragraph(),
            'trainer' => fake()->optional()->name(),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'capacity' => fake()->optional()->numberBetween(10, 50),
            'status' => fake()->randomElement([
                'scheduled',
                'ongoing',
                'completed',
                'cancelled',
            ]),
        ];
    }
}

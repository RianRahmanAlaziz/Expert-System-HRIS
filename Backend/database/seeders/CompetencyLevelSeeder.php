<?php

namespace Database\Seeders;

use App\Models\CompetencyLevel;
use Illuminate\Database\Seeder;

class CompetencyLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            [
                'level' => 1,
                'name' => 'Beginner',
                'description' => 'Memahami dasar kompetensi dan membutuhkan bimbingan dalam penerapannya.',
            ],
            [
                'level' => 2,
                'name' => 'Basic',
                'description' => 'Mampu menerapkan kompetensi pada situasi sederhana dengan sedikit bimbingan.',
            ],
            [
                'level' => 3,
                'name' => 'Intermediate',
                'description' => 'Mampu menerapkan kompetensi secara mandiri pada situasi kerja umum.',
            ],
            [
                'level' => 4,
                'name' => 'Advanced',
                'description' => 'Mampu menerapkan kompetensi pada situasi kompleks dan memberikan arahan kepada orang lain.',
            ],
            [
                'level' => 5,
                'name' => 'Expert',
                'description' => 'Menjadi ahli dalam kompetensi dan mampu menjadi referensi atau mentor bagi orang lain.',
            ],
        ];

        foreach ($levels as $level) {
            CompetencyLevel::query()->updateOrCreate(
                ['level' => $level['level']],
                $level,
            );
        }
    }
}

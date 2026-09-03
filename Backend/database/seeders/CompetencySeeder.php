<?php

namespace Database\Seeders;

use App\Models\Competency;
use Illuminate\Database\Seeder;

class CompetencySeeder extends Seeder
{
    public function run(): void
    {
        $competencies = [
            [
                'code' => 'LEAD',
                'name' => 'Leadership',
                'category' => 'Behavioral',
                'description' => 'Kemampuan memimpin dan mengarahkan tim untuk mencapai tujuan organisasi.',
                'status' => 'active',
            ],
            [
                'code' => 'COMM',
                'name' => 'Communication',
                'category' => 'Behavioral',
                'description' => 'Kemampuan menyampaikan dan menerima informasi secara efektif.',
                'status' => 'active',
            ],
            [
                'code' => 'TEAM',
                'name' => 'Teamwork',
                'category' => 'Behavioral',
                'description' => 'Kemampuan bekerja sama secara efektif dalam tim.',
                'status' => 'active',
            ],
            [
                'code' => 'PROB',
                'name' => 'Problem Solving',
                'category' => 'Cognitive',
                'description' => 'Kemampuan mengidentifikasi masalah dan menemukan solusi yang efektif.',
                'status' => 'active',
            ],
            [
                'code' => 'ADAP',
                'name' => 'Adaptability',
                'category' => 'Behavioral',
                'description' => 'Kemampuan beradaptasi terhadap perubahan lingkungan dan kebutuhan pekerjaan.',
                'status' => 'active',
            ],
            [
                'code' => 'TECH',
                'name' => 'Technical Expertise',
                'category' => 'Technical',
                'description' => 'Kemampuan menerapkan pengetahuan dan keterampilan teknis sesuai kebutuhan pekerjaan.',
                'status' => 'active',
            ],
        ];

        foreach ($competencies as $competency) {
            Competency::query()->updateOrCreate(
                ['code' => $competency['code']],
                $competency,
            );
        }
    }
}

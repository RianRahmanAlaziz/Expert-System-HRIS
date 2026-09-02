<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        LeaveType::query()->upsert(
            [
                [
                    'name' => 'Annual Leave',
                    'code' => 'ANNUAL',
                    'default_days' => 12,
                    'description' => 'Cuti tahunan untuk kebutuhan pribadi karyawan.',
                    'status' => 'active',
                ],
                [
                    'name' => 'Sick Leave',
                    'code' => 'SICK',
                    'default_days' => 12,
                    'description' => 'Cuti karena kondisi kesehatan.',
                    'status' => 'active',
                ],
                [
                    'name' => 'Maternity Leave',
                    'code' => 'MATERNITY',
                    'default_days' => 90,
                    'description' => 'Cuti untuk keperluan melahirkan.',
                    'status' => 'active',
                ],
                [
                    'name' => 'Marriage Leave',
                    'code' => 'MARRIAGE',
                    'default_days' => 3,
                    'description' => 'Cuti untuk keperluan pernikahan.',
                    'status' => 'active',
                ],
                [
                    'name' => 'Unpaid Leave',
                    'code' => 'UNPAID',
                    'default_days' => 0,
                    'description' => 'Cuti tanpa pembayaran.',
                    'status' => 'inactive',
                ],
            ],
            ['code'],
            [
                'name',
                'default_days',
                'description',
                'status',
            ]
        );
    }
}

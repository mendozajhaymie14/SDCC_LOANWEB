<?php

namespace Database\Seeders;

use App\Models\CoopMember;
use Illuminate\Database\Seeder;

class CoopMemberSeeder extends Seeder
{
    public function run(): void
    {
        CoopMember::create([
            'member_id'     => 'SDCC-2023-0001',
            'full_name'     => 'Juan Dela Cruz',
            'date_of_birth' => '1985-03-15',
            'email'         => 'juan.delacruz85@gmail.com',
            'is_registered' => false,
            'share_capital' => 50000,
            'is_migs'       => true,
        ]);

        CoopMember::create([
            'member_id'     => 'SDCC-2023-0002',
            'full_name'     => 'Maria Clara Santos',
            'date_of_birth' => '1990-07-22',
            'email'         => 'maria.santos90@gmail.com',
            'is_registered' => false,
            'share_capital' => 30000,
            'is_migs'       => false,
        ]);

        CoopMember::create([
            'member_id'     => 'SDCC-2023-0003',
            'full_name'     => 'Pedro Reyes',
            'date_of_birth' => '1982-11-03',
            'email'         => 'pedro.reyes82@gmail.com',
            'is_registered' => false,
            'share_capital' => 75000,
            'is_migs'       => true,
        ]);

        CoopMember::create([
            'member_id'     => 'SDCC-2023-0004',
            'full_name'     => 'Ana Santos-Lim',
            'date_of_birth' => '1995-01-28',
            'email'         => 'ana.lim95@gmail.com',
            'is_registered' => false,
            'share_capital' => 25000,
            'is_migs'       => false,
        ]);

        CoopMember::create([
            'member_id'     => 'SDCC-2023-0005',
            'full_name'     => 'Carlos Miguel Tan',
            'date_of_birth' => '1978-09-14',
            'email'         => 'carlos.tan78@gmail.com',
            'is_registered' => false,
            'share_capital' => 100000,
            'is_migs'       => true,
        ]);
    }
}
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
        ]);

        CoopMember::create([
            'member_id'     => 'SDCC-2023-0002',
            'full_name'     => 'Maria Clara Santos',
            'date_of_birth' => '1990-07-22',
            'email'         => 'maria.santos90@gmail.com',
            'is_registered' => false,
        ]);
    }
}
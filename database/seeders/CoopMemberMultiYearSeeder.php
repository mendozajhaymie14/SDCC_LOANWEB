<?php

namespace Database\Seeders;

use App\Models\CoopMember;
use Illuminate\Database\Seeder;

class CoopMemberMultiYearSeeder extends Seeder
{
    public function run(): void
    {
        $members = [
            // ─── 2020 MEMBERS ───
            [
                'member_id'     => 'SDCC-2020-0015',
                'full_name'     => 'Roberto Gomez Silang',
                'date_of_birth' => '1975-04-12',
                'email'         => 'roberto.silang75@gmail.com',
                'is_registered' => false,
                'created_at'    => '2020-03-10 10:00:00',
            ],
            [
                'member_id'     => 'SDCC-2020-0042',
                'full_name'     => 'Corazon Aquino Cruz',
                'date_of_birth' => '1982-08-25',
                'email'         => 'corazon.cruz82@gmail.com',
                'is_registered' => false,
                'created_at'    => '2020-09-18 14:30:00',
            ],

            // ─── 2021 MEMBERS ───
            [
                'member_id'     => 'SDCC-2021-0108',
                'full_name'     => 'Fernando Poe Ramos',
                'date_of_birth' => '1989-12-01',
                'email'         => 'fernando.ramos89@gmail.com',
                'is_registered' => false,
                'created_at'    => '2021-02-14 09:15:00',
            ],
            [
                'member_id'     => 'SDCC-2021-0215',
                'full_name'     => 'Teresa Magbanua Dizon',
                'date_of_birth' => '1993-06-17',
                'email'         => 'teresa.dizon93@gmail.com',
                'is_registered' => false,
                'created_at'    => '2021-11-05 11:45:00',
            ],

            // ─── 2022 MEMBERS ───
            [
                'member_id'     => 'SDCC-2022-0301',
                'full_name'     => 'Benigno Santos Aquino',
                'date_of_birth' => '1986-01-20',
                'email'         => 'benigno.aquino86@gmail.com',
                'is_registered' => false,
                'created_at'    => '2022-04-12 08:30:00',
            ],
            [
                'member_id'     => 'SDCC-2022-0419',
                'full_name'     => 'Gabriela Silang Bonifacio',
                'date_of_birth' => '1991-10-08',
                'email'         => 'gabriela.bonifacio91@gmail.com',
                'is_registered' => false,
                'created_at'    => '2022-08-29 15:10:00',
            ],
        ];

        foreach ($members as $member) {
            CoopMember::create($member);
        }
    }
}
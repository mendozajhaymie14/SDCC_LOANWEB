<?php

namespace Database\Seeders;

use App\Models\CoopMember;
use Illuminate\Database\Seeder;

/**
 * The sample cooperative roster.
 *
 * The seeder is idempotent: running it twice leaves the same five members
 * with the same data, so `migrate:fresh --seed` and a plain `db:seed` behave
 * the same. Every member has an e-mail on file, which is what the existing
 * member registration flow mails its verification code to.
 */
class CoopMemberSeeder extends Seeder
{
    public function run(): void
    {
        $members = [
            [
                'member_id'      => 'SDCC-2023-0001',
                'full_name'      => 'Juan Dela Cruz',
                'date_of_birth'  => '1985-03-15',
                'email'          => 'juan.delacruz85@gmail.com',
                'contact_number' => '09171234567',
                'share_capital'  => 50000,
                'is_migs'        => true,
            ],
            [
                'member_id'      => 'SDCC-2023-0002',
                'full_name'      => 'Maria Clara Santos',
                'date_of_birth'  => '1990-07-22',
                'email'          => 'maria.santos90@gmail.com',
                'contact_number' => '09181234567',
                'share_capital'  => 30000,
                'is_migs'        => false,
            ],
            [
                'member_id'      => 'SDCC-2023-0003',
                'full_name'      => 'Pedro Reyes',
                'date_of_birth'  => '1982-11-03',
                'email'          => 'pedro.reyes82@gmail.com',
                'contact_number' => '09191234567',
                'share_capital'  => 75000,
                'is_migs'        => true,
            ],
            [
                'member_id'      => 'SDCC-2023-0004',
                'full_name'      => 'Ana Santos-Lim',
                'date_of_birth'  => '1995-01-28',
                'email'          => 'ana.lim95@gmail.com',
                'contact_number' => '09201234567',
                'share_capital'  => 25000,
                'is_migs'        => false,
            ],
            [
                'member_id'      => 'SDCC-2023-0005',
                'full_name'      => 'Carlos Miguel Tan',
                'date_of_birth'  => '1978-09-14',
                'email'          => 'carlos.tan78@gmail.com',
                'contact_number' => '09211234567',
                'share_capital'  => 100000,
                'is_migs'        => true,
            ],
        ];

        foreach ($members as $member) {
            $existing = CoopMember::where('member_id', $member['member_id'])->first();

            if ($existing) {
                // Keep the registration state of an existing member; only the
                // roster details are refreshed.
                $existing->update($member + ['status' => CoopMember::STATUS_ACTIVE]);
                continue;
            }

            CoopMember::create($member + [
                'is_registered' => false,
                'status'        => CoopMember::STATUS_ACTIVE,
            ]);
        }
    }
}

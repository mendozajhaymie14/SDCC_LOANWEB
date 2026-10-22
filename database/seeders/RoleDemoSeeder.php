<?php

namespace Database\Seeders;

use App\Models\CoopMember;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class RoleDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed demo users for each of the 6 roles.
     * All passwords must be set via environment variables; throw if missing.
     * Only runs in local/testing environments or when the explicit opt-in flag is set.
     */
    public function run(): void
    {
        // Only seed demo users in local/testing or when explicitly opted in
        if (! app()->environment(['local', 'testing']) && config('sdcc.seed_demo_users') !== true) {
            $this->command?->info('Role demo seeding skipped (run in local/testing or set SEED_DEMO_USERS=true).');
            return;
        }

        $passwords = [
            'admin' => env('ADMIN_DEMO_PASSWORD'),
            'branch_manager' => env('BRANCH_MANAGER_DEMO_PASSWORD'),
            'loan_officer' => env('LOAN_OFFICER_DEMO_PASSWORD'),
            'credit_investigator' => env('CREDIT_INVESTIGATOR_DEMO_PASSWORD'),
            'member' => env('MEMBER_DEMO_PASSWORD'),
            'user' => env('USER_DEMO_PASSWORD'),
        ];

        foreach ($passwords as $role => $pwd) {
            if ($pwd === null || $pwd === '') {
                throw new RuntimeException("Environment variable for {$role} demo password is not set. Please set the appropriate DEMO_PASSWORD env variable.");
            }
        }

        $users = [
            ['role' => Permissions::ROLE_ADMIN, 'name' => 'System Administrator', 'email' => 'admin@sdcc.coop', 'password' => $passwords['admin']],
            ['role' => Permissions::ROLE_BRANCH_MANAGER, 'name' => 'Branch Manager', 'email' => 'branchmgr@sdcc.coop', 'password' => $passwords['branch_manager']],
            ['role' => Permissions::ROLE_LOAN_OFFICER, 'name' => 'Loan Officer', 'email' => 'loanofficer@sdcc.coop', 'password' => $passwords['loan_officer']],
            ['role' => Permissions::ROLE_CREDIT_INVESTIGATOR, 'name' => 'Credit Investigator', 'email' => 'creditinvest@sdcc.coop', 'password' => $passwords['credit_investigator']],
            ['role' => Permissions::ROLE_MEMBER, 'name' => 'Cooperative Member', 'email' => 'member@sdcc.coop', 'password' => $passwords['member']],
            ['role' => Permissions::ROLE_USER, 'name' => 'Registered User', 'email' => 'user@sdcc.coop', 'password' => $passwords['user']],
        ];

        foreach ($users as $u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make($u['password']),
                    'usertype' => $u['role'],
                    // Model events are disabled for seeding, so the model hook
                    // that keeps the two columns in step never runs here.
                    'role' => $u['role'],
                ]
            );

            if ($u['role'] !== Permissions::ROLE_MEMBER) {
                continue;
            }

            // The demo member must really be a member: a cooperative record,
            // linked to the account, so usertype and the link agree.
            $member = CoopMember::where('email', $u['email'])->first();

            if (! $member) {
                $member = CoopMember::create([
                    'member_id'      => 'SDCC-' . now()->format('Y') . '-9001',
                    'full_name'      => $u['name'],
                    'date_of_birth' => '1980-01-01',
                    'email'          => $u['email'],
                    'contact_number' => '09171234567',
                    'is_registered'  => true,
                    'status'         => CoopMember::STATUS_ACTIVE,
                ]);
            }

            $member->forceFill([
                'is_registered' => true,
                'user_id'       => $user->id,
                'status'        => CoopMember::STATUS_ACTIVE,
            ])->save();

            $user->forceFill(['coop_member_id' => $member->id])->save();
        }

        $this->command?->info('Role demo users created successfully.');
    }
}

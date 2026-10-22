<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     * Only runs in local/testing environments or when explicitly opted in.
     * Admin credentials are set via ADMIN_SEED_PASSWORD environment variable.
     */
    public function run(): void
    {
        // Never seed the demo admin in production
        if (! app()->environment(['local', 'testing']) && config('sdcc.seed_demo_users') !== true) {
            $this->command?->info('Admin demo user seeding skipped (run in local/testing or set SEED_DEMO_USERS=true).');
            return;
        }

        $password = env('ADMIN_SEED_PASSWORD');

        if ($password === null || $password === '') {
            throw new RuntimeException('ADMIN_SEED_PASSWORD environment variable is not set. Please set it in your .env file.');
        }

        $admin = User::where('email', 'admin@sdcc.com')->first();

        if (!$admin) {
            $admin = User::create([
                'name' => 'System Administrator',
                'email' => 'admin@sdcc.com',
                'password' => Hash::make($password),
                'usertype' => 'admin',
                // Model events are disabled for seeding, so the model hook that
                // keeps the two columns in step never runs here.
                'role' => 'admin',
            ]);
        }

        // Ensure usertype is set to admin (in case it changed)
        if ($admin->usertype !== 'admin' || $admin->role !== 'admin') {
            $admin->usertype = 'admin';
            $admin->role = 'admin';
            $admin->save();
        }
    }
}

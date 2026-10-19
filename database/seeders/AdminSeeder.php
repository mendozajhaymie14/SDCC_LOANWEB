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
     */
    public function run(): void
    {
        $password = env('ADMIN_SEED_PASSWORD');

        if ($password === null) {
            throw new RuntimeException('ADMIN_SEED_PASSWORD environment variable is not set. Please set it in your .env file.');
        }

        $admin = User::where('email', 'admin@sdcc.com')->first();

        if (!$admin) {
            $admin = User::create([
                'name' => 'System Administrator',
                'email' => 'admin@sdcc.com',
                'password' => Hash::make($password),
                'usertype' => 'admin',
            ]);
        }

        // Ensure usertype is set to admin (in case it changed)
        if ($admin->usertype !== 'admin') {
            $admin->usertype = 'admin';
            $admin->save();
        }
    }
}

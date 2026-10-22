<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            CoopMemberSeeder::class,
        ]);

        // Demo users only in local/testing or when explicitly opted in
        if (app()->environment(['local', 'testing']) || config('sdcc.seed_demo_users') === true) {
            $this->call(RoleDemoSeeder::class);
        }
    }
}

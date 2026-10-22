<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The `users.role` column migration (2026_10_21_140000) is the single
 * place that writes the paper-facing role for rows created before the column
 * existed. Rows are inserted through the query builder (raw SQL), bypassing
 * the model's saving hook, so these tests exercise the migration itself and
 * not the hook.
 */
class RoleColumnMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_21_140000_add_role_column_to_users_table.php');
    }

    private function insertUser(string $email, ?string $usertype): void
    {
        DB::table('users')->insert([
            'name'       => $email,
            'email'      => $email,
            'password'   => 'x',
            'usertype'   => $usertype,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_it_backfills_the_role_column_from_usertype(): void
    {
        $this->insertUser('a@example.com', 'admin');
        $this->insertUser('b@example.com', 'member');
        $this->insertUser('c@example.com', 'user');
        $this->insertUser('d@example.com', null);

        $this->migration()->up();

        $this->assertSame('admin', DB::table('users')->where('email', 'a@example.com')->value('role'));
        $this->assertSame('member', DB::table('users')->where('email', 'b@example.com')->value('role'));
        $this->assertSame('user', DB::table('users')->where('email', 'c@example.com')->value('role'));
        // COALESCE(usertype, 'user'): a NULL usertype becomes 'user', never
        // promoted to 'member' — the register-flow gate stays intact.
        $this->assertSame('user', DB::table('users')->where('email', 'd@example.com')->value('role'));
    }

    public function test_running_the_migration_again_changes_nothing(): void
    {
        $this->insertUser('e@example.com', 'user');

        // An explicitly-set role is never overwritten by a re-run: the
        // backfill only touches rows where role IS NULL.
        DB::table('users')->where('email', 'e@example.com')->update(['role' => 'branch_manager']);

        $before = DB::table('users')->pluck('role', 'email')->all();

        $this->migration()->up();
        $this->migration()->up();

        $this->assertEquals($before, DB::table('users')->pluck('role', 'email')->all());
    }

    public function test_down_removes_the_role_column_and_up_restores_it(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'role'));

        $this->migration()->down();
        $this->assertFalse(Schema::hasColumn('users', 'role'));

        $this->migration()->up();
        $this->assertTrue(Schema::hasColumn('users', 'role'));
    }
}
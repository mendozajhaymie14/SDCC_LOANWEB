<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The usertype / coop-link data migration.
 *
 * Business rule: usertype 'member' means the row is linked to a
 * coop_members record; usertype 'user' means registered but NOT a member.
 * The migration promotes rows that already carry a link and never maps a
 * plain 'user' row onto 'member'.
 */
class UsertypeSyncMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** Load the migration file and return the anonymous class instance. */
    private function migration(): object
    {
        return require database_path(
            'migrations/2026_10_21_130000_sync_usertype_with_coop_member_link.php'
        );
    }

    /** A 'user' row without a coop link must never be mapped to 'member'. */
    public function test_unlinked_user_rows_are_not_promoted(): void
    {
        User::factory()->create([
            'name' => 'Plain User',
            'email' => 'plain@example.com',
            'password' => 'x',
            'usertype' => Permissions::ROLE_USER,
        ]);

        $this->migration()->up();

        $this->assertEquals(Permissions::ROLE_USER, User::where('email', 'plain@example.com')->value('usertype'));
    }

    /** A row WITH a coop link is promoted to 'member'. */
    public function test_linked_rows_are_promoted_to_member(): void
    {
        $member = User::factory()->asMember()->create();

        // Force the row back to 'user' so the migration has work to do.
        User::whereKey($member->id)->update(['usertype' => Permissions::ROLE_USER]);

        $this->migration()->up();

        $this->assertEquals(Permissions::ROLE_MEMBER, User::find($member->id)->usertype);
    }

    /** Running it twice changes nothing the second time. */
    public function test_migration_is_idempotent(): void
    {
        $member = User::factory()->asMember()->create();
        User::whereKey($member->id)->update(['usertype' => Permissions::ROLE_USER]);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertEquals(Permissions::ROLE_MEMBER, User::find($member->id)->usertype);
        $this->assertEquals(1, DB::table('users')->where('usertype', 'member')->count());
    }

    /** A 'member' row without a link is NOT rewritten — it is left for an operator. */
    public function test_member_row_without_link_is_left_alone(): void
    {
        User::factory()->create([
            'name' => 'Stray Member',
            'email' => 'stray@example.com',
            'password' => 'x',
            'usertype' => Permissions::ROLE_MEMBER,
        ]);

        $this->migration()->up();

        $this->assertEquals(Permissions::ROLE_MEMBER, User::where('email', 'stray@example.com')->value('usertype'));
        $this->assertNull(User::where('email', 'stray@example.com')->value('coop_member_id'));
    }

    /** The column default stays 'user' — a fresh row is a registered non-member. */
    public function test_column_default_is_user(): void
    {
        $this->migration()->up();

        $default = DB::table('users')->whereRaw('1=0')->value('usertype');
        $this->assertNull($default);

        // The schema default is what a row inserted without usertype gets.
        DB::table('users')->insert([
            'name' => 'Default User',
            'email' => 'default@example.com',
            'password' => 'x',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertEquals(Permissions::ROLE_USER, User::where('email', 'default@example.com')->value('usertype'));
    }

    /** A member row always points at a real coop_members record. */
    public function test_linked_member_has_a_coop_row(): void
    {
        $member = User::factory()->asMember()->create();

        $this->assertNotNull($member->coop_member_id);
        $this->assertTrue(CoopMember::whereKey($member->coop_member_id)->exists());
        $this->assertEquals($member->id, CoopMember::find($member->coop_member_id)->user_id);
    }
}

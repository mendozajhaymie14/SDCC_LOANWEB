<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keep users.usertype in step with the cooperative link.
 *
 * Business rule: usertype 'member' means the row is linked to a
 * coop_members record; usertype 'user' means registered but not a member.
 * The two must always agree.
 *
 *   - A user WITH a coop_member_id is a member.
 *   - A user WITHOUT one is a registered non-member ('user').
 *   - A row already marked 'member' but carrying no coop link is a data
 *     error. It is NOT rewritten here — it is listed for an operator to
 *     repair, because guessing a link would be worse than the error.
 *
 * Idempotent: re-running it changes nothing and reports the same orphans.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'usertype')) {
            return;
        }

        // 1. Anything linked to a cooperative member IS a member.
        $promoted = DB::table('users')
            ->whereNotNull('coop_member_id')
            ->where(function ($query) {
                $query->whereNull('usertype')->orWhere('usertype', '!=', 'member');
            })
            ->update(['usertype' => 'member']);

        // 2. Members without a cooperative link are left alone and reported.
        $orphans = DB::table('users')
            ->where('usertype', 'member')
            ->whereNull('coop_member_id')
            ->orderBy('id')
            ->get(['id', 'name', 'email']);

        // 3. The column default stays 'user' (registered, not a member).
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `users` ALTER COLUMN `usertype` SET DEFAULT 'user'");
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('usertype', 20)->default('user')->nullable()->change();
            });
        }

        info('usertype sync: ' . $promoted . ' linked user(s) set to "member".');

        if ($orphans->isEmpty()) {
            info('usertype sync: no "member" rows without a coop_members link.');
            return;
        }

        info('usertype sync WARNING: ' . $orphans->count() . ' row(s) are marked "member" but have no coop_members link. They were NOT changed — an operator must link or reclassify them:');
        foreach ($orphans as $orphan) {
            info('  #' . $orphan->id . ' ' . $orphan->name . ' <' . $orphan->email . '>');
        }
    }

    public function down(): void
    {
        // Nothing to undo: 'member' is derived from the coop_members link and
        // is restored by this migration whenever it runs again.
    }
};

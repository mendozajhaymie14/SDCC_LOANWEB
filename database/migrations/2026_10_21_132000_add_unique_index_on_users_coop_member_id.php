<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One user per cooperative member.
 *
 * The unique index is the last line of defence for the race in the
 * registration flow: two people cannot claim the same SDCC member ID even
 * if they verify the same e-mail code at the same time.
 *
 * Nullable on purpose — a registered non-member ('user') has no cooperative
 * link, and MySQL allows many NULLs in a unique index.
 *
 * Idempotent: the index is only added when it is missing, and it is skipped
 * (with a warning) when the existing data contains duplicates, because
 * adding it would then fail.
 */
return new class extends Migration
{
    private const INDEX = 'users_coop_member_id_unique';

    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'coop_member_id')) {
            return;
        }

        $existing = collect(Schema::getIndexes('users'))->pluck('name');

        if ($existing->contains(self::INDEX)) {
            return;
        }

        $duplicates = DB::table('users')
            ->whereNotNull('coop_member_id')
            ->select('coop_member_id')
            ->groupBy('coop_member_id')
            ->havingRaw('count(*) > 1')
            ->count();

        if ($duplicates > 0) {
            info('users.coop_member_id WARNING: ' . $duplicates . ' duplicated link(s) found — the unique index was NOT added. An operator must resolve them first.');
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('coop_member_id', self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'coop_member_id')) {
            return;
        }

        $existing = collect(Schema::getIndexes('users'))->pluck('name');

        if ($existing->contains(self::INDEX)) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(self::INDEX);
            });
        }
    }
};

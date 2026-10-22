<?php

use App\Models\CoopMember;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fold the old coop_members status spellings into the canonical one.
 *
 * The column used to hold 'Approved', 'approved', 'Active' and 'active'.
 * CoopMember::STATUS_ACTIVE ('active') is now the only value the application
 * writes or filters on, so the old spellings are mapped onto it.
 *
 * Idempotent: 'active' maps onto 'active', so a second run changes nothing.
 */
return new class extends Migration
{
    /** The legacy spellings that mean "this member is active". */
    private const LEGACY_ACTIVE = ['Approved', 'approved', 'Active', 'ACTIVE'];

    public function up(): void
    {
        if (! Schema::hasTable('coop_members') || ! Schema::hasColumn('coop_members', 'status')) {
            return;
        }

        $changed = DB::table('coop_members')
            ->whereIn('status', self::LEGACY_ACTIVE)
            ->update(['status' => CoopMember::STATUS_ACTIVE]);

        info('coop_members status: ' . $changed . ' row(s) mapped to "' . CoopMember::STATUS_ACTIVE . '".');
    }

    public function down(): void
    {
        // The canonical value is the point of this migration; rolling back to
        // a mixed column would only bring the old ambiguity back.
    }
};

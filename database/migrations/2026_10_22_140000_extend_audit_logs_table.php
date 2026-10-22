<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('audit_logs')) return;

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('actor_id')->nullable()->after('user_id');
            $table->string('actor_role', 20)->nullable()->after('actor_id');
            $table->string('target_type', 50)->nullable()->after('actor_role');
            $table->unsignedBigInteger('target_id')->nullable()->after('target_type');
            $table->json('old_values')->nullable()->after('target_id');
            $table->json('new_values')->nullable()->after('old_values');
        });

        // Keep old rows readable: user_id/actor_id may be null for legacy rows.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropColumn([
                    'actor_id', 'actor_role', 'target_type', 'target_id', 'old_values', 'new_values',
                ]);
            });
        }
    }
};
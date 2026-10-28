<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('applications', 'investigator_note')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->text('investigator_note')->nullable();
                $table->unsignedBigInteger('validated_by')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->text('manager_remark')->nullable();
                $table->text('rejection_reason_override')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'investigator_note')) {
                $table->dropColumn([
                    'investigator_note', 'validated_by', 'validated_at',
                    'manager_remark', 'rejection_reason_override',
                ]);
            }
        });
    }
};

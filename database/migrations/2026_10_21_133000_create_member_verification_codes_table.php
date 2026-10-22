<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storage for the one-time codes that prove a member controls the e-mail
 * address on file.
 *
 * Only the hash of the code is stored — never the code itself. A row also
 * carries the attempt counter, the expiry and the lock, so the whole
 * "5 wrong tries, then 15 minutes locked" rule lives in the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_verification_codes')) {
            return;
        }

        Schema::create('member_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coop_member_id')->nullable();
            $table->string('member_id', 255);
            $table->string('code_hash', 128);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['member_id', 'expires_at']);
            $table->index('coop_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_verification_codes');
    }
};

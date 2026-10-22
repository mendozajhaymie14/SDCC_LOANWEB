<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A one-time code that proves somebody controls the e-mail address on file
 * for a cooperative member.
 *
 * Only the hash of the code is stored. The plain code is handed to the mail
 * and never written to the database.
 */
class MemberVerificationCode extends Model
{
    /** How long a code stays usable. */
    public const TTL_MINUTES = 10;

    /** Wrong attempts allowed before the member ID is locked. */
    public const MAX_ATTEMPTS = 5;

    /** How long a member ID stays locked after too many wrong attempts. */
    public const LOCK_MINUTES = 15;

    /** Verification outcomes. */
    public const STATUS_VERIFIED   = 'verified';
    public const STATUS_INCORRECT  = 'incorrect';
    public const STATUS_INVALID    = 'invalid';
    public const STATUS_LOCKED     = 'locked';

    protected $fillable = [
        'coop_member_id',
        'member_id',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
        'locked_until',
        'ip_address',
    ];

    protected $casts = [
        'attempts'     => 'integer',
        'expires_at'   => 'datetime',
        'consumed_at'  => 'datetime',
        'locked_until' => 'datetime',
    ];

    /**
     * Hash a code so it can be compared without ever storing it.
     */
    public static function hashCode(string $code): string
    {
        return hash_hmac('sha256', trim($code), (string) config('app.key'));
    }

    /**
     * Issue a fresh code for a member.
     *
     * Any earlier code for the same member ID is consumed, so only the most
     * recent code can ever be used.
     *
     * @return array{record: self, code: string}
     */
    public static function issue(CoopMember $member, ?string $ip = null): array
    {
        static::where('member_id', $member->member_id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $record = static::create([
            'coop_member_id' => $member->id,
            'member_id'      => $member->member_id,
            'code_hash'      => static::hashCode($code),
            'expires_at'     => now()->addMinutes(self::TTL_MINUTES),
            'ip_address'     => $ip,
        ]);

        return ['record' => $record, 'code' => $code];
    }

    /**
     * Check a code against the live codes for a member ID.
     *
     * A wrong code costs one attempt; the fifth wrong attempt locks the
     * member ID for LOCK_MINUTES. A correct code is consumed on the spot.
     *
     * @return array{status: string, record?: self, retry_after?: \DateTimeInterface, attempts_left?: int}
     */
    public static function check(string $memberId, string $code): array
    {
        $memberId = strtoupper(trim($memberId));

        $lockedUntil = static::where('member_id', $memberId)
            ->whereNotNull('locked_until')
            ->where('locked_until', '>', now())
            ->max('locked_until');

        if ($lockedUntil !== null) {
            return ['status' => self::STATUS_LOCKED, 'retry_after' => $lockedUntil];
        }

        $record = static::where('member_id', $memberId)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $record) {
            return ['status' => self::STATUS_INVALID];
        }

        if (! hash_equals($record->code_hash, static::hashCode($code))) {
            $record->increment('attempts');

            if ($record->attempts >= self::MAX_ATTEMPTS) {
                $record->update(['locked_until' => now()->addMinutes(self::LOCK_MINUTES)]);

                return ['status' => self::STATUS_LOCKED, 'retry_after' => $record->locked_until];
            }

            return [
                'status'        => self::STATUS_INCORRECT,
                'attempts_left' => self::MAX_ATTEMPTS - $record->attempts,
            ];
        }

        $record->update(['consumed_at' => now()]);

        return ['status' => self::STATUS_VERIFIED, 'record' => $record];
    }
}

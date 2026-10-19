<?php

namespace App\Support;

/**
 * Status helpers for the two parallel status fields that share the same
 * intended values but store them differently:
 *   - `applications` keeps 'Pending' / 'Approved' casing
 *   - `loan_applications` keeps 'pending' / 'approved' lowercase
 * Comparison is case-insensitive, so both stored forms are accepted.
 */
class LoanStatus
{
    /** Both tables may store these */
    public const PENDING   = 'pending';
    public const APPROVED  = 'approved';
    public const DISBURSED = 'disbursed';
    public const REJECTED  = 'rejected';
    public const PAID      = 'paid';
    public const FLAGGED   = 'flagged';

    private static array $ACCEPTED = [
        self::PENDING   => self::PENDING,
        self::APPROVED  => self::APPROVED,
        self::DISBURSED => self::DISBURSED,
        self::REJECTED  => self::REJECTED,
        self::PAID      => self::PAID,
        self::FLAGGED   => self::FLAGGED,
    ];

    /** true if $value matches any accepted case-insensitive status */
    public static function is($value): bool
    {
        if ($value === null) {
            return false;
        }
        $lower = strtolower((string) $value);
        return in_array($lower, array_map('strtolower', self::$ACCEPTED), true);
    }

    public static function isPending($v): bool    { return self::is($v) && strtolower($v) === self::PENDING; }
    public static function isApproved($v): bool  { return self::is($v) && strtolower($v) === self::APPROVED; }
    public static function isDisbursed($v): bool { return self::is($v) && strtolower($v) === self::DISBURSED; }
    public static function isRejected($v): bool  { return self::is($v) && strtolower($v) === self::REJECTED; }
    public static function isPaid($v): bool      { return self::is($v) && strtolower($v) === self::PAID; }
    public static function isFlagged($v): bool   { return self::is($v) && strtolower($v) === self::FLAGGED; }

    /** as-written value to persist (never rewrite existing rows).
     *  `applications` keeps PascalCase ('Approved', 'Paid'); `loan_applications`
     *  keeps lowercase ('approved', 'paid'). */
    public static function write(string $constant, string $table = 'loan_applications'): string
    {
        $lower = strtolower($constant);

        if ($table === 'applications') {
            // Return PascalCase for applications table
            return match ($lower) {
                'pending'   => 'Pending',
                'approved'  => 'Approved',
                'disbursed' => 'Disbursed',
                'rejected'  => 'Rejected',
                'paid'      => 'Paid',
                'flagged'   => 'Flagged',
                default     => ucfirst($constant),
            };
        } else {
            // Return lowercase for loan_applications table
            return match ($lower) {
                'pending'   => 'pending',
                'approved'  => 'approved',
                'disbursed' => 'disbursed',
                'rejected'  => 'rejected',
                'paid'      => 'paid',
                'flagged'   => 'flagged',
                default     => $constant,
            };
        }
    }
}
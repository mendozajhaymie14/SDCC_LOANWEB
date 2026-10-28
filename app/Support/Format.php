<?php

namespace App\Support;

/**
 * Shared presentation formatting for the admin panel.
 * One money helper and one date helper — everywhere money is shown it goes
 * through Format::money() / Format::peso() (₱, always two decimals) and every
 * date through Format::date(). The old wrong currency sign is blocked at the
 * never reaches the view.
 */
class Format
{
    /** ₱1,234.00 — always two decimals, symbol before the number. Null-safe. */
    public static function money($amount): string
    {
        return '₱' . number_format((float) ($amount ?? 0), 2);
    }

    /** Alias for money() — explicit peso formatting. */
    public static function peso($amount): string
    {
        return self::money($amount);
    }

    /** 'M d, Y'. Null/empty safe, returns an em-dash placeholder. */
    public static function date($date, string $format = 'M d, Y'): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        return \Carbon\Carbon::parse($date)->format($format);
    }
}

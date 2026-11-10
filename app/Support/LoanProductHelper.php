<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * Loan product configuration with legacy fallback.
 *
 * When a loan type key is removed from loan_matrix.loan_types,
 * this helper falls back to loan_matrix.legacy_loan_types so that
 * existing applications, reports, and exports still display readable labels.
 */
class LoanProductHelper
{
    /**
     * Get loan type config array (loan_types or legacy_loan_types), with fallback.
     */
    public static function loanTypeConfig(string $key, mixed $default = null): mixed
    {
        $value = Config::get("loan_matrix.loan_types.{$key}");
        if ($value !== null) {
            return $value;
        }
        return Config::get("loan_matrix.legacy_loan_types.{$key}", $default);
    }

    /**
     * Get matrix config for a loan type, with legacy fallback.
     */
    public static function matrixConfig(string $key, mixed $default = null): mixed
    {
        $value = Config::get("loan_matrix.matrix.{$key}");
        if ($value !== null) {
            return $value;
        }
        return Config::get("loan_matrix.legacy_loan_types.{$key}", $default);
    }

    /**
     * Get human-readable loan type label, preferring current config then legacy.
     */
    public static function getLoanTypeLabel(string $loanType): string
    {
        return static::loanTypeConfig($loanType, $loanType) ?? $loanType;
    }

    /**
     * Check whether a loan type requires an assessed credit limit.
     * Exempt: secured, share_cap (these remain exempt even in legacy).
     */
    public static function requiresCreditLimit(string $loanType): bool
    {
        $exempt = ['secured', 'share_cap'];
        return ! in_array($loanType, $exempt, true);
    }
}
<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Amortization service — single source of truth for all loan schedules.
 *
 * Uses the standard diminishing-balance (annuity) formula:
 *   monthly rate r, EMI = P·r / (1 − (1+r)⁻ⁿ)
 *   each month: interest = balance × r, principal = EMI − interest
 *   the last installment is adjusted so the balance ends at exactly 0.
 *
 * The monthly rate is a placeholder (1.00 % per month ≈ 12 %/yr) until SDCC
 * publishes the real rate-by-term table. Override per loan via the $rate
 * argument or by swapping the constructor default.
 */
class AmortizationService
{
    public function __construct(protected float $monthlyRate = 0.01)
    {
    }

    /**
     * Generate a full amortization schedule.
     *
     * @param  float  $principal
     * @param  int    $termMonths
     * @param  float  $rate   Monthly rate (overrides constructor default if set).
     * @return Collection of
     *   ['month' => int, 'due_date' => \Carbon\Carbon, 'payment' => float,
     *    'principal' => float, 'interest' => float, 'remaining_balance' => float]
     */
    public function schedule(float $principal, int $termMonths, ?float $rate = null): Collection
    {
        $r = $rate ?? $this->monthlyRate;
        $emi = $this->emi($principal, $termMonths, $r);

        $schedule = [];
        $remainingBalance = $principal;

        for ($month = 1; $month <= $termMonths; $month++) {
            $dueDate = now()->addMonths($month - 1)->endOfMonth();
            $interest = round($remainingBalance * $r, 2);

            if ($month === $termMonths) {
                // Last installment: pay off exactly — principal = remaining balance.
                $principalPortion = round($remainingBalance, 2);
                $payment = round($principalPortion + $interest, 2);
                $remainingBalance = 0.0;
            } else {
                $principalPortion = round($emi - $interest, 2);
                $payment = $emi;
                $remainingBalance = round($remainingBalance - $principalPortion, 2);
            }

            $schedule[] = [
                'month'             => $month,
                'due_date'          => $dueDate,
                'payment'           => $payment,
                'principal'         => $principalPortion,
                'interest'          => $interest,
                'remaining_balance' => $remainingBalance,
            ];
        }

        return collect($schedule);
    }

    /**
     * Equal monthly installment using the annuity formula.
     *
     * @param  float  $principal
     * @param  int    $termMonths
     * @param  float  $rate   Monthly rate.
     * @return float
     */
    public function emi(float $principal, int $termMonths, ?float $rate = null): float
    {
        $r = $rate ?? $this->monthlyRate;

        if ($principal <= 0 || $termMonths <= 0) {
            return 0.0;
        }
        if ($r <= 0) {
            return round($principal / $termMonths, 2);
        }

        $emi = ($principal * $r) / (1 - pow(1 + $r, -$termMonths));

        return round($emi, 2);
    }

    /**
     * Generate a condensed summary for display.
     *
     * @param  float  $principal
     * @param  int    $termMonths
     * @param  float  $rate   Monthly rate.
     * @return array ['emi' => float, 'total_interest' => float, 'total_paid' => float]
     */
    public function summary(float $principal, int $termMonths, ?float $rate = null): array
    {
        $emi = $this->emi($principal, $termMonths, $rate);
        $totalPaid = round($emi * $termMonths, 2);
        $totalInterest = round($totalPaid - $principal, 2);

        return [
            'emi'           => $emi,
            'total_interest' => $totalInterest,
            'total_paid'    => $totalPaid,
        ];
    }
}
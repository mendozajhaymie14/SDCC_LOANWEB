<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Equal monthly installment (EMI) amortization service.
 * Uses the standard amortization formula with an add-on rate for simplicity,
 * matching the cooperative's flat-rate convention.
 */
class AmortizationService
{
    /** Annual interest rate (e.g., 0.12 = 12%) */
    protected float $rate;

    /**
     * Create a new service instance.
     *
     * @param  float  $rate
     *   Annual interest rate as a decimal. Defaults to 12% (cooperative default).
     */
    public function __construct(float $rate = 0.12)
    {
        $this->rate = $rate;
    }

    /**
     * Generate a full amortization schedule.
     *
     * @param  float  $principal
     *   Loan principal amount.
     * @param  int    $termMonths
     *   Number of monthly payments.
     * @param  float  $rate
     *   Annual interest rate (overrides constructor default if set).
     * @return Collection|array of
     *   ['month' => int, 'due_date' => \Carbon\Carbon, 'payment' => float,
     *    'principal' => float, 'interest' => float, 'remaining_balance' => float]
     */
    public function schedule(float $principal, int $termMonths, float $rate = null): Collection
    {
        $rate = $rate ?? $this->rate;
        $schedule = [];
        $remainingBalance = $principal;
        $monthlyRate = $rate / 12;

        for ($month = 1; $month <= $termMonths; $month++) {
            $dueDate = now()->addMonths($month - 1)->endOfMonth();
            // Interest for this month on remaining balance
            $interest = $remainingBalance * $monthlyRate;
            // Principal portion = EMI - interest (flat-addon EMI = (P + total_interest) / n)
            $totalInterest = $principal * $rate * ($termMonths / 12);
            $payment = round(($principal + $totalInterest) / $termMonths, 2);
            $principalPortion = round($payment - $interest, 2);
            // Ensure principal portion doesn't push balance negative
            $principalPortion = min($principalPortion, $remainingBalance);
            $remainingBalance = max(0, round($remainingBalance - $principalPortion, 2));

            $schedule[] = [
                'month' => $month,
                'due_date' => $dueDate,
                'payment' => $payment,
                'principal' => $principalPortion,
                'interest' => round($interest, 2),
                'remaining_balance' => $remainingBalance,
            ];
        }

        return collect($schedule);
    }

    /**
     * Calculate the equal monthly installment (EMI).
     *
     * @param  float  $principal
     *   Loan principal amount.
     * @param  int    $termMonths
     *   Number of monthly payments.
     * @param  float  $rate
     *   Annual interest rate (default: 12%).
     * @return float  Monthly payment amount.
     */
    public function emi(float $principal, int $termMonths, float $rate = null): float
    {
        $rate = $rate ?? $this->rate;
        $totalInterest = $principal * $rate * ($termMonths / 12);
        return round(($principal + $totalInterest) / $termMonths, 2);
    }

    /**
     * Generate a condensed summary for display.
     *
     * @param  float  $principal
     * @param  int    $termMonths
     * @param  float  $rate
     * @return array ['emi' => float, 'total_interest' => float, 'total_paid' => float]
     */
    public function summary(float $principal, int $termMonths, float $rate = null): array
    {
        $rate = $rate ?? $this->rate;
        $emi = $this->emi($principal, $termMonths, $rate);
        $totalInterest = $principal * $rate * ($termMonths / 12);
        return [
            'emi' => $emi,
            'total_interest' => round($totalInterest, 2),
            'total_paid' => round($emi * $termMonths, 2),
        ];
    }
}
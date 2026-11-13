<?php

namespace App\Services;

use App\Models\CoopMember;
use Illuminate\Support\Facades\Schema;

/**
 * Credit-limit recommendation service.
 *
 * Shares the affordable-principal math with LoanEvaluationService so both can
 * never disagree. The extraction from LoanEvaluationService::evaluate() (lines 74-89)
 * is UNCHANGED: maxPayment = income * dti_limit - obligations;
 * principal = maxPayment * (1 - (1+r)^-n) / r; round to 2dp.
 *
 * The service then applies floor-to-1000 rounding and the credit-limit cap,
 * plus an optional savings cap (disabled by default via savings_multiple = null).
 */
class CreditLimitService
{
    /** Number of decimal places for intermediate principal (matches evaluate()). */
    public const PRINCIPAL_PRECISION = 2;

    /** Floor-down rounding unit in pesos. */
    public const ROUNDING_UNIT = 1000;

    /** Default monthly rate when config is missing. */
    public const DEFAULT_MONTHLY_RATE = 0.01;

    /** Default DTI limit when config is missing. */
    public const DEFAULT_DTI_LIMIT = 0.35;

    /** Default cap in pesos when config is missing. */
    public const DEFAULT_CAP = 2000000;

    /** Default term in months when config is missing. */
    public const DEFAULT_TERM = 36;

    /**
     * Core affordable-principal math — extracted UNCHANGED from
     * LoanEvaluationService::evaluate().
     *
     * @return float Affordable principal rounded to 2dp (NOT floored to 1000 yet).
     */
    public function calculateAffordablePrincipal(
        float $income,
        float $obligations,
        int $termMonths = self::DEFAULT_TERM,
        ?float $monthlyRate = null,
    ): float {
        $monthlyRate = $monthlyRate ?? config('loan_matrix.interest.monthly_rate', self::DEFAULT_MONTHLY_RATE);
        $dtiLimit = config('loan_evaluation.thresholds.dti_limit', self::DEFAULT_DTI_LIMIT);

        $maxMonthlyPayment = $income * $dtiLimit;
        $emiBudget = $maxMonthlyPayment - $obligations;

        if ($emiBudget <= 0) {
            return 0;
        }

        $annuityFactor = $monthlyRate > 0
            ? $monthlyRate / (1 - pow(1 + $monthlyRate, -$termMonths))
            : (1 / $termMonths);

        $affordablePrincipal = $annuityFactor > 0
            ? $emiBudget / $annuityFactor
            : 0;

        return round($affordablePrincipal, self::PRINCIPAL_PRECISION);
    }

    /**
     * Floor down to nearest 1,000 pesos.
     */
    public function floorTo1000(float $value): float
    {
        if ($value < self::ROUNDING_UNIT) {
            return 0;
        }

        return (float) (floor($value / self::ROUNDING_UNIT) * self::ROUNDING_UNIT);
    }

    /**
     * Monthly repayment capacity per member.
     * monthly_capacity = max(0, income * dti_limit - obligations)
     * Single source of truth for capacity-based monthly credit limit.
     *
     * @param float $income Monthly income in pesos.
     * @param float $obligations Existing monthly obligations in pesos.
     * @return float Monthly credit limit capacity in pesos.
     */
    public function calculateMonthlyCapacity(float $income, float $obligations): float
    {
        $dtiLimit = config('loan_evaluation.thresholds.dti_limit', self::DEFAULT_DTI_LIMIT);
        return round(max(0.0, ($income * $dtiLimit) - $obligations), self::PRINCIPAL_PRECISION);
    }

    /**
     * Convert a monthly payment amount to the equivalent principal
     * using the annuity formula (inverse of EMI).
     *
     * EMI = P * r / (1 - (1+r)^-n)
     * P = EMI * (1 - (1+r)^-n) / r
     *
     * @param float $monthlyPayment  Monthly payment budget (EMI)
     * @param int   $termMonths      Loan term in months
     * @param float|null $monthlyRate Monthly interest rate (default: config or 0.01)
     * @return float Principal amount rounded to 2dp (NOT floored to 1000)
     */
    public function principalForMonthlyPayment(
        float $monthlyPayment,
        int $termMonths,
        ?float $monthlyRate = null,
    ): float {
        $monthlyRate = $monthlyRate ?? config('loan_matrix.interest.monthly_rate', self::DEFAULT_MONTHLY_RATE);

        if ($monthlyPayment <= 0) {
            return 0;
        }

        $annuityFactor = $monthlyRate > 0
            ? $monthlyRate / (1 - pow(1 + $monthlyRate, -$termMonths))
            : (1 / $termMonths);

        if ($annuityFactor <= 0) {
            return 0;
        }

        return round($monthlyPayment / $annuityFactor, self::PRINCIPAL_PRECISION);
    }

    /**
     * Compute the credit limit for a member.
     *
     * Returns ['limit' => float, 'cappedBy' => 'affordability'|'savings'|null].
     * The DTI-based principal is floored to 1,000 and capped at credit_limit_cap.
     * If savings_multiple is not null, the limit is min'd with
     * savings_multiple * savings_balance.
     *
     * Returns limit 0 when obligations >= 35% of income — callers must never
     * auto-store 0; leave the DB empty.
     */
    public function calculateCreditLimit(CoopMember $member): array
    {
        $config = config('loan_matrix.credit_limit', []);
        $term = (int) ($config['term'] ?? self::DEFAULT_TERM);
        $monthlyRate = config('loan_matrix.interest.monthly_rate', self::DEFAULT_MONTHLY_RATE);

        $income = (float) ($member->monthly_income ?? 0);
        $obligations = (float) ($member->existing_monthly_obligations ?? 0);
        $savings = (float) ($member->savings_balance ?? 0);

        return $this->calculateCreditLimitRaw($income, $obligations, $savings, $term, $monthlyRate);
    }

    /**
     * Format a suggestion string for the admin edit modal.
     */
    public function formatSuggestion(float $limit, string $cappedBy): string
    {
        if ($limit <= 0) {
            return 'Suggested: ₱0 (obligations at or above 35% of income)';
        }

        $label = match ($cappedBy) {
            'savings' => '(savings)',
            default   => '(affordability)',
        };

        return 'Suggested: ₱' . number_format($limit, 0, ',', ',') . ' ' . $label;
    }

    /**
     * Decide whether the stored credit-limit source should be recomputed
     * when income/obligations/savings change, given the source is 'auto'.
     */
    public function needsRecompute(
        CoopMember $member,
        float $newIncome,
        float $newObligations,
        ?float $newSavings,
    ): bool {
        if ($member->credit_limit_source !== 'auto') {
            return false;
        }

        $income = (float) ($member->monthly_income ?? 0);
        $obligations = (float) ($member->existing_monthly_obligations ?? 0);
        $savings = (float) ($member->savings_balance ?? 0);

        if (abs($newIncome - $income) > 0.01) return true;
        if (abs($newObligations - $obligations) > 0.01) return true;
        if ($newSavings !== null && abs($newSavings - $savings) > 0.01) return true;

        return false;
    }

    /**
     * Decide the server-side credit_limit_source by comparing the submitted
     * limit against the computed suggestion. Never trusts a hidden browser field.
     *
     * Returns 'auto' if the submitted value matches the computed suggestion,
     * 'manual' otherwise.
     */
    public function decideSource(float $submittedLimit, CoopMember $member): string
    {
        $result = $this->calculateCreditLimit($member);

        return $submittedLimit == $result['limit'] ? 'auto' : 'manual';
    }

    /**
     * Compute the credit limit from raw income/obligations/savings (no CoopMember required).
     *
     * Returns ['limit' => float, 'cappedBy' => 'affordability'|'savings'|null].
     * The DTI-based principal is floored to 1,000 and capped at credit_limit_cap.
     * If savings_multiple is not null, the limit is min'd with
     * savings_multiple * savings_balance.
     *
     * @param float $income          Monthly income in pesos.
     * @param float $obligations     Existing monthly obligations in pesos.
     * @param float $savings         Savings/time deposit balance in pesos.
     * @param int   $termMonths      Term in months (default: 36).
     * @param float $monthlyRate     Monthly interest rate (default: config or 0.01).
     *
     * @return array{limit: float, cappedBy: string|int|null}
     */
    public function calculateCreditLimitRaw(
        float $income,
        float $obligations,
        float $savings = 0,
        int $termMonths = self::DEFAULT_TERM,
        ?float $monthlyRate = null,
    ): array {
        $monthlyRate = $monthlyRate ?? config('loan_matrix.interest.monthly_rate', self::DEFAULT_MONTHLY_RATE);
        $dtiLimit = config('loan_evaluation.thresholds.dti_limit', self::DEFAULT_DTI_LIMIT);

        $maxMonthlyPayment = $income * $dtiLimit;
        $emiBudget = $maxMonthlyPayment - $obligations;

        if ($emiBudget <= 0) {
            $affordablePrincipal = 0;
        } else {
            $annuityFactor = $monthlyRate > 0
                ? $monthlyRate / (1 - pow(1 + $monthlyRate, -$termMonths))
                : (1 / $termMonths);

            $affordablePrincipal = $annuityFactor > 0
                ? $emiBudget / $annuityFactor
                : 0;
        }

        $limit = round($affordablePrincipal, CreditLimitService::PRINCIPAL_PRECISION);
        $limit = $this->floorTo1000($limit);

        $cap = (int) config('loan_matrix.credit_limit.cap', self::DEFAULT_CAP);
        $limit = min($limit, $cap);

        $cappedBy = 'affordability';

        $savingsMultiple = config('loan_matrix.credit_limit.savings_multiple') ?? null;
        if ($savingsMultiple !== null) {
            $savingsCap = (float) $savingsMultiple * $savings;

            if ($limit > $savingsCap) {
                $limit = $this->floorTo1000($savingsCap);
                $cappedBy = 'savings';
            }
        }

        return ['limit' => $limit, 'cappedBy' => $cappedBy];
    }
}

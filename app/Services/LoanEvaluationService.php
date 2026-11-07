<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Loan evaluation engine — single source of truth for recommended amount
 * and risk level. Called synchronously at application submit time.
 *
 * Scoring (sum to 100):
 *   - DTI affordability ........... 40 pts
 *   - Payment history ............. 30 pts
 *   - Document verification ....... 15 pts
 *   - Savings coverage ............ 15 pts
 *
 * Risk levels:  >=70 Low | 50–69 Medium | <50 High
 * ai_score < 50 is treated as a risk flag (syncs with the admin side).
 */
class LoanEvaluationService
{
    /**
     * Minimum savings balance for Share Capital Loan.
     */
    public const SAVINGS_MIN = 15000;

    /** Income warning threshold: warn when requested amount exceeds this fraction of monthly income. */
    public const INCOME_WARNING_THRESHOLD = 0.70;

    /**
     * Evaluate a loan application.
     *
     * @param  LoanApplication  $loanApp  Must have amount, term_months,
     *   monthly_income, existing_monthly_obligations, loan_type.
     *   The associated User must have a CoopMember profile (or null).
     * @return array{
     *   eligible: bool,
     *   recommended_amount: float,
     *   risk_level: string,
     *   risk_score: int,
     *   term_months: int,
     *   monthly_rate: float,
     *   rejection_reason: string|null,
     *   reasons: array<int,string>,
     *   details: array<string,mixed>
     * }
     */
    public function evaluate(LoanApplication $loanApp): array
    {
        $amount        = (float) $loanApp->amount;
        $termMonths    = (int)  ($loanApp->term_months ?? 12);
        $loanType      = $loanApp->loan_type;
        $monthlyIncome = (float) ($loanApp->monthly_income ?? 0);
        $existingOblig = (float) ($loanApp->existing_monthly_obligations ?? 0);

        // Member profile data from the linked User → CoopMember.
        $member = null;
        if ($loanApp->user_id) {
            $member = DB::table('coop_members')
                ->where('user_id', $loanApp->user_id)
                ->first();
        }
        $memberSavings      = (float) ($member?->savings_balance ?? 0);
        $approvedCreditLimit = $member?->approved_credit_limit ?? null;
        $isMigs              = (bool)  ($member?->is_migs ?? false);

        $monthlyRate = (float) config('loan_matrix.interest.monthly_rate', 0.01);
        $dtiLimit = (float) config('loan_evaluation.thresholds.dti_limit', 0.35);
        $amortization = app(AmortizationService::class);

        // ── Step 1: affordable principal via DTI ──────────────────────────
        // EMI(principal) = principal * r / (1 - (1+r)^-n)
        // Solve for principal: principal = EMI_budget / (r / (1-(1+r)^-n))
        $maxMonthlyPayment = $monthlyIncome * $dtiLimit;
        $emiBudget = $maxMonthlyPayment - $existingOblig;
        if ($emiBudget <= 0) {
            $affordablePrincipal = 0;
        } else {
            $annuityFactor = $monthlyRate > 0
                ? $monthlyRate / (1 - pow(1 + $monthlyRate, -$termMonths))
                : (1 / $termMonths);
            $affordablePrincipal = $annuityFactor > 0
                ? $emiBudget / $annuityFactor
                : 0;
            $affordablePrincipal = round($affordablePrincipal, 2);
        }

        // ── Step 2: product-specific cap ─────────────────────────────────
        $reasons      = [];
        $rejectionReason = null;
        $eligible     = true;
        $details      = [
            'affordable_principal'     => $affordablePrincipal,
            'monthly_rate'            => $monthlyRate,
            'max_monthly_payment'     => $maxMonthlyPayment,
            'existing_obligations'    => $existingOblig,
            'savings_balance'         => $memberSavings,
        ];

        $recommendedAmount = match ($loanType) {
            'character'  => $this->characterAmount($amount, $memberSavings, $affordablePrincipal),
            'regular'    => $this->minAndRound($amount, $affordablePrincipal),
            'educational'=> $this->minAndRound($amount, $affordablePrincipal),
            'emergency'  => $this->roundDown1000(min($amount, $affordablePrincipal * 0.20)),
            'healthcare' => $this->roundDown1000(min($amount, $affordablePrincipal)),
            'micro'      => $this->roundDown1000(min($amount, 100000)),
            'utility'    => $this->roundDown1000(min($amount, 10000)),
            'secured'    => $this->roundDown1000(min($amount, max($memberSavings ?: $amount))),
            'share_cap'  => $this->roundDown1000(max(0, min(self::SAVINGS_MIN - (int) $memberSavings, $amount))),
            'special'    => $this->specialAmount($amount, $memberSavings, $existingOblig, $monthlyIncome, $monthlyRate),
            'recovery'   => min($amount, $this->recoveryAmount($memberSavings, $isMigs)),
            default      => $this->roundDown1000(min($amount, $affordablePrincipal)),
        };

        // If nothing can be afforded (for affordability-based products),
        // mark the application ineligible.
        if ($recommendedAmount <= 0 && $loanType !== 'share_cap') {
            $eligible = false;
            if (!$rejectionReason) {
                $rejectionReason = 'Unable to afford monthly payment within DTI limit.';
            }
        }

        if ($loanType === 'special' && $memberSavings < 100000) {
            $eligible = false;
            $rejectionReason = 'Special Loan requires at least ₱100,000 savings balance.';
            $recommendedAmount = 0;
        }

        if ($loanType === 'share_cap' && !$loanApp->user) {
            $reasons[] = 'Share Capital Loan requires an active member account.';
        }

        // ── 70% income threshold warning (non-blocking) ──────────────────
        // Warn when the requested amount exceeds 70% of monthly income.
        if ($monthlyIncome > 0 && $amount > $monthlyIncome * self::INCOME_WARNING_THRESHOLD) {
            $reasons[] = sprintf(
                'Requested amount exceeds 70%% of monthly income (₱%.0f vs ₱%.0f).',
                $amount,
                $monthlyIncome * self::INCOME_WARNING_THRESHOLD
            );
        }

        // ── Step 3: risk scoring ─────────────────────────────────────────
        // 3a. DTI affordability  (0–40 pts) — computed on the REQUESTED amount
        // so a request far above what is affordable pulls the score down.
        // Graded: full points at or below the DTI limit, falling linearly
        // to 0 when the ratio reaches 2 x the limit.
        $emi = $amortization->emi($amount, $termMonths, $monthlyRate);
        $dtiRatio = $monthlyIncome > 0
            ? ($existingOblig + $emi) / $monthlyIncome
            : 1.0;
        $dtiWeight = (int) config('loan_evaluation.weights.dti', 40);
        if ($dtiRatio <= $dtiLimit) {
            $dtiScore = $dtiWeight;
        } else {
            $dtiScore = max(0, (int) round($dtiWeight * (1 - (($dtiRatio - $dtiLimit) / $dtiLimit))));
        }

        // 3b. Payment history  (0–30 pts)
        $paymentScore = $this->paymentHistoryScore($loanApp);

        // 3c. Document verification  (0–15 pts)
        $hasDocs = $loanApp->documents && count((array) $loanApp->documents) > 0;
        $docScore = $hasDocs ? 15 : 0;

        // 3d. Savings coverage  (0–15 pts) — replaces share-capital coverage.
        $savingsScore = $this->savingsScore($memberSavings, $amount);

        $riskScore = $dtiScore + $paymentScore + $docScore + $savingsScore;

        $riskLow = (int) config('loan_evaluation.thresholds.risk_low', 70);
        $riskMedium = (int) config('loan_evaluation.thresholds.risk_medium', 50);
        $riskLevel = $riskScore >= $riskLow
            ? 'Low'
            : ($riskScore >= $riskMedium ? 'Medium' : 'High');

        // Affordability override (after risk level is computed):
        // DTI ratio > 1.5 x limit forces High; DTI ratio > limit forces
        // at least Medium.
        $overrideMultiplier = (float) config('loan_evaluation.thresholds.dti_override_multiplier', 1.5);
        if ($dtiRatio > $dtiLimit * $overrideMultiplier && $riskLevel !== 'High') {
            $riskLevel = 'High';
            $reasons[] = 'Debt-to-income ratio far exceeds the limit; risk forced to High.';
        } elseif ($dtiRatio > $dtiLimit && $riskLevel === 'Low') {
            $riskLevel = 'Medium';
            $reasons[] = 'Debt-to-income ratio exceeds the limit; risk elevated to Medium.';
        }

        // If requested amount exceeds 2x the recommended amount,
        // risk must be at least Medium (cannot be Low)
        $escalationMultiple = (float) config('loan_evaluation.thresholds.request_escalation_multiple', 2);
        if ($amount > $escalationMultiple * $recommendedAmount && $riskLevel === 'Low') {
            $riskLevel = 'Medium';
            $reasons[] = 'Requested amount exceeds recommended by more than 2x; risk elevated to Medium.';
        }

        // ── Build plain-language reasons ─────────────────────────────────
        if ($rejectionReason) {
            $reasons[] = $rejectionReason;
        }
        if (!$hasDocs) {
            $reasons[] = 'No supporting documents uploaded.';
        }
        if ($approvedCreditLimit !== null && $approvedCreditLimit === 0) {
            $reasons[] = 'Credit limit not assessed — evaluation cannot proceed fully.';
        }
        if ($dtiRatio > $dtiLimit) {
            $reasons[] = sprintf(
                'Debt-to-income ratio (%.1f%%) exceeds the %.0f%% limit.',
                $dtiRatio * 100,
                $dtiLimit * 100
            );
        }
        if (empty($reasons)) {
            $reasons[] = sprintf(
                'Eligible for ₱%s based on DTI and product rules; risk level %s.',
                number_format($recommendedAmount, 2),
                $riskLevel
            );
        }

        return [
            'eligible'           => $eligible,
            'recommended_amount' => $recommendedAmount,
            'risk_level'         => $riskLevel,
            'risk_score'         => $riskScore,
            'term_months'        => $termMonths,
            'monthly_rate'       => $monthlyRate,
            'rejection_reason'   => $rejectionReason,
            'reasons'            => $reasons,
            'details'            => array_merge($details, [
                'dti_ratio'        => round($dtiRatio, 4),
                'emi'              => $emi,
                'dti_score'        => $dtiScore,
                'payment_score'    => $paymentScore,
                'doc_score'        => $docScore,
                'savings_score'    => $savingsScore,
                'is_migs'          => $isMigs,
            ]),
        ];
    }

    // ── Product helpers ──────────────────────────────────────────────────

    private function characterAmount(float $requested, float $savingsBalance, float $affordable): float
    {
        // Tier 200% of savings balance (requires DTI pass)
        $tier200 = min($requested, $savingsBalance * 2);
        if ($tier200 > 0 && $tier200 <= $affordable) {
            return $this->roundDown1000($tier200);
        }

        // Tier 100% of savings balance (requires DTI pass)
        $tier100 = min($requested, $savingsBalance);
        if ($tier100 > 0 && $tier100 <= $affordable) {
            return $this->roundDown1000($tier100);
        }

        // Tier 50% of savings balance (no DTI requirement)
        $tier50 = min($requested, $savingsBalance * 0.5);
        return $this->roundDown1000($tier50);
    }

    private function specialAmount(float $requested, float $savingsBalance, float $obligations, float $income, float $monthlyRate): float
    {
        // Special Loan: savings_balance >= 100 000; amount = min(requested, affordable principal
        // for a 120-month term under the same DTI rule).
        if ($savingsBalance < 100000) {
            return 0.0;
        }

        $term120 = 120;
        $affordable120 = $this->computeAffordablePrincipal($income, $obligations, $term120, $monthlyRate);
        // Cap by income-based DTI for 120 months
        return $this->roundDown1000(min($requested, $affordable120));
    }

    private function recoveryAmount(float $savingsBalance, bool $isMigs): float
    {
        if ($savingsBalance <= 0) {
            return 0.0;
        }

        if ($isMigs) {
            // 50 % of savings balance, capped at 30 000
            return $this->roundDown1000(min($savingsBalance * 0.5, 30000));
        }

        // 70 % of 50 % = 35 % of savings balance, capped at 15 000
        return $this->roundDown1000(min($savingsBalance * 0.35, 15000));
    }

    private function minAndRound(float $a, float $b): float
    {
        return $this->roundDown1000(min($a, $b));
    }

    private function roundDown1000(float $value): float
    {
        if ($value < 1000) {
            return 0;
        }
        return (float) (floor($value / 1000) * 1000);
    }

    private function computeAffordablePrincipal(float $income, float $obligations, int $termMonths, float $monthlyRate): float
    {
        $dtiLimit = (float) config('loan_evaluation.thresholds.dti_limit', 0.35);
        $budget = ($income * $dtiLimit) - $obligations;
        if ($budget <= 0) {
            return 0;
        }

        $annuityFactor = $monthlyRate > 0
            ? $monthlyRate / (1 - pow(1 + $monthlyRate, -$termMonths))
            : 0;
        if ($annuityFactor <= 0) {
            return 0;
        }

        return round($budget / $annuityFactor, 2);
    }

    /**
     * Payment history score (0–30 pts).
     * Checks Payments across all of this member's earlier loan applications.
     * No loan history → neutral 20/30.
     * Late = payment date after the due date (disbursement + n months).
     * 0 late → 30 | 5 pts deducted per late payment | minimum 0.
     */
    private function paymentHistoryScore(LoanApplication $loanApp): int
    {
        $userId = $loanApp->user_id;
        if (!$userId) {
            return (int) config('loan_evaluation.thresholds.no_history_score', 20);
        }

        $earlierApps = LoanApplication::where('user_id', $userId)
            ->where('id', '!=', $loanApp->id)
            ->get();

        $latePayments = 0;
        $totalPayments = 0;

        foreach ($earlierApps as $app) {
            $payments = Payment::where('loan_application_id', $app->id)
                ->orderBy('payment_date', 'asc')
                ->get();

            $disbursed = $app->disbursement_date ?? $app->created_at;

            foreach ($payments as $i => $payment) {
                $totalPayments++;
                $dueDate = Carbon::parse($disbursed)->addMonths($i + 1);
                if ($payment->payment_date > $dueDate) {
                    $latePayments++;
                }
            }
        }

        if ($totalPayments === 0) {
            return (int) config('loan_evaluation.thresholds.no_history_score', 20);
        }

        $penalty = (int) config('loan_evaluation.thresholds.late_payment_penalty', 5);
        $neutral = (int) config('loan_evaluation.thresholds.no_history_score', 20);

        return max(0, $neutral - ($latePayments * $penalty));
    }

    /**
     * Savings coverage score (0–15 pts).
     * How well the REQUESTED amount is covered by savings balance.
     * coverage = savings_balance / requested amount.
     */
    private function savingsScore(float $savingsBalance, float $amount): int
    {
        if ($amount <= 0 || $savingsBalance <= 0) {
            return 0;
        }
        $coverage = $savingsBalance / $amount;
        $bands = config('loan_evaluation.thresholds.savings_coverage', [
            'full' => 1.0, 'partial' => 0.5, 'minimal' => 0.2,
        ]);
        if ($coverage >= (float) $bands['full'])  return 15;
        if ($coverage >= (float) $bands['partial'])  return 10;
        if ($coverage >= (float) $bands['minimal']) return 5;
        return 0;
    }
}

<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Loan evaluation engine — single source of truth for recommended amount
 * and risk level. Called synchronously at application submit time.
 *
 * Scoring (sum to 100):
 *   - DTI affordability ........... 40 pts
 *   - Payment history ............. 30 pts
 *   - Document verification ....... 15 pts
 *   - Share-capital coverage .....  15 pts
 *
 * Risk levels:  >=70 Low | 50–69 Medium | <50 High
 * ai_score < 50 is treated as a risk flag (syncs with the admin side).
 */
class LoanEvaluationService
{
    /**
     * DTI cap: (existing obligations + EMI) must not exceed this fraction
     * of gross monthly income. Placeholder — confirm with SDCC.
     */
    public const DTI_LIMIT = 0.35;

    /** Minimum share capital for Share Capital Loan. */
    public const SHARE_CAPITAL_MIN = 15000;

    /** Risk-level score thresholds. */
    public const RISK_LOW = 70;
    public const RISK_MEDIUM = 50;

    /**
     * Evaluate a loan application.
     *
     * @param  LoanApplication  $loanApp  Must have amount, term_months,
     *   monthly_income, existing_monthly_obligations, share_capital, loan_type.
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
        $shareCapital  = (float) ($loanApp->share_capital ?? 0);

        // Member profile data from the linked User → CoopMember.
        $member = null;
        if ($loanApp->user_id) {
            $member = DB::table('coop_members')
                ->where('user_id', $loanApp->user_id)
                ->first();
        }
        $memberShareCapital = (float) ($member?->share_capital ?? $shareCapital ?? 0);
        $memberSavings      = (float) ($member?->savings_balance ?? 0);
        $approvedCreditLimit = $member?->approved_credit_limit ?? null;
        $isMigs              = (bool)  ($member?->is_migs ?? false);

        $monthlyRate = (float) config('loan_matrix.interest.monthly_rate', 0.01);
        $amortization = app(AmortizationService::class);

        // ── Step 1: affordable principal via DTI ──────────────────────────
        // EMI(principal) = principal * r / (1 - (1+r)^-n)
        // Solve for principal: principal = EMI_budget / (r / (1-(1+r)^-n))
        $maxMonthlyPayment = $monthlyIncome * self::DTI_LIMIT;
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
            'share_capital'           => $memberShareCapital,
        ];

        $recommendedAmount = match ($loanType) {
            'character'  => $this->characterAmount($amount, $memberShareCapital, $affordablePrincipal),
            'regular'    => $this->minAndRound($amount, $affordablePrincipal),
            'educational'=> $this->minAndRound($amount, $affordablePrincipal),
            'emergency'  => $this->roundDown1000(min($amount, $affordablePrincipal * 0.20)),
            'healthcare' => $this->roundDown1000(min($amount, $affordablePrincipal)),
            'micro'      => $this->roundDown1000(min($amount, 100000)),
            'utility'    => $this->roundDown1000(min($amount, 10000)),
            'secured'    => $this->roundDown1000(min($amount, max($memberShareCapital ?: $amount))),
            'share_cap'  => $this->roundDown1000(max(0, min(self::SHARE_CAPITAL_MIN - (int) $memberShareCapital, $amount))),
            'special'    => $this->specialAmount($amount, $memberShareCapital, $existingOblig, $monthlyIncome, $monthlyRate),
            'recovery'   => min($amount, $this->recoveryAmount($memberShareCapital, $isMigs)),
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

        if ($loanType === 'special' && $memberShareCapital < 100000) {
            $eligible = false;
            $rejectionReason = 'Special Loan requires at least ₱100,000 share capital.';
            $recommendedAmount = 0;
        }

        if ($loanType === 'share_cap' && !$loanApp->user) {
            $reasons[] = 'Share Capital Loan requires an active member account.';
        }

        // ── Step 3: risk scoring ─────────────────────────────────────────
        // 3a. DTI affordability  (0–40 pts) — computed on the REQUESTED amount
        // so a request far above what is affordable pulls the score down.
        $emi = $amortization->emi($amount, $termMonths, $monthlyRate);
        $dtiRatio = $monthlyIncome > 0
            ? ($existingOblig + $emi) / $monthlyIncome
            : 1.0;
        $dtiScore  = $dtiRatio <= self::DTI_LIMIT ? 40 : max(0, (int) round(40 * (1 - $dtiRatio / self::DTI_LIMIT)));

        // 3b. Payment history  (0–30 pts)
        $paymentScore = $this->paymentHistoryScore($loanApp, $memberShareCapital);

        // 3c. Document verification  (0–15 pts)
        $hasDocs = $loanApp->documents && count((array) $loanApp->documents) > 0;
        $docScore = $hasDocs ? 15 : 0;

        // 3d. Share-capital coverage  (0–15 pts)
        $capScore = $this->shareCapitalScore($memberShareCapital, $amount);

        $riskScore = $dtiScore + $paymentScore + $docScore + $capScore;

        $riskLevel = $riskScore >= self::RISK_LOW
            ? 'Low'
            : ($riskScore >= self::RISK_MEDIUM ? 'Medium' : 'High');

        // If requested amount exceeds 2x the recommended amount,
        // risk must be at least Medium (cannot be Low)
        if ($amount > 2 * $recommendedAmount && $riskLevel === 'Low') {
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
        if ($dtiRatio > self::DTI_LIMIT) {
            $reasons[] = sprintf(
                'Debt-to-income ratio (%.1f%%) exceeds the %.0f%% limit.',
                $dtiRatio * 100,
                self::DTI_LIMIT * 100
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
                'share_cap_score'  => $capScore,
                'is_migs'          => $isMigs,
            ]),
        ];
    }

    // ── Product helpers ──────────────────────────────────────────────────

    private function characterAmount(float $requested, float $shareCapital, float $affordable): float
    {
        // Tier 200% of share capital (requires DTI pass)
        $tier200 = min($requested, $shareCapital * 2);
        if ($tier200 > 0 && $tier200 <= $affordable) {
            return $this->roundDown1000($tier200);
        }

        // Tier 100% of share capital (requires DTI pass)
        $tier100 = min($requested, $shareCapital);
        if ($tier100 > 0 && $tier100 <= $affordable) {
            return $this->roundDown1000($tier100);
        }

        // Tier 50% of share capital (no DTI requirement)
        $tier50 = min($requested, $shareCapital * 0.5);
        return $this->roundDown1000($tier50);
    }

    private function specialAmount(float $requested, float $shareCapital, float $obligations, float $income, float $monthlyRate): float
    {
        // Special Loan: share_capital >= 100 000; amount = min(requested, affordable principal
        // for a 120-month term under the same DTI rule).
        if ($shareCapital < 100000) {
            return 0.0;
        }

        $term120 = 120;
        $affordable120 = $this->computeAffordablePrincipal($income, $obligations, $term120, $monthlyRate);
        // Cap by income-based DTI for 120 months
        return $this->roundDown1000(min($requested, $affordable120));
    }

    private function recoveryAmount(float $shareCapital, bool $isMigs): float
    {
        if ($shareCapital <= 0) {
            return 0.0;
        }

        if ($isMigs) {
            // 50 % of share capital, capped at 30 000
            return $this->roundDown1000(min($shareCapital * 0.5, 30000));
        }

        // 70 % of 50 % = 35 % of share capital, capped at 15 000
        return $this->roundDown1000(min($shareCapital * 0.35, 15000));
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
        $budget = ($income * self::DTI_LIMIT) - $obligations;
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
     * Checks Payments table for late payments.
     * No loan history → neutral 20/30 (not full marks).
     * 0 late → 30 | has late → proportional deduction | all overdue → 0.
     */
    private function paymentHistoryScore(LoanApplication $loanApp, float $shareCapital): int
    {
        $latePayments = Payment::where('loan_application_id', $loanApp->id)
            ->where('payment_date', '<', now()->subDays(30))
            ->count();
        $totalPayments = Payment::where('loan_application_id', $loanApp->id)->count();

        if ($totalPayments === 0) {
            return 20; // neutral — no history to judge
        }

        // Deduct 5 points per late/missed payment, minimum 0.
        return max(0, 30 - ($latePayments * 5));
    }

    /**
     * Share-capital coverage score (0–15 pts).
     * How well the REQUESTED amount is covered by share capital.
     */
    private function shareCapitalScore(float $shareCapital, float $amount): int
    {
        if ($amount <= 0 || $shareCapital <= 0) {
            return 0;
        }
        $coverage = $shareCapital / $amount;
        if ($coverage >= 1.0)  return 15;
        if ($coverage >= 0.5)  return 10;
        if ($coverage >= 0.2)  return 5;
        return 0;
    }
}
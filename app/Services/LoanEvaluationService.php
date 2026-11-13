<?php

namespace App\Services;

use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\Application;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Loan evaluation engine — single source of truth for recommended amount
 * and risk level. Called synchronously at application submit time.
 *
 * Scoring (sum to 100):
 *   - DTI affordability ........... 45 pts
 *   - Payment history ............. 35 pts
 *   - Document verification ....... 0 pts (advisory only)
 *   - Savings coverage ............ 20 pts
 *
 * Risk levels:  >=70 Low | 50–69 Medium | <50 High
 * ai_score < 50 is treated as a risk flag (syncs with the admin side).
 */
class LoanEvaluationService
{
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

        // Member profile data — resolved via helper: users.coop_member_id → coop_members.user_id → email fallback.
        $coopMember = CoopMember::forLoanApplication($loanApp);
        $memberSavings      = (float) ($coopMember?->savings_balance ?? 0);
        $approvedCreditLimit = $coopMember?->approved_credit_limit !== null ? (float) $coopMember->approved_credit_limit : null;
        $isMigs              = (bool)  ($coopMember?->is_migs ?? false);
        $monthlyUsed         = $coopMember?->monthlyUsed() ?? 0.0;

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

        $requiresLimit = true;

        // ── Step 2a: missing / zero credit-limit handling (limit-required products only) ────────────────────────
        if ($requiresLimit && $approvedCreditLimit === null) {
            $eligible = false;
            $rejectionReason = 'Credit limit not assessed. Please contact the cooperative office.';
            $recommendedAmount = 0;
            $details['credit_limit'] = null;
            $details['available_credit'] = null;
            $details['outstanding'] = 0;
            $details['limit_applied'] = false;
        } elseif ($requiresLimit && $approvedCreditLimit !== null && $approvedCreditLimit == 0) {
            $eligible = false;
            $rejectionReason = 'Credit limit assessed at zero — no borrowing capacity.';
            $recommendedAmount = 0;
            $details['credit_limit'] = 0;
            $details['available_credit'] = 0;
            $details['outstanding'] = 0;
            $details['limit_applied'] = false;
        }

        $recommendedAmount = match ($loanType) {
            'regular'    => $this->minAndRound($amount, $affordablePrincipal),
            'educational'=> $approvedCreditLimit !== null && $approvedCreditLimit != 0
                ? $this->roundDown1000(min($amount, $approvedCreditLimit))
                : 0,
            'emergency'  => $approvedCreditLimit !== null && $approvedCreditLimit != 0
                ? $this->roundDown1000(min($amount, $approvedCreditLimit * 0.20))
                : 0,
            'healthcare' => $this->roundDown1000(min($amount, $affordablePrincipal)),
            'micro'      => $this->roundDown1000(min($amount, 100000)),
            'utility'    => $this->roundDown1000(min($amount, 10000)),
            'special'    => $this->specialAmount($amount, $memberSavings, $existingOblig, $monthlyIncome, $monthlyRate),
            default      => $this->roundDown1000(min($amount, $affordablePrincipal)),
        };

        // If limit check already rejected, enforce recommended_amount = 0
        if (!$eligible) {
            $recommendedAmount = 0;
        }

        // ── Cap at available credit for limit-required products when assessed ─────────────────────────────
        if ($eligible && $requiresLimit && $approvedCreditLimit !== null && $approvedCreditLimit != 0) {
            $memberModel = CoopMember::where('user_id', $loanApp->user_id)->first();
            $outstanding = $memberModel
                ? CoopMember::outstandingPrincipal($memberModel)
                : 0;
            $availableCredit = max(0, (float) $approvedCreditLimit - $outstanding);
            if ($availableCredit < $recommendedAmount) {
                $recommendedAmount = $this->roundDown1000($availableCredit);
                $reasons[] = sprintf('Request exceeds available credit of ₱%s. Capped at ₱%s.',
                    number_format($availableCredit, 2),
                    number_format($recommendedAmount, 2)
                );
            }
            $details['credit_limit'] = (float) $approvedCreditLimit;
            $details['outstanding'] = $outstanding;
            $details['available_credit'] = $availableCredit;
            $details['limit_applied'] = true;
        }

        // ── Cap at monthly available for new loans exceeding capacity ─────────────────────────────
        // If a new loan's monthly payment exceeds the member's available capacity,
        // cap the recommended amount. Override decisions live in the controller
        // (AdminController::approve), not in the evaluation service.
        if ($eligible && $requiresLimit && $approvedCreditLimit !== null && $approvedCreditLimit != 0) {
            // coopMember already resolved at method start via forLoanApplication()
            $available = $coopMember?->monthlyAvailable();
            if ($available !== null && $available > 0) {
                $emi = $amortization->emi($recommendedAmount, $termMonths, $monthlyRate);
                if ($emi > $available) {
                    $capped = $this->roundDown1000(
                        app(CreditLimitService::class)->principalForMonthlyPayment($available, $termMonths, $monthlyRate)
                    );
                    $recommendedAmount = $capped;
                    $reasons[] = sprintf('Monthly payment exceeds available capacity of ₱%s. Capped at ₱%s.',
                        number_format($available, 2),
                        number_format($capped, 2)
                    );
                }
            }
            // If $available is null (income unknown), we don't cap and don't reject:
            // override decisions live in the controller, not here.
        }

        // If nothing can be affordable (for affordability-based products),
        // mark the application ineligible.
        if ($recommendedAmount <= 0) {
            $eligible = false;
            if (!$rejectionReason) {
                $rejectionReason = 'Unable to afford monthly payment within DTI limit.';
            }
        }

        $minimumSavings = (float) config('loan_evaluation.loan_constants.special_loan_minimum', 100000);

        if ($loanType === 'special' && $memberSavings < $minimumSavings) {
            $eligible = false;
            $rejectionReason = sprintf('Special Loan requires at least ₱%s savings balance.', number_format($minimumSavings, 0));
            $recommendedAmount = 0;
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
            ? ($existingOblig + $monthlyUsed + $emi) / $monthlyIncome
            : 1.0;
        $dtiWeight = (int) config('loan_evaluation.weights.dti', 40);
        if ($dtiRatio <= $dtiLimit) {
            $dtiScore = $dtiWeight;
        } else {
            $dtiScore = max(0, (int) round($dtiWeight * (1 - (($dtiRatio - $dtiLimit) / $dtiLimit))));
        }

        // 3b. Payment history  (0–30 pts)
        $paymentScore = $this->paymentHistoryScore($loanApp);

        // 3c. Document verification  (0–0 pts; weight 0 = advisory only)
        $hasDocs = $loanApp->documents && count((array) $loanApp->documents) > 0;
        $docScore = $hasDocs ? (int) config('loan_evaluation.weights.documents', 0) : 0;

        // 3d. Savings coverage  (0–15 pts) — replaces share-capital coverage.
        $savingsScore = $this->savingsScore($memberSavings, $amount);

        $riskScore = $dtiScore + $paymentScore + $docScore + $savingsScore;

        $riskLow = (int) config('loan_evaluation.thresholds.risk_low', 70);
        $riskMedium = (int) config('loan_evaluation.thresholds.risk_medium', 50);
        $riskLevel = $riskScore >= $riskLow
            ? 'Low'
            : ($riskScore >= $riskMedium ? 'Medium' : 'High');

        // Hard overrides: limit-required product with missing/zero limit => High
        if ($requiresLimit && $approvedCreditLimit === null) {
            $riskLevel = 'High';
            $reasons[] = 'Credit limit not assessed. Please contact the cooperative office; risk forced to High.';
        } elseif ($requiresLimit && $approvedCreditLimit !== null && $approvedCreditLimit == 0) {
            $riskLevel = 'High';
            $reasons[] = 'Credit limit assessed at zero — no borrowing capacity; risk forced to High.';
        }

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
            $reasons[] = 'Credit limit assessed at zero — no borrowing capacity.';
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
            'evaluation_reasons'   => $reasons,
        ];
    }

    // ── Product helpers ──────────────────────────────────────────────────

    private function minAndRound(float $a, float $b): float
{
    return $this->roundDown1000(min($a, $b));
}

    /**
     * Special Loan: savings_balance >= config('loan_evaluation.loan_constants.special_loan_minimum');
     * amount = min(requested, affordable principal for a 120-month term under the same DTI rule).
     */
    private function specialAmount(float $requested, float $savingsBalance, float $obligations, float $income, float $monthlyRate): float
    {
        $minimumSavings = (float) config('loan_evaluation.loan_constants.special_loan_minimum', 100000);

        if ($savingsBalance < $minimumSavings) {
            return 0.0;
        }

        $term120 = 120;
        $affordable120 = $this->computeAffordablePrincipal($income, $obligations, $term120, $monthlyRate);
        // Cap by income-based DTI for 120 months
        return $this->roundDown1000(min($requested, $affordable120));
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
     * Payment history score (0–35 pts).
     * Checks Payments across all of this member's earlier loan applications.
     * No loan history → no_history_score (default 18).
     * Late = payment date after the due date (disbursement + n months).
     * 0 late → 35 | penalty per late payment based on config; minimum 0.
     */
    private function paymentHistoryScore(LoanApplication $loanApp): int
    {
        $userId = $loanApp->user_id;
        if (!$userId) {
            return (int) config('loan_evaluation.thresholds.no_history_score', 18);
        }

        $earlierApps = LoanApplication::where('user_id', $loanApp->user_id)
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
            return (int) config('loan_evaluation.thresholds.no_history_score', 18);
        }

        $penalty = (int) config('loan_evaluation.thresholds.late_payment_penalty', 10);

        $maxScore = (int) config('loan_evaluation.weights.payment_history', 35);

        return max(0, $maxScore - ($latePayments * $penalty));
    }

    /**
     * Savings coverage score (0–20 pts).
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
        $maxScore = (int) config('loan_evaluation.weights.savings', 20);
        if ($coverage >= (float) $bands['full'])  return $maxScore;
        if ($coverage >= (float) $bands['partial'])  return (int) round($maxScore / 2);
        if ($coverage >= (float) $bands['minimal']) return (int) round($maxScore / 4);
        return 0;
    }
}

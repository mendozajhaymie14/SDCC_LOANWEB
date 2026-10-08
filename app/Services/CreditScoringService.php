<?php

namespace App\Services;

class CreditScoringService
{
    /**
     * Calculates credit score (Range: 300 - 850) based on AI extraction
     * and declared application data using cooperative financial rules.
     */
    public function evaluateCapacity(float $requestedAmount, float $declaredIncome, array $extraction): array
    {
        $score = 600; // Base baseline score
        $reasons = [];

        // 1. Check Document Legibility
        if (!($extraction['is_legible'] ?? true)) {
            return [
                'score' => 350,
                'status' => 'Rejected',
                'summary' => 'Uploaded document was unreadable or poor quality.',
            ];
        }

        // 2. Income Verification Comparison
        $verifiedIncome = $extraction['monthly_net_income'] ?? null;
        if ($verifiedIncome && $declaredIncome > 0) {
            $variance = abs($verifiedIncome - $declaredIncome) / $declaredIncome;
            if ($variance > 0.25) {
                $score -= 50;
                $reasons[] = 'Declared income differs significantly from scanned document.';
            } else {
                $score += 50;
                $reasons[] = 'Declared income verified by document scan.';
            }
        }

        // 3. Debt Service Ratio (Loan Amount vs Monthly Net Income)
        if ($verifiedIncome && $verifiedIncome > 0) {
            $loanToIncomeRatio = $requestedAmount / $verifiedIncome;
            if ($loanToIncomeRatio <= 3.0) {
                $score += 120;
                $reasons[] = 'Low debt ratio relative to monthly income.';
            } elseif ($loanToIncomeRatio <= 6.0) {
                $score += 80;
                $reasons[] = 'Moderate debt ratio relative to monthly income.';
            } else {
                $score -= 100;
                $reasons[] = 'Requested loan exceeds safe monthly income capacity.';
            }
        }

        // 4. Fraud Flags Penalty
        if (!empty($extraction['fraud_flags'])) {
            $score -= 150;
            $reasons[] = 'Risk flags detected on submitted document.';
        }

        // Clamp final score range between 300 and 850
        $finalScore = max(300, min(850, $score));

        // Determine Loan Status
        $status = 'Pending Review';
        if ($finalScore >= 700) {
            $status = 'Approved';
        } elseif ($finalScore < 500) {
            $status = 'Rejected';
        }

        return [
            'score' => $finalScore,
            'status' => $status,
            'summary' => implode('; ', $reasons),
        ];
    }
}
<?php

return [

/*
|--------------------------------------------------------------------------
| Loan Evaluation Engine
|--------------------------------------------------------------------------
|
| Weights, thresholds and product constants used by
| App\Services\LoanEvaluationService. Everything the rule engine decides
| lives here, so a weight can be changed without a code deploy.
|
| The four weights must add up to 100 — that is the total risk score.
*/

/*
| Risk score weights (must total 100).
*/
'weights' => [
    'dti'             => 45, // affordability: (existing obligations + EMI) / income
    'payment_history' => 35, // repayment behaviour on the applicant's loans
    'documents'       => 0,  // supporting documents on file
    'savings'         => 20, // savings balance covers the requested amount
],

/*
| Thresholds.
*/
'thresholds' => [
    // Share of gross monthly income that (obligations + EMI) may not exceed.
    'dti_limit' => 0.35,

    // Score at or above which the application is Low risk / Medium risk.
    'risk_low'    => 70,
    'risk_medium' => 50,

    // Savings-coverage bands for the 20-point savings score.
    // 'full' means savings >= requested amount, 'partial' means >= 50%,
    // 'minimal' means >= 20% of the requested amount.
    'savings_coverage' => [
        'full'   => 1.0,
        'partial' => 0.5,
        'minimal' => 0.2,
    ],

    // Points deducted per late payment, and the neutral score used when
    // there is no payment history to judge.
    'late_payment_penalty' => 10,
    'no_history_score'     => 18,

    // A request this many times the recommended amount can never be Low risk.
    'request_escalation_multiple' => 2,

    // DTI override multiplier: DTI > 1.5 x limit forces High.
    'dti_override_multiplier' => 1.5,

    // Days after which a recorded payment counts as late — compared to the
    // loan's due date, not a fixed 30-day window.
    'late_payment_after_days' => 30,
],

/*
| Loan product constants.
*/
'loan_constants' => [
    // Special Loan requires this much savings balance.
    'special_loan_minimum' => 100000,
],

/*
| Recovery Loan (calamity) rules.
*/
'recovery' => [
    'migs_rate'          => 0.5,
    'migs_cap'           => 30000,
    'non_migs_rate'      => 0.35,
    'non_migs_cap'       => 15000,
],
];
# Rule-based scoring weights — `LoanEvaluationService`

Source of truth: `config/loan_evaluation.php` (weights + thresholds) and
`app/Services/LoanEvaluationService.php` (evaluation order). The engine holds
no hard-coded weights; every number below is config-driven.

## Weights (must total 100)

| Component        | Weight | Meaning                                                        |
|------------------|-------:|----------------------------------------------------------------|
| `dti`            | 30     | Affordability: (existing obligations + new EMI) / income       |
| `payment_history`| 35     | Repayment behaviour on the applicant's loans                   |
| `documents`      | 0      | Supporting documents on file (advisory only)                   |
| `savings`        | 20     | Savings balance covers the requested amount                    |
| `monthly_limit`  | 15     | Monthly credit limit headroom vs. requested amount             |

`risk_score` is the sum of the five component scores (0–100).

## Monthly-limit component

```
monthly_limit_score = round(15 * min(1, monthlyAvailable / requestedAmount))
```

- Request fits (`requested ≤ monthlyAvailable`) → full 15.
- `monthlyAvailable > 0` but request exceeds it → proportional, PHP `round()`
  half-up (e.g. `round(15 * 5500/10000) = round(8.25) = 8`).
- `monthlyAvailable == 0` → 0.
- Monthly limit unassessed (`monthlyAvailable` null) → full 15, and **no risk
  floor below** (unassessed is not a risk signal; controllers block it).

`monthlyAvailable = max(0, monthlyCapacity − monthlyUsed)` where Monthly Used
is the sum of principal approved this calendar month (Asia/Manila). DTI keeps
using `monthlyPaymentsActive()` (EMI sum) — that definition is unchanged.

## Risk bands and floors

Bands: `risk_score ≥ 70` Low, `≥ 50` Medium, else High. After the band, the
existing overrides (DTI, request > 2× recommended, zero available, unassessed
credit limit) apply, then the monthly-limit floor:

| Condition                                     | Effect                                   |
|-----------------------------------------------|------------------------------------------|
| `monthlyAvailable == 0`                       | forced High (pre-existing hard override, kept) |
| `requested > monthlyAvailable > 0` and `requested ≤ 2 × monthlyAvailable` | at least Medium |
| `requested > 2 × monthlyAvailable`            | High |
| `requested ≤ monthlyAvailable`, or limit unassessed | no rule                         |

Floors only raise a level, never lower it, and add exactly one reason:

- Medium: `Requested amount (₱X) is above this month's available credit (₱Y); risk raised to Medium.`
- High: `Requested amount (₱X) is more than twice this month's available credit (₱Y); risk raised to High.`

The 2× boundary is inclusive for Medium (`monthly_limit_medium_multiple`,
default 2): exactly 2× available → Medium; 2× + ₱1 → High. When the level is
already High the High floor adds no reason.

## Examples (income 50,000 / obligations 2,000 / savings 10,000 / no payment
history / 12-month term / monthly limit 15,500; Monthly Used adjusted per row)

| Requested | Available | `monthly_limit_score` | `risk_score` | Risk level | Recommended | Notes |
|----------:|----------:|----------------------:|-------------:|------------|------------:|-------|
| 5,000     | 15,500    | 15                    | 83           | Low        | 5,000       | no monthly-limit reason |
| 10,000    | 5,500     | 8                     | 76           | Medium     | 5,500       | Low band; floor raises to Medium + cap reason |
| 5,000     | 500       | 2                     | 70           | High       | 500         | Low band; floor raises to High |
| 5,500     | 0         | 0                     | 68           | High       | 0           | zero-available hard override; no 2×-escalation reason |

Score-band vs. risk level: the floor can make the level stricter than the band
(rows 2–4 above: 76 and 70 are Low-band scores raised by the floor, 68 is a
Medium-band score forced High at zero available). That divergence is by
design; the band is the starting point, the floor is a minimum.

Pending applications approved before this change keep their stored
`risk_score`/`risk_level`; re-evaluation happens only on the next
`evaluate()` call. Backfilling stored scores is a separate task.

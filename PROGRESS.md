# Monthly credit limit policy — progress

Branch: credit-limit-wip

- A. Request-time monthly-limit rejection removed (null-only block) — DONE (UserController, AdminController; MonthlyCreditLimitTest updated)
- B. Exact cap (min(existing, Monthly Available), no rounding), forced High at 0, eligible=false at 0 — DONE (LoanEvaluationService)
- C. Approve gate stays authoritative (override only > monthly available / > total available) — DONE (unchanged, AdminController::approve; covered by tests)
- D. Modal override UI — DONE (application-modal.blade.php: box only when amount > monthly available OR > total available; prefill recommended, fallback requested when 0; oninput toggles; permission-gated)
- E. MultipleOfThousand rule deleted; roundDown1000/minAndRound removed; form min ₱1 — DONE
- F. Monthly Used/Available, DTI risk, total credit limit gate — DONE (unchanged)
- G. Active Members modal — DONE (CoopMember::maxForTwelveMonths(); footer: "Credit Limit" row removed, "Max for 12 months" added; monthly_max_12 in activeMembersData; N/A score note in Recent Loan Applications)
- Tests: MonthlyCreditLimitTest 43/43, EvaluationEngineTest+LoanEvaluationTest 21/21, gate/encode/modal suites 54/54, admin/member suites 48/48
- Full suite: PENDING (final run)
- Baseline before change: 638 tests / 1934 assertions / 15 skipped

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
- Full suite: 638 tests / 1936 assertions / 15 skipped — 0 failures (baseline was 638/1934/15)
- Baseline before change: 638 tests / 1934 assertions / 15 skipped

## Override-box bug fix (uncommitted, on top of dd22b43)

- Root cause: modal was built from sparse window.apps / $formattedApp JSON (no monthly_available / available_credit_total / recommended_amount; amount as formatted "₱10,000.00") → override logic never saw the values it compares against.
- Fix: renderApplicationModal self-fetches GET /admin/applications/{id}/data when monthly_available is missing; one pure window.shouldShowOverride(amount, monthlyAvailable, totalAvailable, canOverride) drives both build-time and oninput decisions; parser strips ₱/commas; Confirm disabled + "ask a manager" notice when no OVERRIDE_CREDIT_LIMIT.
- Footer Recent Loan Applications N/A: apps JSON never emitted app_id and used ai_score for the "Rule-Based Score" column → added app_id key, score = rule_score ?? ai_score.
- Tests: tests/Browser/override-logic.test.cjs (node, 13 truth-table cases, no browser/db); MonthlyCreditLimitTest additions — approve_override_matrix_at_zero_monthly_available (+portal-less variant), approve_amount_at_or_below_available_needs_no_override, active_members_page_lists_max_for_12_months, modal_override_wiring_is_present_in_blade, same-source maxForTwelveMonths assertion in application_data test.
- Playwright browser spec: NOT added — login fixture user blocked per user instruction (no new fixture user, no scratch-DB changes without approval); node logic test covers the decision table instead.
- Scratch DB leftover rows from the earlier deleted fixture (mirror APP-9902, member SDCC-OVERRIDE-TEST-0001, users override.manager@ / override.officer@sdcc.coop, loan apps 5-6) — ask before deleting (CLAUDE.md rule 4).


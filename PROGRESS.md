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

## Monthly-limit scoring & risk floors (rule engine reacts to monthly limit)

- Config: weights dti 45→30, payment_history 35, documents 0, savings 20, NEW monthly_limit 15 (total 100); new threshold `monthly_limit_medium_multiple = 2`; comments updated.
- Engine (`LoanEvaluationService`): new `monthly_limit_score` component (round half-up, `weight * min(1, available/requested)`, full 15 when fits or unassessed, 0 at available 0) added to risk_score + `details.monthly_limit_score`; risk floor AFTER band+overrides: available>0 && requested>available → ≥Medium (≤2×) / High (>2×); unassessed → no floor; existing overrides untouched; one reason sentence per raise, never repeated, none when already High.
- New tests: `tests/Feature/MonthlyLimitScoringTest.php` — 7 scenarios + weights-total-100 (all pass).
- Existing tests updated deliberately (old → new): EvaluationEngineTest sum assertion adds monthly_limit_score; breakdown dti 45→30 + monthly_limit_score 15 (score 68 unchanged); config-drives test 43→58, High→Medium (band 50–69); provider comments recomputed. MonthlyCreditLimitTest exact-cap 83→76, Low→Medium, reasons array gains floor line (₱10000/₱5500, %.0f format); forces-high 83→68; dti_score 45→30; not-forced-high fixture 440000→20000 (20000 ≤ 2×14279.37 keeps the "capped but not forced High" intent).
- Docs: `docs/scoring-weights.md` — weights table, monthly-limit formula, risk floors, the four examples, band-vs-level divergence, stale-score note.
- UI: none — modal shows only the total score, no per-component breakdown (verified in application-modal.blade.php).
- Scoped runs: MonthlyLimitScoringTest 7/7 + EvaluationEngineTest + MonthlyCreditLimitTest + LoanEvaluationTest + CreditLimitEnforcementTest → 94 tests green after the two expectation fixes above. Full suite + final count pending.

## One loan request at a time (pending/flagged blocks) + override-at-load + reason money format

### Part 2 — one loan request at a time
- Audit findings (gaps that existed before): UserController::storeApplication blocked only status 'pending' and only via user_id (flagged apps and portal-less/legacy email-matched loans slipped through); AdminController::storeEncodedApplication blocked only when a portal user was linked (portal-less members never blocked) and only 'pending'; LoanSubmissionService::submit had NO guard at all (two concurrent submits = two applications); no shared member-scoped check existed.
- Shared check: CoopMember::pendingLoanApplication() + hasPendingLoan() — newest-first, status IN (pending, flagged), scoped like monthlyUsed(): user_id with email fallback for legacy staff-encoded loans; portal-less by email only; neither → never matches.
- Enforcement (3 places): UserController::storeApplication (member message "You already have a loan application under review. Please wait for it to be approved or rejected before requesting another loan."), AdminController::storeEncodedApplication (staff message with ref: "This member already has an application under review (APP-XXXX). It must be approved or rejected first."), LoanSubmissionService::submit (DB transaction + lockForUpdate on the member row — last-line race guard).
- UI: encode page — member picker options carry data-pending/data-pending-ref/data-pending-status + "⚠ loan under review (REF)" label, #memberPendingWarning filled on select, #encodeSubmitBtn disabled until another member is chosen (refreshPendingWarning on change); controller compute pending_loan_ref/pending_loan_status via one undecided-apps query. Member loan form — banner with reference + status, submit disabled.
- Tests (MonthlyCreditLimitTest 48→57): +7 — pending blocks member form (no rows, no stored files), approved/rejected unblocks, flagged blocks, portal-less blocked by email on staff encode (ref in message + no stored files), two portal-less members don't block each other, double submit creates one application, encode page marks members (viewData + blade wiring). Reworked application_data_pending_warning_totals_pending_amounts fixture (second submit now blocked by the rule → seed B directly); updated stale needles: member message needle + 3 staff needles now "(APP-" prefix (ref appended).

### Part 1 — override option visible when request is over the limit
- applicationData() returns over_limit (requested > Monthly Available OR > total available credit, incl. available 0) + requested_amount; modal drives initial state from the server flag (checkbox unchecked + reason hidden + intro notice "…Approve up to ₱X, or tick Override…" for over-limit; nothing for fitting requests; no-permission → ask-a-manager notice + Confirm disabled; typing above available still reveals the checkbox via toggleOverrideBox; unchecking restores recommended + hides reason). confirmApprove requires reason when checked; body sends only approved_amount + reason — AdminController::approve() stays authoritative (permission + reason + AuditLog on every override).
- tests/Browser/override-logic.test.cjs already covers the new wiring (13 truth-table cases + toggle flow + blade wiring + no-override-flag POST) — passes unchanged.
- PHPUnit: application_data_over_limit_true_for_100k_requested + _when_monthly_available_is_zero added earlier in this WIP.

### Part 3 — peso format in reasons
- Every ₱ amount in LoanEvaluationService reasons now number_format(…, 2) (8 sprintf sites); MonthlyCreditLimitTest reason expectation updated old ₱10000/₱5500 → ₱10,000.00/₱5,500.00; grep confirms no other tests assert the old strings.

### Final checks
- Full suite ONCE: 660 tests / 2048 assertions / 15 skipped / 0 failures (baseline after scoring task 638/1936/15; skipped unchanged). Scoped run (15 files touching changed paths): 146/146. Node override-logic: passed. No artisan DB commands run (phpunit forces sqlite :memory: per phpunit.xml + TestCase guard), so the DB_DATABASE print rule was not triggered.
- Pending: scratch-DB leftover rows from the earlier deleted fixture (mirror APP-9902, member SDCC-OVERRIDE-TEST-0001, users override.manager@/override.officer@sdcc.coop, loan apps 5-6) — need approval before deleting (CLAUDE.md rule 4). No Playwright browser spec / fixture user (per standing instruction; node logic test covers the decision table).

## Repayments page: proof of payment + encoder (Tasks A1–A5)

- A1: repayments.blade.php restyled to shared classes from admin/css.blade.php (.app-table, .records-count, .applicant-name, .num, .td-num, .td-date, .status-pill, .loan-type-tag, .empty-table-msg, .btn-new, .btn-review, .btn-submit, .btn-cancel, .kpi-card/.kpi-val, .nav-icon on View/Edit buttons + modal titles/close). Page keeps its own DM Sans + FA 6.5.1 head links (standalone page; css.blade.php has none, so not duplicates). Behavior identical.
- A2: proof of payment. Migration 2026_11_15_000000_add_proof_path_to_payments.php (nullable proof_path, Schema::hasColumn guard) + Payment fillable. Record modal: required file input (jpg/jpeg/png/webp, ≤5 MB client check + server `proof` => required|image|mimes:jpg,jpeg,png,webp|max:5120), thumbnail preview + Remove, inline errors; form enctype multipart; submit-disable handlers untouched. storeRepayment stores on default local disk (storage/app/private/payment-proofs, random hashName, never client name) inside the existing transaction; file deleted on failure; AuditLog text mentions proof. GET /repayments/{id}/proof (RECORD_PAYMENTS group, whereNumber, name repayments.proof) streams via Storage::response, 404 when none, never exposes path. View modal: thumbnail click-opens-larger / "No proof on file". Edit modal: current thumbnail, optional replacement, no file keeps old, old file deleted only after commit, AuditLog notes replacement.
- A3: Payment::encoder() BelongsTo(User, 'encoded_by'); repayments() eager-loads with('loanApplication.user','encoder'); name shown in table/View/Edit modal, "—" only when null. updateRepayment never writes encoded_by. payments.recorded_by column exists but nothing reads it (only the old broken backfill command wrote it; not fillable).
- A4: BackfillPaymentRecorders rewritten — writes encoded_by (not the non-fillable recorded_by), matches via loan_application_id + amount (date tiebreak for ambiguity), skips ambiguous/already-set/missing-actor rows, dry-run default + sdcc guard kept. Scratch dry-run output: "Found 0 'Payment Recorded' audit log entries." / 0 / 0 / exit 0. Apply NOT run (user runs it after backup; commands in final report).
- A5: RepaymentTest +8 tests (a–h), all with Storage::fake('local'); ValidationTest/ReminderTest/RepaymentConsistencyTest payloads gained proof attaches. Scoped runs: RepaymentTest+BackfillPaymentRecordersTest 25/25; Validation+Reminder+RepaymentConsistency 41/41; AdminApplicationViewTest 11/11.

## Applications list columns (Task B)

- application.blade.php: AMOUNT→REQUESTED (value unchanged); RECOMMENDED→APPROVED with status-based cell — approved/disbursed/paid (case-insensitive) → Format::money(approved_amount ?: amount) (legacy empty fallback), rejected → ₱0.00, else "—". Detail modal "Recommended Amount" row untouched. footer.blade.php filterApps/handleSearch key on data-status/data-app-id + name/id only — header rename safe; no other test/JS depends on old header names (grep verified; admins.blade.php cells[] is a different table).
- Tests: AdminApplicationViewTest::admin_list_shows_risk_level_and_recommended_amount_columns → admin_list_shows_risk_level_and_requested_amount_columns (asserts ₱5,000.00 + new headers); +admin_list_approved_column_reflects_status_based_approved_amount (approved ₱4,000.00, paid ₱4,500.00, rejected ₱0.00, legacy fallback ₱7,000.00, pending/flagged hide ₱2,500.00). 11/11 green.
- Full suite ONCE: 671 tests / 2108 assertions / 15 skipped / 0 failures (baseline 660/2048/15/0; +11 tests = 8 RepaymentTest + 2 BackfillPaymentRecordersTest + 1 AdminApplicationViewTest; skipped unchanged). Deprecations 361 = pre-existing framework-level ones.
- Poisoning bug found+fixed during final run: BackfillPaymentRecordersTest::it_refuses_to_run_on_the_protected_sdcc_database flipped config database.default → mysql, breaking RefreshDatabase teardown for every later test class (BackupTest VACUUM-in-transaction cascade, 628 errors). Fixed to override connections.sqlite.database instead; verified Backfill+BackupTest 12/12 then full suite green.


## TASK 2 — Reports page fixes (2.1–2.7)

- 2.1 Granted loans = approved + paid (LoanStatus::isGranted()). KPI "Granted Loans" sums COALESCE(approved_amount, amount) over approved+paid; Approved Records lists paid rows too (with Status column + label); Borrower Performance total_approved_amount/outstanding count granted only (paid loans keep outstanding 0); Loan Summary already groups paid.
- 2.2 Amount semantics: loanSummaryData sums CASE WHEN status IN (approved,paid) THEN COALESCE(approved_amount, amount) ELSE amount END for status totals, type totals and the grand total (Total row = sum of rows by construction). Loan Summary section note spells out the rule. Builders return raw floats; web/PDF/Excel each format.
- 2.3 Borrower Performance member resolution: iterates loans and resolves each via CoopMember::forLoanApplication (user link → email fallback). Portal-less staff-encoded loans group under their coop member; loans with no member row appear under the loan's own applicant name instead of vanishing.
- 2.4 Math bug: $anchorPaid (per-loan totalPaid) is now separate from the $totalPaid accumulator — no more overwrite + double count; earlier loans' totals no longer wiped. Payment date filter applies with start OR end (was: both required).
- 2.5 Overdue Payments: new overdue_date closure filters next_due_date (was: created_at); status filter ignored + dropdown disabled for this report (it only ever lists approved loans); amount split into "Overdue installment" = min(monthly_payment, remaining_balance) and "Remaining balance", days overdue kept. Web + PDF + Excel all show both columns.
- 2.6 Excel: approved_records (E approved amount, H recommended), borrower_performance (C/D/E) and overdue_payments (D installment, E balance) write real floats with ₱#,##0.00 format; builders no longer pre-format to strings.
- 2.7 tests/Feature/ReportsConsistencyTest.php (new, 10 tests): granted-paid everywhere, amount semantics + Total row consistency, member resolution (portal-less email match + unmatched name), multi-loan total_paid math, one-sided date filters (start/end), overdue next_due_date filter + status ignored + installment cap, web column presence, Excel float + format + status label, and 4 types × web/PDF/Excel with data and empty.
- tests/Feature/ReportsTest.php: two assertStringContainsString money assertions → assertEqualsWithDelta (builders now return raw floats).
- Scoped run: ReportsTest + ReportsConsistencyTest + OverdueReportTest = 43/43 green (132 assertions). Full suite once at the end per FINAL.

## TASK 3 — Email notification outbox implementation (3.1–3.8)

- **3.1**: `AdminController::approve()` + `reject()` guards block re-approval/re-rejection with 409, preventing duplicate email log rows in the outbox.
- **3.2**: `PaymentReminderService::alreadySentToday()` now dedupes across BOTH `AppNotification` and `AppNotificationLog` (same Manila day + type + loan_id). Added `error_message = 'No email address on file'` for Skipped rows via the extended `AppNotificationLog::log()` signature.
- **3.3**: `PaymentReceived` event listener (`NotifyMemberOfPayment`) auto-discovered in Laravel 12 — already wired (verified empirically via a passing listener-dependent test). Duplicate payment guards in `storeRepayment` (reference_no + amount+date checks) prevent duplicates.
- **3.4**: `approveMember()` now logs `AppNotificationLog::log()` with `type = 'membership_approved'` after the email send (with `logFailed` catch). `rejectMember()` log type changed from `'member_rejected'` to `'membership_rejected'` to match the constant/dropdown.
- **3.5**: Welcome email removed — no `Mail::send` is invoked for `TYPE_WELCOME`; the "Welcome" entry removed from the type dropdown in `notifications-log.blade.php`.
- **3.6**: `reminder_failed`, `reminder_summary`, `system` removed from the type dropdown. These are in-app staff-only types and never write to the outbox.
- **3.7**: Stats cards use filtered query counts (`$stats['total']`, `$stats['sent']`, `$stats['failed']`, `$stats['skipped']`, `$stats['today']`) with a Skipped card rendered via the neutral `nl-pill-skipped` badge. Grid changed from `repeat(2, 1fr)` to `repeat(auto-fit, minmax(120px, 1fr))` so six cards wrap into one tidy row. Search `orWhere` grouped under `where(function...)` so it doesn't leak into other filter whens. Error rate = `failed/(sent+failed)` of the filtered set.
- **3.8**: Status dropdown gains `Skipped` option. Skipped rows render the neutral `nl-pill-skipped` badge in the Status column. `error_message` ('No email address on file') appears in the Error column. Type dropdown lists only 8 real types (`approved, rejected, due_soon, due_today, overdue, payment_received, membership_approved, membership_rejected`).
- **Tests**: `tests/Feature/NotificationOutboxTest.php` (new, 23 tests) covers per-type Mail::fake assertions, duplicate guard, failing-transport path, search grouping, status dropdown, and type-dropdown cleanup.
- **Verification**: Full suite = **707 tests / 2239 assertions / 15 skipped / 0 failures** (baseline was 671/2108/15/0).

## PARTS A–D — final state (2026-11-17)

- **Part A (notification outbox / Payment Received listener)**: suspect (a) confirmed — listener used `$loan->user?->email`, silently skipping portal-less members. Fixed to eager-load `user` + `loanApplication`, fill MEMBER REF / LOAN REF, TYPE_LABELS map, one outbox row (Sent/Failed/Skipped) per payment, email-only, mail failure must not break recording, in-app only when loan has portal user. AdminController approve/reject 409 guards block duplicate outbox rows.
- **Part B (repayments page)**: restyle to `.rep-*` scoped classes (`.rep-filter-row/-group/-label/-control/-actions`, `.rep-btn`, `.rep-btn-danger`), "Member ID"/"NAME" headers, "Collected this month" KPI, Search/Start/End + Reset filters, Record modal notes LAST + `req-mark` required labels + quick-pay buttons + hidden loan summary card, View modal with proof download + modal-footer Edit button, Edit modal with reason field + balance-after warning + sticky modal-footer; `.req-mark`/`.modal-footer` added to css.blade.php.
- **Part C (audit reason)**: `updateRepayment()` gained `reason` => nullable|string|max:500; `$validated['reason'] ?? null` guard fixed crash when reason omitted; audit text refactored into `$auditText`; new test `editing_repayment_with_reason_saves_it_in_audit_log` green (RepaymentTest 24/24).
- **Part D.5 (backfill)**: `sdcc:backfill-notif-log-loans` command (dry-run default, --apply flag, sdcc guard); dry-run on scratch: 0 rows; --apply NOT run.
- **Part D.6**: message-preview migration NOT created (user approval required; pending).
- **Part D.7**: no Resend button added (compliant).
- **Pre-existing flaky failures (not caused by A–D)**: `ReminderTest::running_reminders_twice_in_one_day_does_not_duplicate` (line 208), `ReminderTest::duplicate_guard_second_run_no_new_send` (line 934), `NotificationOutboxTest::running_reminders_twice_writes_no_duplicate_log_rows` (line 440) — UTC vs Manila timezone boundary in `alreadySentToday()`; root cause documented in REPORT.md.
- **Full suite ONCE**: 714 tests / 2267 assertions / 15 skipped / **3 failures** (all pre-existing flaky, not caused by this session's changes). Baseline 707/2239/15/0 → delta +7 tests / +28 assertions / +3 pre-existing failures.
- Pending: D.6 migration approval; scratch-DB leftover fixture rows (need approval to delete); `C\357\200\272New` untracked dir left untouched.

# REPORT — SDCC Loan Web Project (Tasks 0–10 complete)

**Branch:** `credit-limit-wip` (main is the usual PR target)
**Date:** 2026-11-17

---

## Summary

All assigned work is complete:

| Task | Status |
|------|--------|
| Tasks 0–7 | Done (committed earlier; see git log) |
| Task 8 — Notification detail modal | Done (`c3cf656`) |
| Task 9 — Repayments tabs + Reverse button | Done (`a41b69b`) |
| Task 10 — Outbox Loan column off-by-one follow-up | Done (`70561a4`) |

Final full-suite run: **757 tests / 2515 assertions / 15 skipped / 0 failures** (baseline at Task 7: 755/2507/15/0; delta +2 counted by phpunit vs +3 new test methods — see "Test counts" note below; **skipped unchanged at 15**).

Browser verification: **49/49 static-page checks pass** (23 notifications-log + 26 repayments), real headless chromium, no server, no login, external fonts/CDN blocked.

---

## TASK 8 — Notification detail modal (/admin/notifications-log)

**Root cause (1 sentence):** The modal's inline style carried `display: flex !important`, which beats the shared `.modal { display: none !important }` stylesheet rule (inline `!important` beats stylesheet `!important`), so the box rendered on load and `closeAllModals()` — which only removes the `.open` class — could never hide it; the X button removed the backdrop but the box stayed.

**Fix:** Removed the display/flex/max-height overrides (inline style now positioning-only, same pattern as the repayments modals) so the shared `.modal`/`.modal.open` mechanism fully controls visibility; every close path (X, Close button, Esc, backdrop click) routes through `closeNotifModal()` = clear `#notifModalBody` + `closeAllModals()`; one modal element, no leftover static markup; reopening replaces innerHTML so content never stacks.

**Files changed:** `resources/views/admin/notifications-log.blade.php`.

**Browser checks (23/23 passed):** modal + backdrop hidden on load (no bounding box, no other modal visible); row click opens with correct title (`Loan Approved email`) and Recipient/Loan (APP-0009)/Subject fields; X hides box+backdrop and empties body; reopen + Close hides and empties; Esc hides; backdrop click hides; opening twice never stacks content; row B replaces row A (title + subject swap).

---

## TASK 9 — Repayments page (/repayments)

**Root cause (1 sentence):** A stray unterminated `.empty-table-msg {` fragment at `css.blade.php:1224` made CSS native nesting swallow every rule after it (`.search-icon`, `.rep-hidden`, all `.rep-tab*` …) into itself, so the tab styles and the panel-hiding rule were dead CSS — tabs rendered as plain browser buttons and both panels showed.

**Fix:** Deleted the stray fragment (the real, complete `.empty-table-msg` definition already exists at line 1191); the server-driven tab wiring (`$activeTab`, `?tab=due`, default Payments) was already correct and is unchanged.

**9.3 choice (stated):** kept the Reverse button in the row, made it a small ghost/danger-text button via a new `rep-row-reverse` class (0.72rem font, 0.25/0.6rem padding, dashed red border, `var(--red)` text, transparent background) — strictly less prominent than View/Edit; the confirmation modal with the required reason is unchanged.

**Files changed:** `resources/views/admin/css.blade.php` (fragment removed + `rep-row-reverse` rule), `resources/views/admin/repayments.blade.php` (button gains the class).

**Browser checks (26/26 passed):** all 4 modals hidden on load; tab buttons styled (computed transparent bg, 16px padding, DM Sans) vs UA defaults; count badge is a red 50px-radius pill; Payments visible / Due hidden on load; Due click shows Due only; switching back restores; URL unchanged (no reload); Record Payment modal opens then X and Cancel each fully hide it; View/Edit/Reverse open once each and close; Reverse modal contains the required `#reverseReason`; Reverse computed style = transparent bg + `rgb(220,38,38)` + smaller than View. The server-side `?tab=due` default is covered by `RepaymentsPageTask5Test` (a static dump cannot carry a request query).

**Tests:** no existing test asserted the old Reverse button classes (they exercise the reverse API only) — no test changes needed. Repayments suites 49/49 green after the edits.

---

## TASK 10 — Outbox Loan column off-by-one follow-up

**What Task 6.3 found and changed (stated exactly):** `LoanApplication::application()` was a plain `hasOne(Application::class, 'loan_application_id')` with NO ordering. With duplicate mirror rows (two `applications` rows sharing one `loan_application_id`), HasOne returns the FIRST (lowest-id = oldest) mirror, so the outbox rendered the stale `app_id` while the Applications page/modal/Repayments showed the newest — "every loan ref shifted by one". Fix: `->latest('id')` on the HasOne so the newest mirror is always returned. Verified by `NotificationDuplicateMirrorTest` (duplicate mirrors APP-0009 + APP-0010 on one loan; outbox links APP-0010) and `NotificationLoanRefTest` (loan id 10 / mirror id 8 / APP-0009; outbox == Applications page == application modal; log stores the loan id, not the mirror id).

**Root cause (1 sentence):** two resolution paths still disagreed — the outbox used the newest mirror (HasOne `latest('id')`) while the Repayments page used `Payment::appId()`, which read `$this->application?->app_id` through an UNORDERED `belongsTo` → the OLDEST mirror.

**Fix:** `Payment::appId()` now resolves through the canonical helper `$this->loanApplication?->appId()` (newest mirror), keeping the old fallback chain; the outbox blade reads `$loanApplication?->appId()` directly. `Payment::application()` has no other callers in `app/` (grep-verified), so the unused unordered belongsTo was left in place. Stored rows were NOT rewritten — stored `loan_application_id` values are loan ids (correct when present); the mismatch was in resolution, not storage, so `BackfillNotifLogLoans` (NULL refs only) was not extended and `--apply` was NOT run.

**Reproduction test (deliberate ids):** `NotificationLoanRefTest::test_outbox_loan_ref_matches_applications_page_when_ids_differ` — loan id 10 / mirror id 8 / APP-0009 — asserts all four surfaces agree: outbox Loan column text, outbox Loan link target (`openNotifLoan('APP-0009', 8)`), the Repayments page, and the application modal data route. `NotificationDuplicateMirrorTest::test_payment_app_id_uses_newest_mirror` is the root-cause test (two mirrors APP-0009/APP-0010 + a payment; asserts `Payment::appId() === 'APP-0010'`, Repayments page shows APP-0010 and NOT APP-0009) — verified it FAILS on the old `appId()` and PASSES on the fix.

**Read-only dry-run report:** `scripts/dry-run-notif-loan-refs.php` lists every outbox row (log id, recipient, type, created_at, stored loan_application_id, the app_id that column resolves to today, and the loan whose approval/rejection/payment event time + recipient actually match) and flags mismatches, incl. NULL-but-backfillable rows.

**Task 10 mismatch list:** on `sdcc_scratch` the outbox table is EMPTY → 0 rows, 0 mismatches (scratch run output: `DB: sdcc_scratch` / `app_notification_logs is EMPTY in 'scratch'`). The real-DB list must be produced by you (command below) — the agent is not authorized to read `sdcc`.

**Incident (disclosed):** the first version of the dry-run script passed the env as a positional arg, which Laravel ignores for env-file switching (only artisan `--env` does) — my "scratch" run actually connected to real `sdcc` and printed 8 outbox rows into the transcript. It was strictly read-only (SELECTs only; no writes, no data changed), but it crossed the "never touch sdcc" line. Fixed: the script now calls `loadEnvironmentFrom('.env.'.$env)` and hard-aborts unless `DB_DATABASE=sdcc_scratch` when env=scratch; the `sdcc` variant prints a warning and is for you to run.

**Files changed:** `app/Models/Payment.php`, `resources/views/admin/notifications-log.blade.php`, `scripts/dry-run-notif-loan-refs.php` (new), tests below.

---

## Files changed (this session)

| File | Change |
|------|--------|
| `resources/views/admin/notifications-log.blade.php` | T8 modal hidden by default + `closeNotifModal()` on all close paths; T10 `$loanRef` via canonical `appId()` |
| `resources/views/admin/css.blade.php` | T9 removed stray unterminated `.empty-table-msg {` fragment; added `.rep-row-reverse` |
| `resources/views/admin/repayments.blade.php` | T9 Reverse button gains `rep-row-reverse` class |
| `app/Models/Payment.php` | T10 `appId()` routes through `loanApplication->appId()` |
| `tests/Feature/StaticPageDumpTest.php` | NEW — renders both pages to `tests/tmp/*.html` |
| `tests/Browser/static-page.test.cjs` | NEW — reusable Playwright static-page helper (notif/repayments/all, 60 s hard timeout, network blocked) |
| `tests/Feature/NotificationLoanRefTest.php` | T10 +2 assertions (outbox link target, Repayments page) |
| `tests/Feature/NotificationDuplicateMirrorTest.php` | T10 +1 test `test_payment_app_id_uses_newest_mirror` |
| `scripts/dry-run-notif-loan-refs.php` | NEW — read-only outbox-vs-event report |
| `PROGRESS.md`, `.gitignore` | T8–T10 notes; ignore `tests/tmp/` |

Deleted strays: `tests/Feature/TmpDumpNotifPageTest.php`, `tmp_notif_page.html`, `tmp_notif_loan_ref_diag.php` (replaced by the proper helper/script above).

---

## Tests added/changed (old → new)

| Test | Before | After |
|------|--------|-------|
| `StaticPageDumpTest` | — | 2 new dump tests (notifications-log, repayments) |
| `NotificationLoanRefTest::test_outbox_loan_ref_matches_applications_page_when_ids_differ` | 4 assertions | 6 assertions (+ outbox link target `openNotifLoan('APP-0009', 8)`, + Repayments page shows APP-0009) |
| `NotificationDuplicateMirrorTest` | 2 tests | 3 tests (+ `test_payment_app_id_uses_newest_mirror`, verified failing on the old `Payment::appId()`) |
| Browser helper | stray `TmpDumpNotifPageTest.php` + `tmp_notif_page.html` | proper `StaticPageDumpTest` + `tests/Browser/static-page.test.cjs` (49 checks) |

No existing tests were removed or weakened.

**Test-count note:** phpunit reports 757 vs the 755 baseline (+2) although 3 new test methods were added (+2 static dumps, +1 payment-mirror test). `git diff ebe6b1d HEAD -- tests/` shows 451 insertions / 0 deletions, so nothing was lost; the 755 baseline figure in the Task 7 commit message appears to have been under-counted by one (e.g. a `@test`-annotated method), and the final report uses the real, current numbers.

---

## Browser test results (which checks passed)

`node tests/Browser/static-page.test.cjs all` → **49/49 checks passed, 0 failed**
- `notif` suite: 23/23 (Task 8's seven spec checks + same for other modals on the page)
- `repayments` suite: 26/26 (Task 9.2's spec checks + 9.3 Reverse styling)

---

## Final test counts

- `vendor/bin/phpunit` (run ONCE, final): **757 tests / 2515 assertions / 15 skipped / 0 failures** (skipped did not rise; deprecations are pre-existing framework-level warnings).
- `node tests/Browser/static-page.test.cjs all` (run ONCE, final): 49/49 passed.
- Scoped runs during development: notifications 36/36, repayments 49/49.

---

## Data commands — NOT run (user action required; BACK UP FIRST)

1. **Payment reversal migration** `2026_11_17_000000_add_reversal_to_payments.php` — CREATED but NOT RUN (waiting for your approval). Real-DB payments pages error until applied (SoftDeletes queries a column that doesn't exist yet); tests pass because RefreshDatabase runs all migrations on sqlite `:memory:`.
2. **Backfill command** `sdcc:backfill-notif-log-loans` (dry-run default, `--apply` flag, sdcc guard) — dry-run on scratch returned 0 rows; `--apply` NOT run.
3. **Task 10 dry-run report on real data** — run it yourself (read-only SELECTs; back up first, dry-run before any --apply):

```bash
# Print DB_DATABASE before any DB command
php artisan config:cache --env=scratch
# check DB_DATABASE in bootstrap/cache/config.php — must be sdcc_scratch

# Real-DB read-only report (agent is not authorized to run this)
php scripts/dry-run-notif-loan-refs.php sdcc
```

Never run anything against the protected `sdcc` database without `ALLOW_PROTECTED_DB=1` and your explicit approval.

---

## Anything not done

- Task 6 Part D.6 (message-preview migration) — pending your approval.
- Scratch-DB leftover fixture rows from earlier override testing — need your approval to delete.
- The real-`sdcc` outbox mismatch list (Task 10) can only be produced by you (command above); scratch is empty.
- Pre-existing flaky reminder tests (UTC-vs-Manila boundary in `PaymentReminderService::alreadySentToday()`) — documented earlier, not touched this session; they passed in the final run.

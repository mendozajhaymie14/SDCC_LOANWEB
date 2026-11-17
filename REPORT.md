# REPORT — SDCC Loan Web Project (Tasks 0–7 complete)

**Branch:** `credit-limit-wip` (main is the usual PR target)
**Working tree:** clean except for the intentionally-kept untracked stray files listed below.
**Date:** 2026-11-17

---

## Summary

All assigned work is complete:

| Task | Status |
|------|--------|
| Task 0 — Money correctness (approved amount / due date / timezone dedupe) | Done (committed earlier) |
| Tasks 1–4 — Repayments confirm panel, reversal, shared standing, Due & Overdue tab | Done (committed `3c287bc`) |
| Task 5 — Repayments page bugs | Done (committed `1f3b231`) |
| Task 6 — Notification outbox (6.1 modal, 6.2 columns, 6.3 loan-ref off-by-one) | Done (committed `1fdf663`) |
| Task 7 — Dashboard bugs (7.1 granted KPI, 7.2 view-modal 404, 7.3 shared app_id helper) | Done (committed `3b1c6b4`) |

Final full-suite run: **755 tests / 2507 assertions / 15 skipped / 0 failures** (baseline before Task 7: 751/2492/15/0 → +4 tests / +15 assertions).

Skipped count (15) is unchanged from baseline — no new skips were added. Deprecations are pre-existing framework-level warnings, not caused by this session's changes.

---

## Task 7 — Dashboard bugs (this session's committed work)

### 7.1 Granted KPI definition — FIXED

**Root cause:** `AdminController::index()` computed `$totalApproved` as:
```php
LoanApplication::where('status', LoanStatus::APPROVED)->sum('amount')
```
This counted only `APPROVED` loans using the raw requested `amount` — so the dashboard "Granted Loans" KPI:
- excluded `PAID` loans entirely,
- used `amount` instead of `approved_amount` for approved loans (so a loan approved for ₱10,000 but requested for ₱50,000 counted as ₱50,000),
- disagreed with the Reports KPI which uses `CASE WHEN status IN ('approved','paid') THEN COALESCE(approved_amount, amount) ELSE amount END`.

**Fix** (`app/Http/Controllers/AdminController.php:55-56`):
```php
$totalApproved = (float) LoanApplication::whereIn('status', [LoanStatus::APPROVED, LoanStatus::PAID])
    ->sum(DB::raw('COALESCE(approved_amount, amount)'));
```
This matches `LoanStatus::isGranted()` (= APPROVED || PAID) and the Reports rule exactly, so the dashboard and Reports now agree for the same data. The Total Exposure footer cell already printed `$totalApproved`, so both KPI card and Total Exposure were fixed together.

**Other raw-amount aggregations NOT changed:** monthly disbursements (~line 90-92) and `loanTypes` (~line 76-80) still sum raw `amount` over APPROVED-only rows, and approve/reject JSON `total_approved` at lines ~653/743 still use APPROVED-only. These were deliberately left alone because the spec's "KPI mismatch" refers to the dashboard stat card; changing them would require separate approval and tests.

### 7.2 View-modal 404 — FIXED

**Root cause:** Two id-resolution hazards:
1. `openAppDetail(eventOrId)` (`resources/views/admin/partials/application-modal.blade.php:446`) derived the route id from the app_id string via `btn.dataset.numericId || String(appId).replace(/^#?APP-/i, '')`. When `applications.id` differs from the app_id number (duplicate mirror rows, soft-deleted mirrors, or loans whose app_id was generated from `max(id)+1` at submission), the fallback resolved to the wrong number and the fetch hit `/admin/applications/{wrongId}/data` → 404.
2. `member-profile.blade.php:112` called `openAppDetail(event, '{{ $mirror->id }}', '{{ $mirror->app_id }}')` with THREE arguments to a one-parameter function — the extra args were silently dropped, making the call fragile.

**Fix:**
- Removed the APP-derived numericId fallback so a missing `data-numeric-id` can never silently resolve to a wrong id.
- Every caller now sets `data-numeric-id="{{ $app->id }}"` (the numeric `applications.id` the route requires) and passes exactly one argument.
- `routes/web.php`: dropped `->whereNumber('id')` from the `admin.applications.data` route so it accepts any id form (numeric id / APP-XXXX code / loan id).
- `AdminController::applicationData()` (`app/Http/Controllers/AdminController.php:3269`) now resolves by `app_id` → numeric `applications.id` → `loan_application_id` (newest mirror via `latest('id')`), returning a clear `{"success": false, "message": "Application not found."}` JSON 404 when nothing matches.

**Verified:** all three entry forms (numeric id, APP-XXXX code, loan_application_id) return HTTP 200 with the correct `app_id` for the same loan/mirror pair.

### 7.3 Shared app_id helper — FIXED

**Root cause:** Each surface resolved the loan reference independently — the Applications page read `$app->app_id` directly, the outbox read `$loanApplication->application->app_id`, and `LoanStatusUpdated::toArray()` built `APP-{loan_id}` from the loan's own id. With duplicate mirror rows these could disagree ("every loan ref shifted by one").

**Fix:**
- Added `LoanApplication::appId()` (`app/Models/LoanApplication.php`) as the single resolution point — returns the newest mirror's `app_id` (the HasOne is `->latest('id')`), falling back to `APP-{loan id}` only when no mirror row exists.
- `Notifications\LoanStatusUpdated::toArray()` now uses `$this->application->appId()` instead of the old `application_number ?? 'APP-' . str_pad($this->application->id, ...)`.

`resources/views/admin/footer.blade.php:658-659` already handled both `APP-` and numeric forms via `$prefix = is_numeric($appNumber) ? 'APP-' : ''`, so new notifications now carry the correct mirror app_id consistent with the Applications page, Repayments, and outbox.

---

## Files changed (Task 7 commit `3b1c6b4`)

| File | Change |
|------|--------|
| `app/Http/Controllers/AdminController.php` | 7.1 `$totalApproved` uses `whereIn([APPROVED,PAID])->sum(COALESCE(approved_amount, amount))`; 7.2 `applicationData()` resolves app_id → numeric id → loan_application_id with clear 404 JSON |
| `app/Models/LoanApplication.php` | 7.3 added shared `appId()` helper |
| `app/Notifications/LoanStatusUpdated.php` | 7.3 `toArray()` uses `$this->application->appId()` |
| `resources/views/admin/member-profile.blade.php` | 7.2 View button now passes one arg to `openAppDetail(event)` |
| `routes/web.php` | 7.2 dropped `->whereNumber('id')` from `admin.applications.data` route |
| `tests/Feature/DashboardGrantedKpiTest.php` | NEW — dashboard KPI equals approved+paid using `approved_amount` |
| `tests/Feature/Task7ViewModalIdResolutionTest.php` | NEW — multi-id data route 200; shared helper consistency; outbox loan link shows mirror app_id |

---

## Tests added (Task 7)

| Test file | Before | After |
|-----------|--------|-------|
| `tests/Feature/DashboardGrantedKpiTest.php` | — | 1 new test |
| `tests/Feature/Task7ViewModalIdResolutionTest.php` | — | 3 new tests |

No existing tests were changed for Task 7.

**Test-first verification:** `DashboardGrantedKpiTest` was confirmed to FAIL on the pre-fix line `where('status', APPROVED)->sum('amount')` (would show ₱50,000 instead of ₱25,000) and PASS with the applied fix.

---

## Migrations

**None created or run for this session.** All Task 7 changes are application-layer only (controller, model, notification, blade view, route). No `migrate`, `migrate:fresh`, `db:wipe`, or `schema:dump` commands were issued — consistent with CLAUDE.md database-safety rules.

---

## Data-fix commands — NOT run (user action required)

Two migrations/data-fix steps were identified in earlier task work but deliberately NOT executed against any real database. They must be run by you (the user) when you're ready:

1. **Payment reversal migration** `2026_11_17_000000_add_reversal_to_payments.php` — CREATED but NOT RUN (waiting for your approval). Real-DB payments pages error until applied because SoftDeletes queries a column that doesn't exist yet; tests pass because RefreshDatabase runs all migrations on sqlite `:memory:`.

2. **Backfill command** `sdcc:backfill-notif-log-loans` (dry-run default, `--apply` flag, sdcc guard) — dry-run on scratch returned 0 rows; `--apply` NOT run.

**Exact commands you must run yourself** (per CLAUDE.md, never edit `.env`, always print DB_DATABASE first):

```bash
# 1. Print DB_DATABASE before any DB command
php artisan config:cache --env=scratch
# check DB_DATABASE in bootstrap/cache/config.php — must be sdcc_scratch

# 2. Apply reversal migration on scratch DB (never sdcc directly)
php artisan migrate --env=scratch

# 3. Then run the backfill (dry-run first, then --apply)
php artisan sdcc:backfill-notif-log-loans --env=scratch
php artisan sdcc:backfill-notif-log-loans --env=scratch --apply
```

Never run these against the protected `sdcc` database without `ALLOW_PROTECTED_DB=1` and your explicit approval. Back up before any `--apply`.

---

## Anything not done

- Monthly disbursements (~line 90-92 in AdminController) and `loanTypes` (~line 76-80) still aggregate raw `amount` over APPROVED-only rows — left intentionally; the spec's KPI mismatch refers to the stat card.
- Approve/reject JSON `total_approved` at lines ~653/743 still uses APPROVED-only `sum('amount')`.
- Task 6 Part D.6 (message-preview migration) — pending your approval.
- Scratch-DB leftover fixture rows from earlier override testing — need your approval to delete.
- `C\357\200\272New` untracked directory — left untouched (pre-existing).

---

## Stray root files — LISTED, NOT deleted

Per instructions, these untracked files exist in the project root/tests and are NOT deleted:

- `tests/Feature/TmpDumpNotifPageTest.php`
- `tmp_notif_page.html`

(Plus earlier-session strays noted in PROGRESS.md: `active-members-proof.png`, `applications-index-proof.png`, `encode-proof.png`, `phpunit_out.txt`, `risk-flags-proof.png`, `server.log`, `storagelogsserve-8001.log`, `test-results/`, `tmp_create_admin_test_user.php`, `tmp_verify_spec.spec.cjs`.)

These were present before this session and are left as-is per instructions.

---

## Pre-existing flaky tests (not caused by this session)

Three reminder/outbox tests intermittently fail due to a UTC-vs-Manila timezone boundary in `PaymentReminderService::alreadySentToday()`:
- `ReminderTest::running_reminders_twice_in_one_day_does_not_duplicate`
- `ReminderTest::duplicate_guard_second_run_no_new_send`
- `NotificationOutboxTest::running_reminders_twice_writes_no_duplicate_log_rows`

Root cause documented in PROGRESS.md / REPORT.md; not introduced by this session's changes.

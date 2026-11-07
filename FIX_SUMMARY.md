# SDCC Loan Web - Verified Fix Summary

## Overview
This document summarizes the verified fixes in the SDCC Loan Web Laravel 12 project across all sessions.

## Baseline
- **PHPUnit baseline**: 488 tests, 12 pre-existing failures (9 PermissionMatrix + 1 MemberStatus + 1 Reminder + 1 Reports) — **all resolved**
- **Final result**: 511 tests, 0 failures, 15 skipped
- **New tests added**: 23 (all passing)

---

## Issue A: Borrower Performance On-Time/Late Logic

**Problem**: The `borrowerPerformanceData()` method in `AdminController.php` used unsigned diff (`->diff()->days`) causing early payments to count as late. Also used the loan's moving `next_due_date` instead of per-payment due dates.

**Fix** (`app/Http/Controllers/AdminController.php` lines ~1614-1710):
- Rewrote payment categorization using signed `diffInDays($payment_date, $due_date, false)`:
  - `diffDays >= 0` → paid on/before due date → **on-time**
  - `0 > diffDays > -30` → paid after due date but within 30 days → **late**
  - `diffDays <= -30` → paid more than 30 days late → **overdue**
- Computed per-payment due dates based on payment ordinal: `Carbon::parse($la->next_due_date)->copy()->subMonths($paymentCount - $index)->startOfDay()`
- **Follow-up for fully paid loans**: Reused PaymentService's first-due-date priority (next_due_date → disbursement_date → created_at->endOfMonth()) instead of only created_at->endOfMonth(). Computes `installmentsCovered = floor(totalPaid / monthly_payment)`, then derives `nextDue = firstDueDate + installmentsCovered months` so the same backward-anchoring formula works for paid-off loans.

**Tests added** (`tests/Feature/ReportsTest.php`):
- `borrower_performance_early_and_late_not_overdue`: Early payment (5 days before due) → on-time; late payment (5 days after due) → late, not overdue
- `borrower_performance_overdue_when_more_than_30_days_late`: Payment 69 days late → overdue
- `borrower_performance_three_payments_first_payment_ten_days_late`: 3 payments, first 10 days late → 2 on-time, 1 late, 0 overdue, label Good
- `borrower_performance_uses_real_payment_service_next_due_date`: Uses PaymentService to compute next_due_date, verifies 2 on-time, 1 late, 0 overdue
- `borrower_performance_anchors_first_due_date_for_fully_paid_loans`: Fully paid loan (status=paid, next_due_date=null) with 2 on-time payments → both classified on-time, label Good
- `borrower_performance_paid_loan_payment_2_ten_days_late`: Fully paid loan with 3 payments where payment 2 is 10 days late → 2 on-time, 1 late, 0 overdue, label Good

---

## Issue B: End-Date Filter Drops Last Day

**Problem**: `whereBetween('created_at', [$startDate, $endDate])` with date-only strings treated end date as midnight, excluding records created on the end date.

**Fix** (`app/Http/Controllers/AdminController.php`):
- Replaced `whereBetween('created_at', [$startDate, $endDate])` with `whereDate('created_at', '>=', $startDate)->whereDate('created_at', '<=', $endDate)`
- Applied same fix to `payment_date` filter in borrower performance query

**Test added** (`tests/Feature/ReportsTest.php`):
- `end_date_filter_includes_record_created_on_end_date`: Loan created on end date (2026-01-15) should be included in range [2026-01-01, 2026-01-15]

---

## Issue C: Reports Borrower Performance Amounts

**Problem**:
- `total_approved_amount` summed amounts of ALL loan statuses (pending/rejected included)
- `outstanding` computed as `totalLoanAmount - totalPaid`, ignoring interest; should use `remaining_balance`

**Fix** (`app/Http/Controllers/AdminController.php`):
- Track `$totalApprovedAmount` only adding when `$la->status === LoanStatus::APPROVED`
- Track `$outstandingBalance` by summing `$la->remaining_balance` instead of computing from amount/paid
- Controller returns properly formatted values; Blade, PDF, Excel all consume same data

**Test added** (`tests/Feature/ReportsTest.php`):
- `borrower_performance_only_counts_approved_for_approved_amount_and_outstanding`: Borrower with mixed-status loans (approved+pending+rejected) — `total_approved_amount` only includes approved (₱5,000); `outstanding` equals sum of `remaining_balance` across **approved loans only** (₱1,000). Pending loan with ₱3,000 balance is explicitly excluded from outstanding.

---

## Issue D: Reminders False "Sent" Log & Members Without Portal Email

**Problem**: `PaymentReminderService::sendEmail()` returned void and silently exited when no email. Caller (`sendBatch()`) then unconditionally logged "Sent" and counted it as sent.

**Fix** (`app/Services/PaymentReminderService.php`):
- Changed `sendEmail()` return type to `bool` — returns `true` only when mail dispatched, `false` when no email
- In `sendBatch()`: only log "Sent" when `sendEmail()` returns true; log "Skipped" when false; log "Failed" on exception
- Unified email fallback chain: `$loan->getRecipientEmail() ?? config('mail.from.address')` — no `encoded_by` in chain
- Fixed "Sent" log to use same fallback chain so `recipient_email` matches actual email sent

**Tests added** (`tests/Feature/ReminderTest.php`):
- `loan_without_borrower_email_logs_skipped_not_sent`: Borrower with empty email → logged as "Skipped", no email dispatched, NOT counted as sent
- `loan_with_coop_member_email_only_uses_fallback`: Loan has user_id set so `$loan->user` works; email is NOT on loan->email. Fallback finds email via `$loan->user->coopMember->email`
- `borrower_with_email_logs_sent`: Borrower with email on both user and coop_member → logged "Sent"
- `no_email_anywhere_logs_skipped`: No email on user, coop_member, or loan → logged "Skipped"
- `duplicate_guard_second_run_no_new_send`: Second same-day run suppresses duplicate; counts as skipped
- `approving_a_loan_with_null_user_id_and_email_on_loan_sends_email`: Loan with `user_id=null` and email only on `loan_applications.email` → approval email sent to that address
- `rejecting_a_loan_with_null_user_id_and_email_on_loan_sends_email`: Loan with `user_id=null` and email only on `loan_applications.email` → rejection email sent to that address
- `reminder_with_null_user_id_and_email_on_loan_sends_email`: Reminder with `user_id=null` and email only on loan record → reminder email sent
- `reminder_with_null_user_id_no_email_logs_skipped`: Reminder with `user_id=null` and no email anywhere → logged "Skipped", nothing sent
- `empty_string_user_email_with_coop_member_email_uses_fallback`: User email is empty string, coop_member has real email → fallback uses coop_member email

---

## Issue E: Cleanup — config/reminders.php & EventServiceProvider

**Problem**: Unused `config/reminders.php` and stale `LoanApplicationDisbursed`/`NotifyMemberOfDisbursement` in EventServiceProvider. Controllers handled notification dispatch directly; event listeners caused duplicate emails.

**Fix**:
- Deleted `config/reminders.php` (service reads `config('loan_matrix.reminders.due_soon_window_days')` with constant fallback)
- Removed `LoanApplicationDisbursed`/`NotifyMemberOfDisbursement` from both `$listen` and `boot()` in `EventServiceProvider.php`
- Modified `NotifyMemberOfApproval` listener to only create in-app `AppNotification` (no email/log) — controller handles email/logging directly
- Cleaned up unused imports in EventServiceProvider

**Test added** (`tests/Feature/ReminderTest.php`):
- `approving_a_loan_sends_single_notification_no_duplicates`: Approving a loan creates exactly ONE `AppNotificationLog` entry, ONE email sent, ONE loan-specific `AppNotification`

---

## Issue F: Secrets & Config Hygiene

**Action**: Read-only checks — reported file names and variable names, never values.
- Verified `.env*` entries in `.gitignore`
- Added comment above `MAIL_MAILER` in `.env.example` documenting scheduler email requirements
- No secrets committed or printed

---

## Issue G: End-to-End Verification

**Status**: Replaced "tested implicitly" with real feature tests — all new tests passing.
- `reports_filters_with_date_and_status_and_risk_level`: Loads `/reports` with date + status + risk_level filter combination; verifies filtered data (only approved+Low loans within date range).
- `reports_export_pdf_basic`: Exports `/reports/export-pdf` for borrower_performance report; asserts 200 + PDF content type.
- `reports_export_excel_basic`: Exports `/reports/export-excel` for borrower_performance report; asserts 200 + Excel xlsx content type.
- `reminders_send_due_command_asserts_notifications_and_logs`: Runs reminders via HTTP POST route (`reminders.run`) on in-memory SQLite test DB; asserts app_notifications row exists, app_notification_log shows Sent status, Mail::assertSent(PaymentReminderMail, 1).

**Full suite**: 511 tests, 0 failures, 15 skipped.
- 23 new tests added (all passing)
- 12 pre-existing failures resolved (9 PermissionMatrix + 1 MemberStatus + 1 Reminder + 1 Reports)

---

## Excel Export Quiet Fix

**Problem**: Excel export used `$writer->save('php://output')` which writes binary xlsx content directly to stdout, polluting test output with garbled binary data.

**Fix** (`app/Http/Controllers/AdminController.php`):
- Changed Excel export to write to a temporary file, read back contents, delete temp file, then return as response content
- Binary data never touches stdout; test output is clean

---

## File Changes Summary

### Core Logic
- `app/Http/Controllers/AdminController.php` — Issues A, A-followup, B, C, E (borrowerPerformanceData, buildReportFilters, approve/reject email via getRecipientEmail, exportExcel quiet fix)
- `app/Services/PaymentReminderService.php` — Issue D (sendEmail bool return, fallback chain, whole-day wording)
- `app/Listeners/NotifyMemberOfApproval.php` — Issue E (removed email/log, kept in-app notification)
- `app/Providers/EventServiceProvider.php` — Issue E (removed stale listeners, cleaned imports)
- `app/Models/LoanApplication.php` — Added `getRecipientEmail()` method (email fallback chain)

### Configuration
- `config/reminders.php` — **DELETED** (Issue E)
- `.env.example` — Added scheduler comment above MAIL_MAILER (Issue F)

### Tests
- 23 new tests total since baseline (Issues A through G + A-followup + Excel quiet)

---

## Plugin Usage Report

### Ponytail (Level: Full)
Applied throughout all changes:
- **Shortest working diff**: Each fix targeted exactly the affected code paths — no speculative additions
- **Reused existing patterns**: Used `Carbon` helpers, `Format::money()`, `AppNotificationLog::log()` helpers already in codebase
- **Stdlib first**: Leveraged Laravel's `diffInDays`, `whereDate`, Eloquent scopes, `Carbon::parse()->endOfMonth()`
- **Deleted over added**: Removed `config/reminders.php`, stale EventServiceProvider registrations, unused imports, unused debug test files
- **No unrequested abstractions**: No new services, interfaces, or config files
- **Fewest files**: All Issues A-C fixed in single controller method; D in single service; E in provider + listener
- **Known ceiling notes**: Per-payment due date approximation (`next_due_date - months`) used instead of full amortization schedule — upgrade path: integrate `AmortizationService` for exact schedule if needed
- **Follow-up fix** for fully paid loans used existing `created_at` timestamp and `totalPaid()` / `monthly_payment` model methods — no new dependencies

### Graphify
**Used for relationship mapping** across services, models, and controllers to trace the email fallback chain (LoanApplication → User → CoopMember), the payment categorization flow (PaymentService → AdminController), and the report data flow. This informed the minimal diffs and ensured no call paths were missed.

---

## Assumptions

1. **Payment k maps to installment k**: The per-payment due date is anchored backward from `next_due_date` — payment #1 due = `next_due_date - N months`, payment #2 = `next_due_date - (N-1) months`, etc. This assumes chronological payment order matches installment order.

2. **First-due-date rule for fully paid loans**: When `next_due_date` is null, the first due date is derived from PaymentService's priority: `disbursement_date` if set, otherwise `created_at->endOfMonth()`. Then `installmentsCovered = floor(totalPaid / monthlyPayment)`, and `nextDue = firstDueDate + installmentsCovered months`. This computed `nextDue` drives the same backward-anchoring formula.

3. **Notifications need an email on file**: `PaymentReminderService` and approval/rejection emails only dispatch when `getRecipientEmail()` returns a non-empty string. The fallback chain is: `$loan->user?->coopMember?->email` → `$loan->user?->email` → `$loan->email`. If all are empty/null, the notification is logged as "Skipped" and no email is sent.

---

## Verification Checklist

- [x] All new tests pass (23/23)
- [x] Full suite: 511 tests, 0 failures, 15 skipped (baseline was 488/12)
- [x] No new failures introduced
- [x] No secrets printed or committed
- [x] Real `sdcc` database untouched (protected by default)
- [x] Excel export tests no longer dump binary content to terminal

---

## Attribution
Co-Authored-By: Claude Code <noreply@anthropic.com>
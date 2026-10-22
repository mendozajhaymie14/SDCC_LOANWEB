# SDCC LoanWEB

**San Dionisio Credit Cooperative — Loan Origination, Evaluation & Administration Web Application**

A Laravel 12.x application that digitizes the cooperative's loan application workflow:
member submission → credit evaluation → approval/rejection → repayment tracking → reminders.

Single source of truth for **rule-based loan evaluation** (`LoanEvaluationService`) plus an explicit
flat permission matrix consumed by both middleware and sidebar UI.

---

## Tech Stack (dev environment)

| Concern | Choice |
|---------|--------|
| Framework | Laravel 12.x (PHP ^8.2) |
| Database | MySQL 10.4+ (prod) or SQLite :memory: (phpunit) |
| Auth | Laravel Fortify + Jetstream + Tailwind; explicit permission middleware (`app/Http/Middleware/RoleMiddleware.php`) |
| Frontend | Blade, Tailwind CSS, Alpine.js, Vite build into `public/build/` |
| PDF | `barryvdh/laravel-dompdf` |
| AI document analysis | Google Gemini API (optional; advisory-only when `GEMINI_API_KEY` absent) |
| Testing | PHPUnit 11, SQLite in-memory, `RefreshDatabase` |

**Dev server:** `php artisan serve` on the configured port (`APP_PORT=8000`). Start Apache/MySQL if you use
XAMPP rather than the PHP CLI embedded server. For the build, Vite bundles the JS/CSS into
`public/build/` and the production manifest is served by the PHP server.

---

## Setup

```bash
# 1. Configure environment
cp .env.example .env
# Edit .env: DB_CONNECTION (mysql or sqlite), DB_DATABASE, GEMINI_API_KEY (optional),
#            PASSWORD hashing rounds, session lifetime, mail driver.
# NOTE: never commit .env. A scratch copy (.env.scratch) mirrors local setup; see README setup below.

# 2. Install dependencies
composer install
npm install && npm run build       # bundles frontend into public/build/

# 3. Scratch database setup (NEVER touch the production 'sdcc' database)
APP_ENV=scratch php artisan key:generate
APP_ENV=scratch php artisan storage:link            # symlink local storage -> public/storage (dev only)
APP_ENV=scratch php artisan migrate --seed          # seeds the RoleDemo users
echo "Scratch server:"
APP_ENV=scratch php artisan serve --port=8000

# 4. Background workers (production-style)
APP_ENV=local php artisan queue:work         # runs pending jobs from the database queue
APP_ENV=local php artisan schedule:run        # one-pass of the scheduler; usually triggered by cron
```

**Test suite:**

```bash
php vendor/bin/phpunit --config phpunit.xml
```

The suite asserts every row of the permission matrix (110 data-provider cases), the
registration-form fixes, session expiry, and loan-evaluation scoring. 7 tests are
skipped (expected skipped suites); 0 failures.

### Scratch workflow shortcut

When using the embedded PHP server against `.env.scratch` directly (as in browser
tests), `php artisan serve` inherits `APP_ENV` from the parent process, so ALWAYS start
it with the env var explicit:

```bash
APP_ENV=scratch LOG_LEVEL=debug php artisan serve --port=8000
```

---

## Seeded Demo Accounts (per role — credentials live in `.env`, never printed here)

| Role | `usertype` | Login | Email |
|------|-----------|-------|-------|
| System Administrator | `admin` | admin@sdcc.coop | admin@sdcc.coop |
| Branch Manager | `branch_manager` | branchmgr@sdcc.coop | branchmgr@sdcc.coop |
| Loan Officer | `loan_officer` | loanofficer@sdcc.coop | loanofficer@sdcc.coop |
| Credit Investigator | `credit_investigator` | creditinv@sdcc.coop | creditinv@sdcc.coop |
| Cooperative Member | `member` | member@sdcc.coop | member@sdcc.coop |
| Registered User | `user` | user@sdcc.coop | user@sdcc.coop |

Credentials come from the env vars `ADMIN_DEMO_PASSWORD`,
`BRANCH_MANAGER_DEMO_PASSWORD`, `LOAN_OFFICER_DEMO_PASSWORD`,
`CREDIT_INVESTIGATOR_DEMO_PASSWORD`, `MEMBER_DEMO_PASSWORD`, `USER_DEMO_PASSWORD`
(`ADMIN_SEED_PASSWORD` also accepted). The seeders **throw** if any value is blank, and
skip in production unless `SEED_DEMO_USERS=true`. See `database/seeders/RoleDemoSeeder.php`.

---

## Known Limitations (from the design paper, §1; plus scope conflicts noted during this pass)

1. **Single cooperative scope** — multi-tenancy is not supported; `coop_members` assumes one SDCC instance.
2. **Rule weights hard-coded** in `LoanEvaluationService` (DTI 40 / History 30 / Docs 15 / Capital 15). Configurable weights land in `config/loan_evaluation.php` (Task E); otherwise changing them requires a code deploy.
3. **No disbursement processing** — the disbursements page records release events only (date, reference); it never moves money and there is no payment gateway. Paper C1 explicitly forbids payout logic.
4. **AI is advisory only** — the Gemini integration documents each application (document check) but must never change the recommended amount or risk score. Label it "Document check (assistive)"; the app works fully with `GEMINI_API_KEY` empty (paper C2).
5. **Member-only reminders; staff encoding TBD** — members may self-submit applications; staff encoding of applications and member profiles (FR-05–09) is still being built (Task D). Both paths feed the same tables.
6. **Reminder channel** — the `mail` log driver is delivered in local dev; SMS is not implemented unless added. Duplicate-send guards and post-payment silence are covered by tests (Task E).
7. **Tech-stack deviation** — the paper describes Bootstrap / HTML-CSS-JS; the implementation uses Laravel, Livewire-adjacent views, Tailwind CSS and Jetstream (paper C5 listed this as an accepted deviation). It was not rewritten.
8. **Daily automated backup not yet scheduled** — the manual backup route exists but a daily `schedule:run` job writing to a non-public folder with a 14-day retention cap lands in Task G.
9. **CSV-import-from-Google-Sheets helper not yet added** — `import:members` / `import:loans` commands (Task H) are next to come.
10. **Index review not yet run** — NFR-02/NFR-01 structural review of slow queries is deferred to Task G; 500-member/1000-loan load test is pending verification.
11. **No REST/GraphQL layer** — all endpoints are Blade-rendered. Paper limitation (under §1).
12. **Local file storage only** — `public/storage` symlinked; no S3/cloud driver configured (paper §1.9).
13. **Demo accounts never created in production** — seeders skip unless `SEED_DEMO_USERS=true`; operator supplies the real `.env` and seeds manually (same rule as Team B §5).

---

## Project Structure (Key Paths)

```
app/
├── Actions/Fortify/CreateNewUser.php     # memberships via usertype='member'
├── Http/Controllers/AdminController.php  # all admin routes
├── Http/Middleware/RoleMiddleware.php    # deny-by-default group + route guard
├── Models/{Application,LoanApplication,Borrowers,CoopMember,User}.php
├── Support/Permissions.php               # the single permission matrix (one place)
├── Services/LoanEvaluationService.php    # rule engine
└── Jobs/ProcessLoanDocuments.php         # async Gemini OCR + analysis
database/migrations/                       # last_scanned_at guarded (idempotent)
tests/                                   # Feature suite; Browser suite (Playwright)
scripts/dev/                              # scratch scripts (gitignored)
docs/dev-notes/                           # internal audit/analysis docs (gitignored)
```

The four `last_scanned_at` migrations use `Schema::hasColumn('table', 'col')` guards so a
repeat `migrate:fresh` is safe (verified on `sdcc_scratch`).

---

## Quick Test

```bash
php vendor/bin/phpunit --config phpunit.xml --testdox
```

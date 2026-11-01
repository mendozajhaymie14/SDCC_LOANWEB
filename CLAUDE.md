# CLAUDE.md — SDCC Loan Web Project Rules

## Database Safety

**Never run destructive database commands against the real `sdcc` database.**

### Protected databases
- `sdcc` is protected by default (config `sdcc.protected_databases`).
- Override with env `SDCC_PROTECTED_DATABASES=sdcc,other` (comma-separated).
- Any database name ending in `_scratch` or `_test` is auto-protected.
- Use `--env=scratch` to switch to `sdcc_scratch` for all work.

### Blocked commands (unless `ALLOW_PROTECTED_DB=1` is set on the command line)
- `migrate:fresh`
- `migrate:refresh`
- `migrate:reset`
- `migrate:rollback`
- `db:wipe`
- `db:seed`
- `schema:dump --prune`
- Plain `migrate` (without `--pretend`)

### Rules
1. **Print `DB_DATABASE` before any DB command.** Run:
   ```
   php artisan config:cache --env=scratch
   ```
   and check `DB_DATABASE` in `bootstrap/cache/config.php`.
2. **Never edit `.env`** — use `.env.scratch` for scratch work.
3. **Never delete or move files under `C:\xampp\mysql`** — ask before any command that touches MySQL data files.
4. **Ask before any command that modifies a database.**

### Running migrations on real `sdcc` database (Windows)

The real `sdcc` database is protected by default. Use `--env=scratch` to work with the scratch database instead.

To run migrations on the protected `sdcc` database with explicit override:

**Command Prompt (cmd):**
```cmd
set ALLOW_PROTECTED_DB=1 && php artisan migrate
```

**PowerShell:**
```powershell
$env:ALLOW_PROTECTED_DB=1; php artisan migrate
```

Plain `php artisan migrate` (without `ALLOW_PROTECTED_DB=1`) on the `sdcc` database will throw a `ProtectedDatabaseException` and print the database name being used.

### Scratch DB
- Scratch DB: `sdcc_scratch` (see `.env.scratch`).
- Always run `php artisan serve --env=scratch --port=8001` for browser tests.
- Never touch `sdcc`.

## Project Conventions
- Laravel 12, PHP 8.2, Blade templating
- Scratch DB `sdcc_scratch` via `--env=scratch`
- RBAC with 6 roles via `App\Support\Permissions`
- Dual tables: `loan_applications` (member) + `applications` (admin)
- Shared layout `admin.index` with `@yield('admin_content')`
- `Format` class: `money($amount)`, `date($date, format)`
- `LoanStatus` constants: PENDING/APPROVED/DISBURSED/REJECTED/PAID/FLAGGED
- Playwright for browser tests on `php artisan serve --env=scratch --port=8001`

## Testing
- `phpunit.xml` forces `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`
- Tests must never use MySQL by accident (checked in `tests/TestCase.php`)
- Run: `vendor/bin/phpunit`
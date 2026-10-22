# SDCC Loan Web - Project Overview

## Framework and Dependencies
- **Laravel Framework**: Version ^12.0 (from composer.json)
- **PHP Version**: ^8.2 required
- **Key Packages**:
  - barryvdh/laravel-dompdf: ^3.1 (PDF generation)
  - google-gemini-php/laravel: ^2.0 (AI document analysis via Gemini Vision)
  - laravel/jetstream: ^5.5 (authentication, teams, profile)
  - laravel/sanctum: ^4.0 (API tokens)
  - laravel/tinker: ^3.10 (REPL for Laravel)
  - livewire/livewire: ^3.6 (reactive frontend components)
  - Development packages include: phpunit, pest, laravel/sail, laravel/pint, etc.

## Environment Configuration
From `.env.example` (never read actual .env for security):
- **Database**: Configured for SQLite by default (`DB_CONNECTION=sqlite`)
- **Session**: Database driver with 120-minute lifetime
- **Queue**: Database connection for delayed jobs
- **Gemini API**: Placeholder for `GEMINI_API_KEY` (used for document OCR/analysis)
- **Encryption**: BCRYPT_ROUNDS=12 for password hashing
- **Application**: Local development mode with debug enabled

## Local Setup Instructions
Based on composer.json scripts:
1. Copy `.env.example` to `.env` and configure environment variables
2. Run `composer install` to install PHP dependencies
3. Run `php artisan key:generate` to set application key
4. Run `php artisan migrate --force` to create database tables
5. Install Node.js dependencies: `npm install`
6. Build assets: `npm run build`
7. Start development server: `php artisan serve`
8. For queue processing: `php artisan queue:listen --tries=1 --timeout=0`
9. For file monitoring: `php artisan pail --timeout=0`
10. For Vite hot reload: `npm run dev`

## Folder Structure (Names Only)

### app/
- Actions/ (Fortify/Jetstream action classes)
- Console/ (Artisan commands: SendPaymentReminders.php, TestScanDoc.php)
- Events/ (Loan application events)
- Http/Controllers/ (AdminController.php, UserController.php, MemberApplicationController.php, etc.)
- Jobs/ (ProcessLoanDocuments.php for async document analysis)
- Listeners/ (Notification listeners for loan events)
- Models/ (Eloquent models: User, LoanApplication, Application, CoopMember, etc.)
- Notifications/ (Loan status update notifications)
- Providers/ (Service providers: App, Event, Fortify, Jetstream)
- Services/ (Business logic: CreditScoringService, DocumentScannerService, AmortizationService)
- View/Components/ (Blade components: AppLayout, GuestLayout)
- View/ (Blade components directory)

### routes/
- api.php (API routes - currently minimal)
- console.php (Artisan command scheduling: reminders:send-due daily at 08:00)
- web.php (Main web routes with auth middleware groups)

### resources/views/
- admin/ (Admin dashboard blades: index, body, footer, sidebar, partials, modals)
- auth/ (Authentication blades: login, register, member-application, etc.)
- user/ (Member dashboard blades: index, loan-form, amortization, etc.)
- emails/ (Email templates)
- home/ (Public-facing blades: index, faqs, css, etc.)
- layouts/ (Application layouts)

### database/migrations/
- Consolidated migration files creating core tables:
  - 2026_09_29_000001_users_table.php
  - 2026_09_29_000002_coop_members_table.php
  - 2026_09_29_000003_loan_applications_table.php
  - 2026_09_29_000004_applications_table.php
  - 2026_09_29_000005_borrowers_table.php
  - 2026_09_29_000006_app_notifications_table.php
  - Plus additional migrations for features added later (payments, reminders, audit logs, etc.)
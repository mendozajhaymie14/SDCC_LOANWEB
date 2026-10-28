<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\MemberVerificationController;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Illuminate\Support\Facades\Auth;
use App\Support\Permissions;

Route::middleware(['auth', 'role'])->group(function () {

    // Where Fortify sends people after login. Routes by permission, not by
    // hard-coded usertype: staff with view_dashboard get the admin panel,
    // members land on their own portal.
    Route::get('/dashboard', function () {
        $user = Auth::user();

        if (Permissions::has($user, Permissions::OWN_PORTAL)) {
            return redirect()->route('user.dashboard');
        }

        if (Permissions::has($user, Permissions::VIEW_DASHBOARD)) {
            return app(AdminController::class)->index();
        }

        abort(403, 'Unauthorized action.');
    })->middleware('role:' . Permissions::VIEW_DASHBOARD)->name('dashboard');

    Route::get('/home', fn () => redirect()->route('dashboard'))
        ->middleware('role:' . Permissions::VIEW_DASHBOARD)
        ->name('home');

    // Member area - own portal, view-only on loans
    Route::middleware(['role:' . Permissions::OWN_PORTAL])->prefix('user')->name('user.')->group(function () {
        Route::get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');

        Route::get('/notifications', [UserController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/{notification}/read', [UserController::class, 'markNotificationRead'])->name('notifications.read');

        // Member-facing notification feed (fetched by the portal header)
        Route::get('/api/member/notifications', [UserController::class, 'memberNotifications'])
            ->name('api.member.notifications');

        // Applying for a loan needs an active cooperative membership. A
        // registered non-member is redirected to the membership page with a
        // friendly message instead of a raw error (see RoleMiddleware).
        Route::get('/loans/apply', [UserController::class, 'createApplication'])
            ->middleware('role:' . Permissions::APPLY_LOAN)
            ->name('loans.create');
        Route::post('/loans', [UserController::class, 'storeApplication'])
            ->middleware('role:' . Permissions::APPLY_LOAN)
            ->name('loans.store');
        Route::delete('/loans/{application}', [UserController::class, 'cancelApplication'])
            ->middleware('role:' . Permissions::APPLY_LOAN)
            ->name('loans.cancel');
    });

    // 'Become a member' — the membership form and the applicant's own status.
    // Registered users only; a guest uses the public /member-application form.
    Route::middleware(['role:' . Permissions::APPLY_MEMBERSHIP])->prefix('user')->name('user.')->group(function () {
        Route::get('/membership', [UserController::class, 'membership'])->name('membership');
    });

    // Member's own loans - view only, gated separately from the rest of the portal
    Route::middleware(['role:' . Permissions::VIEW_OWN_LOANS])->prefix('user')->name('user.')->group(function () {
        Route::get('/loans/{application}/amortization', [UserController::class, 'amortizationSchedule'])
            ->name('loans.amortization');
    });

    // Member-facing amortization PDF export - named outside the 'user.' group so
    // the view keeps its original route('export.amortization.pdf') reference.
    Route::middleware(['role:' . Permissions::VIEW_OWN_LOANS])
        ->get('/user/loans/{application}/amortization/pdf', [UserController::class, 'exportAmortizationPdf'])
        ->name('export.amortization.pdf');

    // ------------------------------------------------------------------
    // Admin: user / role management, settings, backups, audit logs
    // ------------------------------------------------------------------
    Route::middleware(['role:' . Permissions::MANAGE_USERS])->group(function () {
        // Admin Users page — lists staff accounts
        Route::get('/admin/users', [AdminController::class, 'adminUsers'])
            ->name('admins.index');
        Route::post('/admin/users', [AdminController::class, 'storeAdmin'])
            ->name('admins.store');
        Route::put('/admin/users/{id}', [AdminController::class, 'updateAdmin'])
            ->name('admins.update');
        Route::delete('/admin/users/{id}', [AdminController::class, 'destroyAdmin'])
            ->name('admins.destroy');
    });

    Route::middleware(['role:' . Permissions::MANAGE_SETTINGS])->group(function () {
        Route::get('/admin/settings', [AdminController::class, 'settings'])
            ->name('settings.index');
        Route::post('/admin/settings', [AdminController::class, 'updateSettings'])
            ->name('settings.update');
    });

    Route::middleware(['role:' . Permissions::MANAGE_BACKUPS])->group(function () {
        Route::get('/admin/backup', [AdminController::class, 'backup'])
            ->name('admin.backup');
    });

    Route::middleware(['role:' . Permissions::VIEW_AUDIT_LOGS])->group(function () {
        Route::get('/admin/audit-logs', [AdminController::class, 'auditLogs'])
            ->name('admin.audit-logs');
    });

    // ------------------------------------------------------------------
    // Branch manager: reports, staff activity, loan decisions, memberships,
    // release records, audit log viewing
    // ------------------------------------------------------------------
    Route::middleware(['role:' . Permissions::VIEW_REPORTS])->group(function () {
        Route::get('/reports', [AdminController::class, 'reports'])
            ->name('reports.index');
        Route::get('/reports/export-pdf', [AdminController::class, 'exportPdf'])
            ->name('reports.exportPdf');
        Route::get('/reports/export-excel', [AdminController::class, 'exportExcel'])
            ->name('reports.exportExcel');
    });

    Route::middleware(['role:' . Permissions::STAFF_ACTIVITY])->group(function () {
        Route::get('/admin/notifications-log', [AdminController::class, 'notificationLogs'])
            ->name('admin.notifications-log');
    });

    Route::middleware(['role:' . Permissions::APPROVE_LOANS])->group(function () {
        // Approve / reject an application (used by the View drawer's Accept/Reject buttons)
        Route::post('/applications/{appId}/approve', [AdminController::class, 'approve'])
            ->whereNumber('appId')
            ->name('applications.approve');
    });

    Route::middleware(['role:' . Permissions::REJECT_LOANS])->group(function () {
        Route::post('/applications/{appId}/reject', [AdminController::class, 'reject'])
            ->whereNumber('appId')
            ->name('applications.reject');
    });

    Route::middleware(['role:' . Permissions::APPROVE_MEMBERSHIPS])->group(function () {
        // Cooperative membership applications
        Route::get('/memberships', [AdminController::class, 'memberships'])
            ->name('memberships.index');
        Route::post('/memberships/{id}/approve', [AdminController::class, 'approveMember'])
            ->name('memberships.approve');
        Route::post('/memberships/{id}/reject', [AdminController::class, 'rejectMember'])
            ->name('memberships.reject');
        Route::delete('/memberships/{id}', [AdminController::class, 'destroyMember'])
            ->name('memberships.destroy');
        Route::put('/memberships/{id}', [AdminController::class, 'updateMember'])
            ->name('memberships.update');
    });

    Route::middleware(['role:' . Permissions::PROCESS_DISBURSEMENTS])->group(function () {
        // Release record — records that a loan was released to the member.
        // Per the design paper this records the release only; no money moves.
        Route::get('/disbursements', [AdminController::class, 'disbursements'])
            ->name('disbursements.index');
        Route::post('/admin/disbursements/process', [AdminController::class, 'processDisbursement'])
            ->name('disbursements.process');
    });

    // ------------------------------------------------------------------
    // Loan officer + credit investigator: applications, evaluation, members
    // ------------------------------------------------------------------
    Route::middleware(['role:' . Permissions::ENCODE_APPLICATIONS])->group(function () {
        // Route to render all applications page (Controller handles fetching database rows)
        Route::get('/applications', [AdminController::class, 'application'])
            ->name('applications.index');

        // Route to handle form submission and store application in database
        Route::post('/applications', [AdminController::class, 'store'])
            ->name('applications.store');

        Route::delete('/applications/{appId}', [AdminController::class, 'destroy'])
            ->whereNumber('appId')
            ->name('applications.destroy');
    });

    Route::middleware(['role:' . Permissions::VIEW_APPLICATIONS])->group(function () {
        // Re-run the AI credit scan for a specific application (triggered from the detail modal)
        Route::get('/admin/applications/{id}', [AdminController::class, 'showApplication'])
            ->whereNumber('id')
            ->name('admin.applications.show');

        // JSON data endpoint for modal re-fetch
        Route::get('/admin/applications/{id}/data', [AdminController::class, 'applicationData'])
            ->whereNumber('id')
            ->name('admin.applications.data');

        Route::get('/admin/applications/{id}/amortization', [AdminController::class, 'amortizationSchedule'])
            ->name('admin.applications.amortization');
        Route::get('/admin/applications/{id}/amortization/pdf', [AdminController::class, 'exportAmortizationPdf'])
            ->name('admin.applications.amortization.pdf');
    });

    Route::middleware(['role:' . Permissions::VIEW_EVALUATION])->group(function () {
        Route::get('/admin/credit-assessment', [AdminController::class, 'creditAssessment'])
            ->name('credit-assessment.index');
    });

    Route::middleware(['role:' . Permissions::VERIFY_DOCUMENTS])->group(function () {
        Route::post('/admin/applications/{id}/scan', [AdminController::class, 'triggerAiScan'])
            ->whereNumber('id')
            ->name('admin.applications.scan');

        Route::get('/admin/risk-flags', [AdminController::class, 'riskFlags'])
            ->name('risk-flags.index');
    });

    Route::middleware(['role:' . Permissions::ADD_REVIEW_NOTES])->group(function () {
        Route::post('/admin/applications/{id}/notes', [AdminController::class, 'addReviewNote'])
            ->whereNumber('id')
            ->name('admin.applications.notes');
    });

    Route::middleware(['role:' . Permissions::VALIDATE_RECOMMENDATION])->group(function () {
        Route::post('/admin/applications/{id}/recommendation', [AdminController::class, 'validateRecommendation'])
            ->whereNumber('id')
            ->name('admin.applications.recommendation');
    });

    Route::middleware(['role:' . Permissions::VIEW_MEMBER_PROFILES])->group(function () {
        Route::get('/active-members', [AdminController::class, 'borrowers'])
            ->name('active-members.index');
    });

    Route::middleware(['role:' . Permissions::EDIT_MEMBER_PROFILES])->group(function () {
        Route::post('/active-members', [AdminController::class, 'storeBorrower'])
            ->name('active-members.store');

        Route::post('/active-members/{id}/approve', [AdminController::class, 'approveBorrower'])
            ->name('active-members.approve');

        Route::post('/active-members/{id}/reject', [AdminController::class, 'rejectBorrower'])
            ->name('active-members.reject');

        Route::delete('/active-members/{id}', [AdminController::class, 'destroyBorrower'])
            ->name('active-members.destroy');
    });

    // ------------------------------------------------------------------
    // Loan officer + branch manager: record payments
    // ------------------------------------------------------------------
    Route::middleware(['role:' . Permissions::RECORD_PAYMENTS])->group(function () {
        Route::get('/repayments', [AdminController::class, 'repayments'])
            ->name('repayments.index');
        Route::post('/admin/repayments', [AdminController::class, 'storeRepayment'])
            ->name('repayments.store');
        Route::put('/repayments/{id}', [AdminController::class, 'updateRepayment'])
            ->name('repayments.update');
        Route::delete('/repayments/{id}', [AdminController::class, 'destroyRepayment'])
            ->name('repayments.destroy');
    });

    // ------------------------------------------------------------------
    // Staff-wide: reminder run, notification outbox
    // ------------------------------------------------------------------
    Route::middleware(['role:' . Permissions::STAFF_ACTIVITY])->group(function () {
        Route::post('/admin/reminders/run', [AdminController::class, 'runReminders'])
            ->name('reminders.run');

        Route::post('/notifications/mark-read', [AdminController::class, 'markNotificationsRead'])
            ->name('notifications.markRead');
    });
});

// Public routes - no auth required
//
// The old GET /api/member/{memberId} lookup is gone: it handed out member
// names to anybody who guessed an ID, and the register page never worked
// with it anyway. Verification is now a two-step POST exchange.
Route::post('/register/existing-member/send-code', [MemberVerificationController::class, 'sendCode'])
    ->middleware('throttle:member-verification')
    ->name('register.existing-member.send-code');

Route::post('/register/existing-member/verify', [MemberVerificationController::class, 'verify'])
    ->middleware('throttle:member-verification')
    ->name('register.existing-member.verify');

// Cooperative membership application (guests only — for people who are not
// yet registered members). Kept separate from the regular register flow so
// the two paths stay easy to distinguish.
Route::get('/member-application', [\App\Http\Controllers\MemberApplicationController::class, 'create'])->name('member.applications.create');
Route::post('/member-application', [\App\Http\Controllers\MemberApplicationController::class, 'store'])->name('member.applications.store');
Route::get('/member-application/{id}', [\App\Http\Controllers\MemberApplicationController::class, 'show'])->name('member.applications.show');

Route::get('/', function () {
    return view('home.index');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

// Changed /exit to POST
Route::post('/exit', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->regenerateToken();
    return redirect('/login');
})->name('exit');

Route::get('/faqs', function () {
    return view('home.faqs');
})->name('faqs');

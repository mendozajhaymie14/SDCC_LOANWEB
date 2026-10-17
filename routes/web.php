<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UserController;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Illuminate\Support\Facades\Auth;

Route::middleware(['auth'])->group(function () {

    // Where Fortify sends people after login. Splits admins from members
    // using the 'usertype' column already on the users table.
    Route::get('/dashboard', function () {
        return (auth()->user()->usertype === 'admin')
            ? app(AdminController::class)->index()
            : redirect()->route('user.dashboard');
    })->name('dashboard');

    Route::get('/home', fn () => redirect()->route('dashboard'))->name('home');

    // Member area - members only
    Route::middleware(['role:member'])->prefix('user')->name('user.')->group(function () {
        Route::get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');

        Route::get('/notifications', [UserController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/{notification}/read', [UserController::class, 'markNotificationRead'])->name('notifications.read');

        Route::get('/loans/apply', [UserController::class, 'createApplication'])->name('loans.create');
        Route::post('/loans', [UserController::class, 'storeApplication'])->name('loans.store');
        Route::delete('/loans/{application}', [UserController::class, 'cancelApplication'])->name('loans.cancel');

        Route::post('/loans/{application}/pay', [UserController::class, 'markAsPaid'])->name('loans.pay');

        Route::get('/loans/{application}/amortization', [UserController::class, 'amortizationSchedule'])
            ->name('loans.amortization');

        // Member-facing amortization PDF export for user dashboard - moved here with ownership check
        Route::get('/loans/{application}/amortization/pdf', [UserController::class, 'exportAmortizationPdf'])
            ->name('export.amortization.pdf');
    });

    // Admin-only routes
    Route::middleware(['role:admin'])->group(function () {
        // Route to render all applications page (Controller handles fetching database rows)
        Route::get('/applications', [AdminController::class, 'application'])
            ->name('applications.index');

        // Member notification API
        Route::get('/api/member/notifications', [UserController::class, 'memberNotifications'])
            ->name('api.member.notifications');

        // Route to handle form submission and store application in database
        Route::post('/applications', [AdminController::class, 'store'])
            ->name('applications.store');

        // Approve / reject an application (used by the View drawer's Accept/Reject buttons)
        Route::post('/applications/{appId}/approve', [AdminController::class, 'approve'])
            ->name('applications.approve');

        Route::post('/applications/{appId}/reject', [AdminController::class, 'reject'])
            ->name('applications.reject');

        // Re-run the AI credit scan for a specific application (triggered from the detail modal)
        Route::get('/admin/applications/{id}', [AdminController::class, 'showApplication'])
            ->name('admin.applications.show');

        Route::post('/admin/applications/{id}/scan', [AdminController::class, 'triggerAiScan'])
            ->name('admin.applications.scan');

        Route::delete('/applications/{appId}', [AdminController::class, 'destroy'])
            ->name('applications.destroy');

        Route::post('/notifications/mark-read', [AdminController::class, 'markNotificationsRead'])
            ->name('notifications.markRead');

        Route::get('/active-members', [AdminController::class, 'borrowers'])
            ->name('active-members.index');

        Route::post('/active-members', [AdminController::class, 'storeBorrower'])
            ->name('active-members.store');

        Route::post('/active-members/{id}/approve', [AdminController::class, 'approveBorrower'])
            ->name('active-members.approve');

        Route::post('/active-members/{id}/reject', [AdminController::class, 'rejectBorrower'])
            ->name('active-members.reject');

        Route::delete('/active-members/{id}', [AdminController::class, 'destroyBorrower'])
            ->name('active-members.destroy');

        // Admin Users page — lists accounts with usertype = 'admin'
        Route::get('/admin/users', [AdminController::class, 'adminUsers'])
            ->name('admins.index');
        Route::post('/admin/users', [AdminController::class, 'storeAdmin'])
            ->name('admins.store');
        Route::put('/admin/users/{id}', [AdminController::class, 'updateAdmin'])
            ->name('admins.update');
        Route::delete('/admin/users/{id}', [AdminController::class, 'destroyAdmin'])
            ->name('admins.destroy');

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

        // Dashboard navigation routes
        Route::get('/disbursements', [AdminController::class, 'disbursements'])
            ->name('disbursements.index');
        Route::post('/admin/disbursements/process', [AdminController::class, 'processDisbursement'])
            ->name('disbursements.process');
        Route::get('/repayments', [AdminController::class, 'repayments'])
            ->name('repayments.index');
        Route::post('/admin/repayments', [AdminController::class, 'storeRepayment'])
            ->name('repayments.store');
        Route::get('/admin/credit-assessment', [AdminController::class, 'creditAssessment'])
            ->name('credit-assessment.index');
        Route::get('/admin/risk-flags', [AdminController::class, 'riskFlags'])
            ->name('risk-flags.index');
        Route::get('/admin/applications/{id}/amortization', [AdminController::class, 'amortizationSchedule'])
            ->name('admin.applications.amortization');
        Route::get('/admin/applications/{id}/amortization/pdf', [AdminController::class, 'exportAmortizationPdf'])
            ->name('admin.applications.amortization.pdf');
        Route::get('/admin/audit-logs', [AdminController::class, 'auditLogs'])
            ->name('admin.audit-logs');
        Route::get('/admin/settings', [AdminController::class, 'settings'])
            ->name('settings.index');
        Route::post('/admin/settings', [AdminController::class, 'updateSettings'])
            ->name('settings.update');
        Route::get('/admin/backup', [AdminController::class, 'backup'])
            ->name('admin.backup');
        Route::get('/admin/notifications-log', [AdminController::class, 'notificationLogs'])
            ->name('admin.notifications-log');
        Route::get('/reports', [AdminController::class, 'reports'])
            ->name('reports.index');
        Route::get('/reports/export-pdf', [AdminController::class, 'exportPdf'])
            ->name('reports.exportPdf');
        Route::get('/reports/export-excel', [AdminController::class, 'exportExcel'])
            ->name('reports.exportExcel');
    });
});

// Public routes - no auth required
Route::get('/api/member/{memberId}', [\App\Http\Controllers\MemberLookupController::class, 'show'])->name('member.lookup');

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
    request()->session()->regenerateToken();
    return redirect('/login');
})->name('exit');

Route::get('/faqs', function () {
    return view('home.faqs');
})->name('faqs');
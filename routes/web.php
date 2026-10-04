<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UserController;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use App\Models\Application;
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

    // Member area
    Route::prefix('user')->name('user.')->group(function () {
        Route::get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');

        Route::get('/loans/apply', [UserController::class, 'createApplication'])->name('loans.create');
        Route::post('/loans', [UserController::class, 'storeApplication'])->name('loans.store');
        Route::delete('/loans/{application}', [UserController::class, 'cancelApplication'])->name('loans.cancel');
    });

    // Route to render all applications page (Controller handles fetching database rows)
    Route::get('/applications', [AdminController::class, 'application'])
        ->name('applications.index');

    // Route to handle form submission and store application in database
    Route::post('/applications', [AdminController::class, 'store'])
        ->name('applications.store');

    // Approve / reject an application (used by the View drawer's Accept/Reject buttons)
    Route::post('/applications/{appId}/approve', [AdminController::class, 'approve'])
        ->name('applications.approve');

    Route::post('/applications/{appId}/reject', [AdminController::class, 'reject'])
        ->name('applications.reject');

    Route::delete('/applications/{appId}', [AdminController::class, 'destroy'])
        ->name('applications.destroy');

    Route::post('/notifications/mark-read', [AdminController::class, 'markNotificationsRead'])
        ->name('notifications.markRead');

    Route::get('/borrowers', [AdminController::class, 'borrowers'])
        ->name('borrowers.index');

    Route::post('/borrowers', [AdminController::class, 'storeBorrower'])
        ->name('borrowers.store');

    Route::post('/borrowers/{id}/approve', [AdminController::class, 'approveBorrower'])
        ->name('borrowers.approve');

    Route::post('/borrowers/{id}/reject', [AdminController::class, 'rejectBorrower'])
        ->name('borrowers.reject');

    Route::delete('/borrowers/{id}', [AdminController::class, 'destroyBorrower'])
        ->name('borrowers.destroy');

    // Admin Users page — lists accounts with usertype = 'admin'
    Route::get('/admin/users', [AdminController::class, 'adminUsers'])
        ->name('admins.index');

    // Cooperative membership applications (from the public form)
    Route::get('/memberships', [AdminController::class, 'memberships'])
        ->name('memberships.index');
    Route::post('/memberships/{id}/approve', [AdminController::class, 'approveMember'])
        ->name('memberships.approve');
    Route::post('/memberships/{id}/reject', [AdminController::class, 'rejectMember'])
        ->name('memberships.reject');
    Route::delete('/memberships/{id}', [AdminController::class, 'destroyMember'])
        ->name('memberships.destroy');
});

Route::get('/api/member/{memberId}', [\App\Http\Controllers\MemberLookupController::class, 'show'])->name('member.lookup');

// Cooperative membership application (guests only — for people who are not
// yet registered members). Kept separate from the regular register flow so
// the two paths stay easy to distinguish.
Route::get('/member-application', [\App\Http\Controllers\MemberApplicationController::class, 'create'])->name('member.applications.create');
Route::post('/member-application', [\App\Http\Controllers\MemberApplicationController::class, 'store'])->name('member.applications.store');
Route::get('/member-application/{id}', [\App\Http\Controllers\MemberApplicationController::class, 'show'])->name('member.applications.show');

Route::get('/', [AdminController::class, 'index']);

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

// Fixed /admin/data route
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/data', function () {
        $applications = Application::latest()->get();
        return view('admin.data', compact('applications'));
    });
});

Route::get('/exit', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
});

Route::get('/faqs', function () {
    return view('home.faqs');
})->name('faqs');
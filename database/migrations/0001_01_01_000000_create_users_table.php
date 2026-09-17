<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    // 1. Dashboard Route
    public function index()
    {
        if (Auth::check()) {
            $usertype = Auth::user()->usertype;

            if ($usertype === 'admin') {
                $applications = Application::latest()->get();
                // Total count of members currently marked as active borrowers.
                $activeBorrowersCount = User::where('borrower_status', 'Active')->count();

                return view('admin.index', compact('applications', 'activeBorrowersCount'));
            }

            return view('home.index');
        }

        return view('home.index');
    }

    // 2. All Applications Route
    public function application()
    {
        $applications = Application::latest()->get();
        return view('admin.application', compact('applications'));
    }

    // 3. Save Form Action to Database
    public function store(Request $request)
    {
        $validated = $request->validate([
            'applicant' => 'required|string|max:255',
            'loan_type' => 'required|string',
            'amount'    => 'required|numeric|min:0',
        ]);

        $lastId = Application::max('id') ?? 0;
        $appId = 'APP-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

        Application::create([
            'app_id'    => $appId,
            'applicant' => $validated['applicant'],
            'loan_type' => $validated['loan_type'],
            'amount'    => (float) $validated['amount'],
            'status'    => 'Pending',
            'ai_score'  => rand(70, 99),
        ]);

        return redirect()->route('applications.index')->with('success', 'Application submitted successfully!');
    }

    // 4. Approve an application (syncs borrower fields onto the matching user account)
    public function approve($appId)
    {
        $application = Application::where('app_id', $appId)->firstOrFail();
        $application->status = 'Approved';
        $application->save();

        // ─── FIND OR CREATE THE MATCHING USER ───
        // Applications are logged by admins with just a name, so there's no
        // guaranteed user_id to match on. We match by name, same as the old
        // Borrowers lookup did. If no account exists yet, one is created
        // with a placeholder email and a random password so they can be
        // given real credentials later.
        $borrower = User::where('name', $application->applicant)->first();

        if (!$borrower) {
            $borrower = User::create([
                'name'     => $application->applicant,
                'email'    => strtolower(str_replace(' ', '', $application->applicant)) . '@gmail.com',
                'password' => Hash::make(Str::random(16)),
                'usertype' => 'user',
            ]);
        }

        $borrower->monthly_income  = $borrower->monthly_income ?? 0.00;
        $borrower->ai_credit_score = $borrower->ai_credit_score ?? ($application->ai_score ?? rand(600, 750));
        $borrower->borrower_status = 'Active';
        $borrower->save();

        AppNotification::create([
            'app_id'    => $application->app_id,
            'applicant' => $application->applicant,
            'type'      => 'approved',
            'message'   => 'Application Approved',
        ]);

        return response()->json([
            'success' => true,
            'app_id'  => $application->app_id,
            'status'  => $application->status,
        ]);
    }

    // 5. Reject an application
    public function reject($appId)
    {
        $application = Application::where('app_id', $appId)->firstOrFail();
        $application->status = 'Rejected';
        $application->save();

        AppNotification::create([
            'app_id'    => $application->app_id,
            'applicant' => $application->applicant,
            'type'      => 'rejected',
            'message'   => 'Application Rejected',
        ]);

        return response()->json([
            'success' => true,
            'app_id'  => $application->app_id,
            'status'  => $application->status,
        ]);
    }

    // 6. Delete an application
    public function destroy($appId)
    {
        $application = Application::where('app_id', $appId)->firstOrFail();
        $applicantName = $application->applicant;

        // Delete the application
        $application->delete();

        // Delete matching notifications
        AppNotification::where('app_id', $appId)->delete();

        // Clear this person's borrower standing rather than deleting their
        // account outright — the account may still be a real login.
        User::where('name', $applicantName)->update([
            'borrower_status' => null,
        ]);

        return response()->json([
            'success' => true,
            'app_id'  => $appId,
        ]);
    }

    // 7. Mark all notifications as read
    public function markNotificationsRead()
    {
        AppNotification::whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    // 8. Logout Action
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    // 9. Borrowers Page Route — lists any user who has ever been marked as
    // a borrower (Active or Blacklisted), rather than everyone with an account.
    public function borrowers()
    {
        $borrowers = User::whereNotNull('borrower_status')->latest()->get();
        return view('admin.borrowers', compact('borrowers'));
    }

    // 10. Store New Borrower manually.
    // Creates a full user account, since borrower data now lives on users.
    public function storeBorrower(Request $request)
    {
        $validated = $request->validate([
            'full_name'       => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email',
            'phone'           => 'nullable|string|max:20',
            'monthly_income'  => 'required|numeric|min:0',
            'ai_credit_score' => 'nullable|integer',
            'status'          => 'required|string',
            'address'         => 'nullable|string',
        ]);

        User::create([
            'name'            => $validated['full_name'],
            'email'           => $validated['email'],
            'password'        => Hash::make(Str::random(16)),
            'usertype'        => 'user',
            'phone'           => $validated['phone'],
            'monthly_income'  => $validated['monthly_income'],
            'ai_credit_score' => $validated['ai_credit_score'] ?? rand(600, 750),
            'borrower_status' => $validated['status'],
            'address'         => $validated['address'],
        ]);

        return redirect()->route('borrowers.index')->with('success', 'Borrower added successfully!');
    }

    // 11. Approve Borrower
    public function approveBorrower($id)
    {
        $borrower = User::findOrFail($id);
        $borrower->borrower_status = 'Active';
        $borrower->save();

        return redirect()->back()->with('success', 'Borrower status updated to Active.');
    }

    // 12. Reject Borrower
    public function rejectBorrower($id)
    {
        $borrower = User::findOrFail($id);
        $borrower->borrower_status = 'Blacklisted';
        $borrower->save();

        return redirect()->back()->with('success', 'Borrower status updated to Blacklisted.');
    }

    // 13. Remove borrower standing.
    // IMPORTANT CHANGE: this used to hard-delete the Borrowers row, which
    // was a separate record from the login account. Now that the same
    // fields live on `users`, hard-deleting here would delete the actual
    // account. Instead this just clears the borrower fields and leaves the
    // account (and their loan_applications history) intact.
    public function destroyBorrower($id)
    {
        $borrower = User::findOrFail($id);
        $borrowerName = $borrower->name;

        $borrower->borrower_status = null;
        $borrower->monthly_income  = null;
        $borrower->ai_credit_score = null;
        $borrower->save();

        // Also clear the matching admin-logged applications, same as before.
        $applications = Application::where('applicant', $borrowerName)->get();
        foreach ($applications as $app) {
            AppNotification::where('app_id', $app->app_id)->delete();
            $app->delete();
        }

        return response()->json([
            'success' => true,
            'id'      => $id,
        ]);
    }
}
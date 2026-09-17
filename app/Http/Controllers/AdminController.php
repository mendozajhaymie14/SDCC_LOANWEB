<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AppNotification;
use App\Models\Borrowers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    // 1. Dashboard Route
    public function index()
    {
        if (Auth::check()) {
            $usertype = Auth::user()->usertype;

            if ($usertype === 'admin') {
                $applications = Application::latest()->get();
                // Fetch total count of active borrowers
                $activeBorrowersCount = Borrowers::where('status', 'Active')->count();

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

    // 4. Approve an application (Automatically syncs to Borrowers table)
    public function approve($appId)
    {
        $application = Application::where('app_id', $appId)->firstOrFail();
        $application->status = 'Approved';
        $application->save();

        // If this admin row came from a member's own submission, reflect
        // the decision back onto their loan_applications record too.
        if ($application->loan_application_id) {
            \App\Models\LoanApplication::where('id', $application->loan_application_id)->update([
                'status'       => 'approved',
                'reviewed_at'  => now(),
            ]);
        }

        // ─── AUTOMATICALLY CREATE / UPDATE BORROWER ───
        $borrower = Borrowers::where('full_name', $application->applicant)->first();

        if (!$borrower) {
            $lastId = Borrowers::max('id') ?? 0;
            $borrowerId = 'BOR-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

            Borrowers::create([
                'borrower_id'     => $borrowerId,
                'full_name'       => $application->applicant,
                'email'           => strtolower(str_replace(' ', '', $application->applicant)) . '@gmail.com',
                'phone_number'    => 'N/A',
                'monthly_income'  => 0.00,
                'ai_credit_score' => $application->ai_score ?? rand(600, 750),
                'status'          => 'Active',
                'address'         => null,
            ]);
        } else {
            $borrower->status = 'Active';
            $borrower->save();
        }

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

        if ($application->loan_application_id) {
            \App\Models\LoanApplication::where('id', $application->loan_application_id)->update([
                'status'      => 'rejected',
                'reviewed_at' => now(),
            ]);
        }

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

        // If this admin row came from a member's own submission, delete
        // their loan_applications record too, so it disappears from their
        // dashboard as well — not just from the admin queue.
        if ($application->loan_application_id) {
            \App\Models\LoanApplication::where('id', $application->loan_application_id)->delete();
        }

        // Delete the application
        $application->delete();

        // Delete matching notifications
        AppNotification::where('app_id', $appId)->delete();

        // Delete corresponding borrower by matching name
        Borrowers::where('full_name', $applicantName)->delete();

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

    // 9. Borrowers Page Route
    public function borrowers()
    {
        $borrowers = Borrowers::latest()->get();
        return view('admin.borrowers', compact('borrowers'));
    }

    // 10. Store New Borrower manually
    public function storeBorrower(Request $request)
    {
        $validated = $request->validate([
            'full_name'       => 'required|string|max:255',
            'email'           => 'required|email|unique:borrowers,email',
            'phone_number'    => 'nullable|string|max:20',
            'monthly_income'  => 'required|numeric|min:0',
            'ai_credit_score' => 'nullable|integer',
            'status'          => 'required|string',
            'address'         => 'nullable|string',
        ]);

        $lastId = Borrowers::max('id') ?? 0;
        $borrowerId = 'BOR-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

        Borrowers::create([
            'borrower_id'     => $borrowerId,
            'full_name'       => $validated['full_name'],
            'email'           => $validated['email'],
            'phone_number'    => $validated['phone_number'],
            'monthly_income'  => $validated['monthly_income'],
            'ai_credit_score' => $validated['ai_credit_score'] ?? rand(600, 750),
            'status'          => $validated['status'],
            'address'         => $validated['address'],
        ]);

        return redirect()->route('borrowers.index')->with('success', 'Borrower added successfully!');
    }

    // 11. Approve Borrower
    public function approveBorrower($id)
    {
        $borrower = Borrowers::findOrFail($id);
        $borrower->status = 'Active';
        $borrower->save();

        return redirect()->back()->with('success', 'Borrower status updated to Active.');
    }

    // 12. Reject Borrower
    public function rejectBorrower($id)
    {
        $borrower = Borrowers::findOrFail($id);
        $borrower->status = 'Blacklisted';
        $borrower->save();

        return redirect()->back()->with('success', 'Borrower status updated to Blacklisted.');
    }

    // 13. Delete Borrower
    public function destroyBorrower($id)
    {
        $borrower = Borrowers::findOrFail($id);
        $borrowerName = $borrower->full_name;

        // Delete the borrower
        $borrower->delete();

        // Delete corresponding applications by matching name
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
    

    public function adminUsers()
    {
        $admins = \App\Models\User::where('usertype', 'admin')->latest()->get();
        return view('admin.admins', compact('admins'));
    }
}
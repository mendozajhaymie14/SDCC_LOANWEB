<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AppNotification;
use App\Models\Borrowers;
use App\Models\CoopMember;
use App\Models\MemberApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    // 1. Dashboard Route
    public function index()
    {
        if (Auth::check()) {
            $usertype = Auth::user()->usertype;

            if ($usertype === 'admin') {
                $applications = Application::latest()->get();
                $activeBorrowersCount = Borrowers::where('status', 'Active')->count();
                $totalDisbursed = (float) Application::where('status', 'Approved')->sum('amount');
                $notifications = AppNotification::latest()->take(5)->get();
                $pendingApplicationsCount = Application::where('status', 'Pending')->count();

                return view('admin.index', compact('applications', 'activeBorrowersCount', 'totalDisbursed', 'notifications'));
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

        if ($application->loan_application_id) {
            \App\Models\LoanApplication::where('id', $application->loan_application_id)->update([
                'status'       => 'approved',
                'reviewed_at'  => now(),
            ]);
        }

        $borrower = Borrowers::withTrashed()->where('full_name', $application->applicant)->first();

        $loanApp = $application->loan_application_id
            ? \App\Models\LoanApplication::find($application->loan_application_id)
            : null;
        $monthlyIncome = $loanApp?->monthly_income ?? 0.00;
        $phone         = $loanApp?->contact_number ?? 'N/A';

        $rawScore = $application->ai_score;
        $aiCreditScore = is_numeric($rawScore)
            ? (int) round(300 + ($rawScore / 100) * 550)
            : rand(300, 850);

        if (!$borrower) {
            $lastId = Borrowers::withTrashed()->max('id') ?? 0;
            $borrowerId = 'BOR-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

            Borrowers::create([
                'borrower_id'     => $borrowerId,
                'full_name'       => $application->applicant,
                'email'           => strtolower(str_replace(' ', '', $application->applicant)) . '@gmail.com',
                'phone_number'    => $phone,
                'monthly_income'  => $monthlyIncome,
                'ai_credit_score' => $aiCreditScore,
                'status'          => 'Active',
                'address'         => null,
            ]);
        } else {
            $borrower->status          = 'Active';
            $borrower->monthly_income  = $monthlyIncome;
            $borrower->phone_number    = $phone;
            $borrower->ai_credit_score = $aiCreditScore;
            $borrower->restore();
            $borrower->save();
        }

        AppNotification::create([
            'app_id'    => $application->app_id,
            'applicant' => $application->applicant,
            'type'      => 'approved',
            'message'   => 'Application Approved',
        ]);

        return response()->json([
            'success'        => true,
            'app_id'         => $application->app_id,
            'status'         => $application->status,
            'total_disbursed' => (float) Application::where('status', 'Approved')->sum('amount'),
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
            'success'        => true,
            'app_id'         => $application->app_id,
            'status'         => $application->status,
            'total_disbursed' => (float) Application::where('status', 'Approved')->sum('amount'),
        ]);
    }

    // 6. Delete an application
    public function destroy($appId)
    {
        $application = Application::where('app_id', $appId)->firstOrFail();
        $applicantName = $application->applicant;

        if ($application->loan_application_id) {
            \App\Models\LoanApplication::where('id', $application->loan_application_id)->delete();
        }

        $application->delete();
        AppNotification::where('app_id', $appId)->delete();
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

    // 10. Active Members Page Route
    public function borrowers()
    {
        $members = CoopMember::with('user')->where('status', 'active')->latest()->get();
        return view('admin.activemember', compact('members'));
    }

    // 11. Store New Member manually
    public function storeBorrower(Request $request)
    {
        $validated = $request->validate([
            'member_id'     => 'required|string|max:255|unique:coop_members,member_id',
            'full_name'     => 'required|string|max:255',
            'email'         => 'required|email|unique:coop_members,email',
            'phone'         => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'is_registered' => 'boolean',
            'status'        => 'required|string|in:active,inactive,suspended',
        ]);

        $lastId = CoopMember::withTrashed()->max('id') ?? 0;
        $memberId = 'SDCC-' . date('Y') . '-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

        $coopMemberId = $validated['member_id'] ?? $memberId;

        $member = CoopMember::create([
            'member_id'      => $coopMemberId,
            'full_name'      => $validated['full_name'],
            'email'          => $validated['email'],
            'date_of_birth'  => $validated['date_of_birth'] ?? now(),
            'is_registered'  => $request->boolean('is_registered'),
            'status'         => $validated['status'] ?? 'active',
        ]);

        return redirect()->route('active-members.index')->with('success', 'Member added successfully!');
    }

    // 12. Activate Member
    public function approveBorrower($id)
    {
        $member = CoopMember::findOrFail($id);
        $member->status = 'active';
        $member->save();

        return redirect()->back()->with('success', 'Member status updated to Active.');
    }

    // 13. Suspend Member
    public function rejectBorrower($id)
    {
        $member = CoopMember::findOrFail($id);
        $member->status = 'inactive';
        $member->save();

        return redirect()->back()->with('success', 'Member status updated to Inactive.');
    }

    // 14. Delete Member
    public function destroyBorrower($id)
    {
        $member = CoopMember::findOrFail($id);

        $memberId = $member->member_id;

        $member->delete();

        return response()->json([
            'success' => true,
            'id'      => $id,
        ]);
    }

    // 14. Admin Users Page
    public function adminUsers()
    {
        $admins = User::where('usertype', 'admin')->latest()->get();
        return view('admin.admins', compact('admins'));
    }

    // 15. Store a new admin account
    public function storeAdmin(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'phone'    => 'nullable|string|max:20',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'phone'    => $validated['phone'] ?? null,
            'usertype' => 'admin',
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'success' => true,
            'id'      => $user->id,
        ]);
    }

    // 16. Update an admin account
    public function updateAdmin(Request $request, $id)
    {
        $admin = User::where('usertype', 'admin')->findOrFail($id);

        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        Validator::make(
            ['email' => $validated['email']],
            ['email' => 'required|email|max:255|unique:users,email,' . $admin->id]
        )->validated();

        $admin->name  = $validated['name'];
        $admin->email = $validated['email'];
        $admin->phone = $validated['phone'] ?? null;
        $admin->save();

        return response()->json([
            'success' => true,
            'id'      => $id,
        ]);
    }

    // 17. Delete an admin account
    public function destroyAdmin($id)
    {
        $admin = User::where('usertype', 'admin')->findOrFail($id);

        if ($admin->id === Auth::id()) {
            return response()->json(['success' => false, 'message' => 'You cannot delete your own account.'], 422);
        }

        $admin->delete();

        return response()->json([
            'success' => true,
            'id'      => $id,
        ]);
    }

    // 18. Membership Applications — List
    public function memberships()
    {
        $applications = MemberApplication::latest()->get();
        return view('admin.memberships', compact('applications'));
    }

    // 19. Approve a membership application
    public function approveMember($id)
    {
        $application = MemberApplication::findOrFail($id);

        $existing = CoopMember::where('email', $application->email)->first();

        // Resolve the user account this application belongs to. The
        // application may carry a user_id (logged-in applicant) or the
        // applicant may have registered as a user before submitting.
        $userId = $application->user_id;
        if (!$userId) {
            $existingUser = \App\Models\User::where('email', $application->email)->first();
            if ($existingUser) {
                $userId = $existingUser->id;
            }
        }

        if (!$existing) {
            $lastId = CoopMember::max('id') ?? 0;
            $memberId = 'SDCC-' . date('Y') . '-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

            $existing = CoopMember::create([
                'member_id'     => $memberId,
                'full_name'     => $application->fullName,
                'date_of_birth' => $application->birthdate,
                'email'         => $application->email,
                'is_registered' => (bool) $userId,
                'user_id'       => $userId,
            ]);
        } elseif ($application->user_id && !$existing->user_id) {
            // The member already existed (e.g. applied before registering),
            // but this application was submitted by a logged-in user — link
            // them so the dashboard recognizes the member as belonging to
            // this account.
            $existing->user_id = $application->user_id;
            $existing->save();
        }

        // If the applicant already has a user account, mark the member as
        // registered and mirror the link on the users table too. Without
        // this the approved member is invisible to the account that
        // submitted the application (UserController::dashboard resolves
        // `$user->coopMember` via users.coop_member_id).
        if ($userId) {
            $existing->is_registered = true;
            $existing->user_id = $userId;
            $existing->save();

            \App\Models\User::where('id', $userId)->update([
                'coop_member_id' => $existing->id,
            ]);
        }

        $application->status             = 'approved';
        $application->reviewed_at        = now();
        $application->reviewed_by        = Auth::id();
        $application->approved_member_id = $existing->id;
        $application->save();

        return response()->json([
            'success'   => true,
            'id'        => $id,
            'status'    => 'approved',
            'member_id' => $existing->member_id,
        ]);
    }

    // 20. Reject a membership application
    public function rejectMember($id)
    {
        $application = MemberApplication::findOrFail($id);

        $application->status      = 'rejected';
        $application->reviewed_at = now();
        $application->reviewed_by = Auth::id();
        $application->save();

        return response()->json([
            'success' => true,
            'id'      => $id,
            'status'  => 'rejected',
        ]);
    }

    // 21. Update a membership application
    public function updateMember(Request $request, $id)
    {
        $application = MemberApplication::findOrFail($id);

        $validated = $request->validate([
            'first_name'        => 'required|string|max:255',
            'middle_name'       => 'nullable|string|max:255',
            'surname'           => 'required|string|max:255',
            'email'             => 'required|email|max:255',
            'contact_number'    => 'nullable|string|max:20',
            'tin'               => 'nullable|string|max:20',
            'nationality'       => 'nullable|string|max:100',
            'place_of_birth'    => 'nullable|string|max:255',
            'gender'            => 'nullable|string|max:20',
            'occupation'        => 'nullable|string|max:255',
            'civil_status'      => 'nullable|string|max:50',
            'residency_type'    => 'nullable|string|max:50',
            'perm_house_no'     => 'nullable|string|max:50',
            'perm_street'       => 'nullable|string|max:255',
            'perm_barangay'     => 'nullable|string|max:255',
            'perm_municipality' => 'nullable|string|max:255',
            'perm_zip_code'     => 'nullable|string|max:20',
            'perm_stay_years'   => 'nullable|integer|min:0',
            'perm_stay_months'  => 'nullable|integer|min:0|max:11',
        ]);

        $application->surname           = $validated['surname'];
        $application->first_name        = $validated['first_name'];
        $application->middle_name       = $validated['middle_name'] ?? '';
        $application->email             = $validated['email'];
        $application->contact_number    = $validated['contact_number'] ?? null;
        $application->tin               = $validated['tin'] ?? null;
        $application->nationality        = $validated['nationality'] ?? null;
        $application->place_of_birth    = $validated['place_of_birth'] ?? null;
        $application->gender            = $validated['gender'] ?? null;
        $application->occupation        = $validated['occupation'] ?? null;
        $application->civil_status      = $validated['civil_status'] ?? null;
        $application->residency_type    = $validated['residency_type'] ?? null;
        $application->perm_house_no     = $validated['perm_house_no'] ?? null;
        $application->perm_street       = $validated['perm_street'] ?? null;
        $application->perm_barangay    = $validated['perm_barangay'] ?? null;
        $application->perm_municipality = $validated['perm_municipality'] ?? null;
        $application->perm_zip_code     = $validated['perm_zip_code'] ?? null;
        $application->perm_stay_years   = $validated['perm_stay_years'] ?? null;
        $application->perm_stay_months  = $validated['perm_stay_months'] ?? null;
        $application->save();

        return response()->json([
            'success' => true,
            'id'      => $id,
        ]);
    }

    // 22. Delete a membership application
    public function destroyMember($id)
    {
        $application = MemberApplication::findOrFail($id);

        if ($application->approved_member_id) {
            $member = CoopMember::find($application->approved_member_id);
            if ($member && !$member->is_registered && !$member->user_id) {
                $member->delete();
            }
        }

        $application->delete();

        return response()->json([
            'success' => true,
            'id'      => $id,
        ]);
    }

    

    // 23. Navigation Placeholder Routes
    public function repayments()        { return view('admin.index'); }
    public function creditAssessment()  { return view('admin.index'); }
    public function riskFlags()         { return view('admin.index'); }
    public function settings()          { return view('admin.index'); }
    public function reports()           { return view('admin.index'); }

    public function disbursements()
    {
        $applications = \App\Models\Application::whereIn('status', ['Approved', 'approved', 'Disbursed', 'disbursed'])
            ->latest()
            ->get();

        $disbursements = $applications->map(function ($app) {
            return (object)[
                'id'             => $app->id ?? 1,
                'reference_no'   => 'DISB-' . str_pad($app->id ?? 1, 5, '0', STR_PAD_LEFT),
                'applicant_name' => $app->applicant ?? $app->full_name ?? 'Applicant',
                'loan_type'      => $app->loan_type ?? 'Personal Loan',
                'amount'         => $app->amount ?? 0,
                'channel'        => 'Bank Transfer',
                'account_number' => '—',
                'notes'          => 'Approved loan application payout.',
                'status'         => 'Disbursed',
                'created_at'     => $app->created_at ?? now(),
            ];
        });

        return view('admin.disbursements', compact('disbursements'));
    }
}


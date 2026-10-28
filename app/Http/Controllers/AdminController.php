<?php

namespace App\Http\Controllers;

use App\Events\LoanApplicationApproved;
use App\Events\PaymentReceived;
use Carbon\Carbon;
use App\Models\Application;
use App\Models\AppNotification;
use App\Models\AppNotificationLog;
use App\Models\AuditLog;
use App\Models\Borrowers;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\MemberApplication;
use App\Models\Payment;
use App\Models\User;
use App\Support\Format;
use App\Support\LoanStatus;
use App\Support\Permissions;
use App\Services\AmortizationService;
use App\Services\LoanEvaluationService;
use App\Services\PaymentReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    // 1. Dashboard Route
    public function index()
    {
        if (Auth::check()) {
            // Staff panel: any role with VIEW_DASHBOARD that is not a portal member.
            // (Members are redirected to their own portal at the route level.)
            if (Permissions::has(Auth::user(), Permissions::VIEW_DASHBOARD)
                && ! Permissions::has(Auth::user(), Permissions::OWN_PORTAL)) {
                $applications = Application::with('loanApplication.user')->latest()->get();

                $totalDisbursed = (float) LoanApplication::whereIn('status', [LoanStatus::APPROVED, LoanStatus::DISBURSED])->sum('amount');
                $activeMembersCount = CoopMember::where('status', CoopMember::STATUS_ACTIVE)->count();
                $activeBorrowersCount = $activeMembersCount;

                $riskFlagsCount = Application::where('risk_level', 'High')->count();
                $flaggedApplications = Application::where('risk_level', 'High')->with('loanApplication.user')->latest()->get();

                $notifications = AppNotification::latest()->take(5)->get();
                $pendingApplicationsCount = Application::where('status', 'Pending')->count();

                $pendingApplications = Application::with('loanApplication.user')
                    ->where('status', 'Pending')
                    ->latest()
                    ->take(5)
                    ->get();

                $membershipApplicationsCount = MemberApplication::whereIn('status', ['Pending', 'pending'])->count();
                $totalRepayments = Payment::sum('amount_paid');

                $loanTypes = LoanApplication::selectRaw('loan_type, count(*) as count, sum(amount) as total_amount')
    ->where('status', 'approved')
    ->groupBy('loan_type')
    ->orderByDesc('total_amount')
    ->get();
    
                $recentRepayments = Payment::latest()->take(5)->get();

                $monthlyDisbursements = [];
                $monthlyRepayments = []; $monthLabels = [];
                for ($i = 5; $i >= 0; $i--) {
                    $monthStart = now()->subMonths($i)->startOfMonth();
                    $monthEnd = now()->subMonths($i)->endOfMonth(); $monthLabels[] = now()->subMonths($i)->format('M');

                    $disbursed = LoanApplication::whereIn('status', [LoanStatus::APPROVED, LoanStatus::DISBURSED])
                        ->whereBetween('created_at', [$monthStart, $monthEnd])
                        ->sum('amount');

                    $collected = Payment::whereBetween('payment_date', [$monthStart, $monthEnd])
                        ->sum('amount_paid');

                    $monthlyDisbursements[] = (float) $disbursed;
                    $monthlyRepayments[] = (float) $collected;
                }

                $lowRisk = Application::where('risk_level', 'Low')->count();
                $moderateRisk = Application::where('risk_level', 'Medium')->count();
                $highRisk = Application::where('risk_level', 'High')->count();

                $riskDistribution = [
                    'Low' => $lowRisk,
                    'Moderate' => $moderateRisk,
                    'High' => $highRisk,
                ];

                $auditLogCount = AuditLog::count();
                if ($auditLogCount === 0) {
                    $recentAuditLogs = collect([
                        (object) [
                            'created_at' => now()->subMinutes(5),
                            'admin_name' => 'System',
                            'action' => 'System Initialized',
                            'details' => 'San Dionisio Credit Cooperative web application installed and ready for use.'
                        ],
                        (object) [
                            'created_at' => now()->subMinutes(10),
                            'admin_name' => 'System',
                            'action' => 'Admin Logged In',
                            'details' => 'Administrator account initialized with default credentials.'
                        ],
                        (object) [
                            'created_at' => now()->subMinutes(15),
                            'admin_name' => 'System',
                            'action' => 'Database Migrated',
                            'details' => 'All database tables created and seeded with initial data.'
                        ]
                    ]);
                } else {
                    $recentAuditLogs = AuditLog::latest()->take(5)->get();
                }

                $emailsSentToday = AppNotificationLog::whereDate('created_at', today())
                    ->where('status', 'Sent')
                    ->count();

                return view('admin.index', compact(
                    'applications', 'activeBorrowersCount', 'activeMembersCount', 'totalDisbursed',
                    'notifications', 'pendingApplicationsCount',
                    'pendingApplications', 'loanTypes', 'recentRepayments',
                    'membershipApplicationsCount', 'totalRepayments',
                    'riskFlagsCount', 'flaggedApplications',
                    'monthlyDisbursements', 'monthlyRepayments','monthLabels', 'riskDistribution',
                    'recentAuditLogs', 'emailsSentToday'
                ));
            }

            return view('home.index');
        }

        return view('home.index');
    }

    // 2. All Applications Route
    public function application()
    {
        $applications = Application::with('loanApplication.user')->latest()->get();
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

        $application = Application::create([
            'app_id'    => $appId,
            'applicant' => $validated['applicant'],
            'loan_type' => $validated['loan_type'],
            'amount'    => (float) $validated['amount'],
            'status'    => 'Pending',
            'ai_score'  => null,
        ]);

        AuditLog::log('Application Created', "New application created: {$application->app_id} for {$application->applicant}");

        return redirect()->route('applications.index')->with('success', 'Application submitted successfully!');
    }

    // 4. Approve an application
    public function approve(int $appId)
    {
        // Pending applications may be soft-deleted; include trashed rows so the
        // act/reject flows work on them too.
        $application = Application::withTrashed()->where('id', $appId)->firstOrFail();

        // Accept approved_amount and reason from JSON or form requests
        $approvedAmount = request()->json('approved_amount') ?? request('approved_amount') ?? null;
        $reason = request()->json('reason') ?? request('reason') ?? null;

        // If approved amount exceeds recommended, require a reason
        if ($approvedAmount && $approvedAmount > ($application->recommended_amount ?? 0) && !$reason) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'A reason is required when approving above the recommended amount.',
                ], 400);
            }
            return back()->with('error', 'A reason is required when approving above the recommended amount.');
        }

        $disbursementDate = now();
        $totalPayable = $this->totalPayable((float) $application->amount, (int) ($application->term_months ?? 12));

        DB::transaction(function () use ($application, $disbursementDate, $totalPayable, $approvedAmount, $reason) {
            $application->status = 'Approved';
            $application->approved_amount = $approvedAmount ?? $application->recommended_amount ?? 0.00;
            $application->next_due_date = $disbursementDate->copy()->addMonth();
            $application->remaining_balance = $totalPayable;
            $application->save();

            if ($application->loan_application_id) {
                LoanApplication::where('id', $application->loan_application_id)->update([
                    'status'            => 'approved',
                    'reviewed_at'       => now(),
                    'remaining_balance' => $totalPayable,
                    'next_due_date'     => $disbursementDate->copy()->addMonth(),
                ]);
            }

            $loanApp = $application->loan_application_id
                ? LoanApplication::find($application->loan_application_id)
                : null;
            $monthlyIncome = $loanApp?->monthly_income ?? 0.00;
            $phone         = $loanApp?->contact_number ?? 'N/A';

            // Keep the score from the scorer; if no scan has run yet, leave it
            // null so the UI shows "pending scan" rather than inventing a number.
            $rawScore = $application->ai_score;
            $aiCreditScore = is_numeric($rawScore)
                ? (int) round(300 + ($rawScore / 100) * 550)
                : null;

            $borrower = Borrowers::withTrashed()->where('full_name', $application->applicant)->first();

            if (!$borrower) {
                $lastId = Borrowers::withTrashed()->max('id') ?? 0;
                $borrowerId = 'BOR-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

                Borrowers::create([
                    'borrower_id'     => $borrowerId,
                    'full_name'       => $application->applicant,
                    'email'           => $loanApp?->email ?? 'N/A',
                    'phone_number'    => $phone,
                    'monthly_income'  => $monthlyIncome,
                    // Column is NOT NULL with a 600 default; an explicit null
                    // would violate the constraint when no scan has run yet.
                    'ai_credit_score' => $aiCreditScore ?? 600,
                    'status'          => 'Active',
                    'address'         => $loanApp?->address ?? null,
                ]);
            } else {
                $borrower->status          = 'Active';
                $borrower->monthly_income  = $monthlyIncome;
                $borrower->phone_number    = $phone;
                if ($aiCreditScore !== null) {
                    $borrower->ai_credit_score = $aiCreditScore;
                }
                $borrower->restore();
                $borrower->save();
            }

            AppNotification::create([
                'app_id'    => $application->app_id,
                'applicant' => $application->applicant,
                'type'      => 'approved',
                'message'   => 'Application Approved',
            ]);

            AuditLog::log('Application Approved', "Application {$application->app_id} approved for {$application->applicant}. Approved amount: " . ($approvedAmount ?? $application->recommended_amount ?? 0) . ($reason ? ". Reason: $reason" : ''));
        });

        $loanApp = $application->loan_application_id
            ? LoanApplication::find($application->loan_application_id)
            : null;
        if ($loanApp) {
            DB::afterCommit(function () use ($loanApp) {
                LoanApplicationApproved::dispatch($loanApp);
            });
        }

        return response()->json([
            'success'        => true,
            'app_id'         => $application->app_id,
            'status'         => $application->status,
            'total_disbursed' => (float) LoanApplication::whereIn('status', [LoanStatus::APPROVED, LoanStatus::DISBURSED])->sum('amount'),
        ]);
    }

    public function totalPayable(float $principal, int $termMonths): float
    {
        return app(AmortizationService::class)->summary($principal, $termMonths)['total_paid'];
    }

    // 5. Reject an application
    public function reject(int $appId)
    {
        // Pending applications may be soft-deleted; include trashed rows so the
        // act/reject flows work on them too.
        $application = Application::withTrashed()->where('id', $appId)->firstOrFail();

        // Accept reason from JSON or form requests
        $rejectReason = request()->json('reason') ?? request('reason') ?? null;

        DB::transaction(function () use ($application, $rejectReason) {
            $application->status = 'Rejected';
            if ($rejectReason) {
                $application->admin_remarks = $rejectReason;
            }
            $application->save();

            if ($application->loan_application_id) {
                LoanApplication::where('id', $application->loan_application_id)->update([
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

            AuditLog::log('Application Rejected', "Application {$application->app_id} rejected for {$application->applicant}" . ($rejectReason ? ". Reason: $rejectReason" : ''));
        });

        return response()->json([
            'success'        => true,
            'app_id'         => $application->app_id,
            'status'         => $application->status,
            'total_disbursed' => (float) LoanApplication::whereIn('status', [LoanStatus::APPROVED, LoanStatus::DISBURSED])->sum('amount'),
        ]);
    }

    // 6. Delete an application
    public function destroy(int $appId)
    {
        $application = Application::where('id', $appId)->firstOrFail();
        $applicantName = $application->applicant;

        if ($application->loan_application_id) {
            LoanApplication::where('id', $application->loan_application_id)->delete();
        }

        $application->delete();
        AppNotification::where('app_id', $application->app_id)->delete();
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

    // 9. Audit Logs Route
    public function auditLogs(Request $request)
    {
        $query = AuditLog::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('admin_name', 'like', "%{$search}%")
                  ->orWhere('actor_role', 'like', "%{$search}%")
                  ->orWhere('details', 'like', "%{$search}%");
            });
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->input('start_date') . ' 00:00:00',
                $request->input('end_date') . ' 23:59:59'
            ]);
        }

        $auditLogs = $query->latest()->paginate(10);
        return view('admin.audit-logs', compact('auditLogs'));
    }

    // 10. Active Members Page Route
    public function borrowers()
    {
        $members = CoopMember::with('user')
            ->where('status', CoopMember::STATUS_ACTIVE)
            ->latest()
            ->get();

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
            'status'        => 'required|string|in:' . implode(',', CoopMember::STATUSES),
        ]);

        $lastId = CoopMember::withTrashed()->max('id') ?? 0;
        $memberId = 'SDCC-' . date('Y') . '-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);
        $coopMemberId = $validated['member_id'] ?? $memberId;

        CoopMember::create([
            'member_id'      => $coopMemberId,
            'full_name'      => $validated['full_name'],
            'email'          => $validated['email'],
            'contact_number' => $validated['phone'] ?? null,
            'date_of_birth'  => $validated['date_of_birth'] ?? now(),
            'is_registered'  => $request->boolean('is_registered'),
            'status'         => $validated['status'] ?? CoopMember::STATUS_ACTIVE,
        ]);

        return redirect()->route('active-members.index')->with('success', 'Member added successfully!');
    }

    // 12. Activate Member
    public function approveBorrower($id)
    {
        $member = CoopMember::findOrFail($id);
        $member->status = CoopMember::STATUS_ACTIVE;
        $member->save();

        return redirect()->back()->with('success', 'Member status updated to ' . CoopMember::STATUS_ACTIVE . '.');
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
        $id = $member->id;
        $member->delete();

        return response()->json(['success' => true, 'id' => $id]);
    }

    // 15. Staff Users Page — lists all four staff roles (admin, branch_manager,
    //     loan_officer, credit_investigator). Rows with a NULL role are included
    //     via the orWhereNull fallback so legacy users are still visible.
    public function adminUsers()
    {
        $admins = User::staff()
            ->latest()
            ->get();
        return view('admin.admins', compact('admins'));
    }

    // 16. Store a new staff account
    public function storeAdmin(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'phone'    => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'role'     => 'required|string|in:' . implode(',', Permissions::STAFF_ROLES),
        ]);

        // Password is optional — if the admin does not supply one, generate a
        // random 16-char string. The generated password is returned ONCE in the
        // JSON response so the admin can relay it to the new staff member.
        $password = $validated['password'] ?? Str::password(16, true, true, false);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'phone'    => $validated['phone'] ?? null,
            'password' => Hash::make($password),
            'usertype' => $validated['role'],
            'role'     => $validated['role'],
        ]);

        AuditLog::log(
            'Staff Account Created',
            "Staff account created: {$user->name} ({$user->email}) as {$user->role}.",
            $user->id
        );

        return response()->json([
            'success' => true,
            'id'    => $user->id,
            'role'  => $user->role,
            'password' => ($validated['password'] ?? null) ? null : $password,
        ]);
    }

    // 17. Update a staff account
    public function updateAdmin(Request $request, $id)
    {
        $user = User::staff()->findOrFail($id);

        if ($user->id === Auth::id()) {
            return response()->json(['success' => false, 'message' => 'You cannot edit your own account.'], 422);
        }

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255',
            'phone'    => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'role'     => 'required|string|in:' . implode(',', Permissions::STAFF_ROLES),
        ]);

        Validator::make(
            ['email' => $validated['email']],
            ['email' => 'required|email|max:255|unique:users,email,' . $user->id]
        )->validated();

        $oldRole = $user->role;

        $user->name  = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;
        // Both columns move together: a staff account is never a registered
        // non-member in either column.
        $user->role     = $validated['role'];
        $user->usertype = $validated['role'];

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        AuditLog::log(
            'Staff Account Updated',
            "Staff account updated: {$user->name} ({$user->email}).",
            $user->id,
            ['role' => $oldRole],
            ['role' => $validated['role']]
        );

        return response()->json(['success' => true, 'id' => $id]);
    }

    // 18. Delete a staff account
    public function destroyAdmin($id)
    {
        $user = User::staff()->findOrFail($id);

        if ($user->id === Auth::id()) {
            return response()->json(['success' => false, 'message' => 'You cannot delete your own account.'], 422);
        }

        $roleBefore = $user->role;
        $user->delete();

        AuditLog::log(
            'Staff Account Deleted',
            "Staff account deleted: {$user->name} ({$user->email}) role={$roleBefore}.",
            $user->id
        );

        return response()->json(['success' => true, 'id' => $id]);
    }

    // 19. Membership Applications — List
    public function memberships()
    {
        $applications = MemberApplication::latest()->get();
        return view('admin.memberships', compact('applications'));
    }

    // 20. Approve a membership application
    //
    // One transaction, one outcome: the cooperative membership exists, the
    // account is linked to it, the account's role becomes 'member', the
    // membership status is the canonical 'active', the audit trail records
    // it and the applicant is told in the app.
    public function approveMember($id)
    {
        $application = MemberApplication::findOrFail($id);

        $member = DB::transaction(function () use ($application) {
            $userId = $application->user_id;

            if (! $userId) {
                $existingUser = User::where('email', $application->email)->first();
                $userId = $existingUser?->id;
            }

            $coopMember = CoopMember::where('email', $application->email)
                ->lockForUpdate()
                ->first();

            if (! $coopMember) {
                $lastId = CoopMember::max('id') ?? 0;
                $memberId = 'SDCC-' . date('Y') . '-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

                $coopMember = CoopMember::create([
                    'member_id'      => $memberId,
                    'full_name'      => $application->fullName,
                    'date_of_birth'  => $application->birthdate ?? now(),
                    'email'          => $application->email,
                    'contact_number' => $application->contact_number,
                    'is_registered'  => (bool) $userId,
                    'user_id'        => $userId,
                    'status'         => CoopMember::STATUS_ACTIVE,
                ]);
            } else {
                $coopMember->status = CoopMember::STATUS_ACTIVE;

                if ($userId && ! $coopMember->user_id) {
                    $coopMember->user_id = $userId;
                }

                $coopMember->save();
            }

            if ($userId) {
                $coopMember->is_registered = true;
                $coopMember->user_id = $userId;
                $coopMember->save();

                // The account and the membership must agree from now on:
                // usertype 'member' means "linked to an active coop_members row".
                $user = User::where('id', $userId)->lockForUpdate()->first();

                if ($user) {
                    $user->coop_member_id = $coopMember->id;
                    // Both columns agree: a member has the 'member' role AND an
                    // active cooperative membership, the only way the portal can
                    // distinguish "linked member" from "registered non-member".
                    $user->role     = Permissions::ROLE_MEMBER;
                    $user->usertype = Permissions::ROLE_MEMBER;
                    $user->save();
                }
            }

            $application->status             = 'approved';
            $application->reviewed_at        = now();
            $application->reviewed_by        = Auth::id();
            $application->approved_member_id = $coopMember->id;
            $application->save();

            AuditLog::log(
                'Membership Approved',
                'Membership application #' . $application->id . ' approved. Cooperative member '
                . $coopMember->member_id . ' (' . $coopMember->full_name . ') is now active.',
                $userId
            );

            if ($userId) {
                AppNotification::create([
                    'user_id' => $userId,
                    'title'   => 'Membership approved',
                    'message' => 'Your cooperative membership application has been approved. You can now apply for a loan.',
                    'type'    => AppNotification::TYPE_MEMBERSHIP_APPROVED,
                ]);
            }

            return $coopMember;
        });

        return response()->json([
            'success'   => true,
            'id'        => $id,
            'status'    => 'approved',
            'member_id' => $member->member_id,
        ]);
    }

    // 21. Reject a membership application
    //
    // The account stays a registered non-member ('user'), so the applicant can
    // fix whatever was wrong and apply again.
    public function rejectMember($id)
    {
        $application = MemberApplication::findOrFail($id);

        DB::transaction(function () use ($application) {
            $application->status      = 'rejected';
            $application->reviewed_at = now();
            $application->reviewed_by = Auth::id();
            $application->save();

            $userId = $application->user_id
                ?: User::where('email', $application->email)->value('id');

            if ($userId) {
                // Not a member yet — keep the account as a registered non-member.
                User::where('id', $userId)
                    ->where(function ($query) {
                        $query->whereNull('coop_member_id')
                            ->orWhere('usertype', '!=', Permissions::ROLE_MEMBER);
                    })
                    ->update([
                        'usertype' => Permissions::ROLE_USER,
                        'role'     => Permissions::ROLE_USER,
                    ]);

                AppNotification::create([
                    'user_id' => $userId,
                    'title'   => 'Membership application declined',
                    'message' => 'Your cooperative membership application was not approved. You may apply again once you have corrected the details.',
                    'type'    => AppNotification::TYPE_MEMBERSHIP_REJECTED,
                ]);
            }

            AuditLog::log(
                'Membership Rejected',
                'Membership application #' . $application->id . ' rejected. The account stays a registered non-member.',
                $userId
            );
        });

        return response()->json(['success' => true, 'id' => $id, 'status' => 'rejected']);
    }

    // 22. Update a membership application
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
        $application->nationality       = $validated['nationality'] ?? null;
        $application->place_of_birth    = $validated['place_of_birth'] ?? null;
        $application->gender            = $validated['gender'] ?? null;
        $application->occupation        = $validated['occupation'] ?? null;
        $application->civil_status      = $validated['civil_status'] ?? null;
        $application->residency_type    = $validated['residency_type'] ?? null;
        $application->perm_house_no     = $validated['perm_house_no'] ?? null;
        $application->perm_street       = $validated['perm_street'] ?? null;
        $application->perm_barangay     = $validated['perm_barangay'] ?? null;
        $application->perm_municipality = $validated['perm_municipality'] ?? null;
        $application->perm_zip_code     = $validated['perm_zip_code'] ?? null;
        $application->perm_stay_years   = $validated['perm_stay_years'] ?? null;
        $application->perm_stay_months  = $validated['perm_stay_months'] ?? null;
        $application->save();

        return response()->json(['success' => true, 'id' => $id]);
    }

    // 23. Delete a membership application
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
        return response()->json(['success' => true, 'id' => $id]);
    }

    // 24. Navigation Routes
    public function repayments()
    {
        $repayments = Payment::with('loanApplication.user')->latest()->get();
        $totalRepayments = Payment::sum('amount_paid');
        $approvedLoans = Application::with('loanApplication.user')
            ->whereIn('status', [LoanStatus::APPROVED, LoanStatus::DISBURSED])
            ->where('remaining_balance', '>', 0)
            ->latest()
            ->get();
        return view('admin.repayments', compact('repayments', 'totalRepayments', 'approvedLoans'));
    }

    public function creditAssessment()
    {
        $applications = Application::with('loanApplication.user')->latest()->get();

        $evaluated = $applications->filter(fn($a) => $a->risk_score !== null);
        $totalEvaluated = $evaluated->count();
        $highGradeCount = $evaluated->filter(fn($a) => ($a->risk_score ?? 0) >= 70)->count();
        $highRiskCount = $evaluated->filter(fn($a) => ($a->risk_score ?? 0) < 50)->count();
        $highGradePct = $totalEvaluated > 0 ? round(($highGradeCount / $totalEvaluated) * 100) : 0;
        $highRiskPct = $totalEvaluated > 0 ? round(($highRiskCount / $totalEvaluated) * 100) : 0;

        return view('admin.credit-assessment', compact(
            'applications', 'totalEvaluated', 'highGradePct', 'highRiskPct'
        ));
    }

    public function riskFlags()
    {
        $flaggedApplications = Application::where('risk_level', 'High')
            ->with('loanApplication.user')
            ->latest()
            ->get();

        $flagReasons = [];
        foreach ($flaggedApplications as $app) {
            $loanApp = $app->loanApplication;
            $reasons = [];
            if ($loanApp && isset($loanApp->risk_factors)) {
                $riskFactors = is_array($loanApp->risk_factors) ? $loanApp->risk_factors : json_decode($loanApp->risk_factors, true);
                if (is_array($riskFactors) && !empty($riskFactors)) {
                    $reasons = array_column($riskFactors, 'reason',);
                    $reasons = array_map(function($item) {
                        return $item['reason'] ?? $item;
                    }, $riskFactors);
                }
            }
            $flagReasons[$app->id] = !empty($reasons) ? implode(', ', $reasons) : 'High risk factors detected';
        }

        $riskFlagsCount = count($flaggedApplications);
        return view('admin.risk-flags', compact('flaggedApplications', 'riskFlagsCount'))->with('flagReasons', $flagReasons);
    }

    public function settings()
    {
        $settings = [
            'cooperative_name'        => config('cooperative.name', 'San Dionisio Credit Cooperative'),
            'cooperative_email'       => config('cooperative.email', 'info@sandionisiocoop.com'),
            'cooperative_address'     => config('cooperative.address', 'San Dionisio, Parañaque City'),
            'cooperative_phone'       => config('cooperative.phone', '+63 2 8XXX XXXX'),
            'annual_interest_rate'    => config('loan_matrix.rate', 12),
            'default_loan_term'       => config('loan_matrix.default_term', 12),
            'penalty_rate'            => config('loan_matrix.penalty_rate', 5),
            'min_approval_score'      => config('loan_matrix.min_score', 50),
            'high_risk_threshold'     => config('loan_matrix.high_risk', 30),
            'grace_period_days'       => config('loan_matrix.grace_days', 7),
            'reminder_frequency'      => config('loan_matrix.reminder_freq', 'weekly'),
        ];

        $recentAuditLogs = AuditLog::latest()->take(5)->get();
        return view('admin.settings', compact('settings', 'recentAuditLogs'));
    }

    public function reports(Request $request)
    {
        view()->share('format', new \App\Support\Format());
        $filters = $this->buildReportFilters($request);

        // KPI cards (always show these, unaffected by report-type filters for now)
        $totalDisbursed = LoanApplication::whereIn('status', [LoanStatus::APPROVED, LoanStatus::DISBURSED])->sum('amount') ?? 0;
        $totalRepayments = Payment::sum('amount_paid') ?? 0;
        $activeMembersCount = CoopMember::where('status', CoopMember::STATUS_ACTIVE)->count() ?? 0;
        $pendingApplicationsCount = Application::where('status', 'Pending')->count() ?? 0;

        // Build the three report sections
        $loanSummary   = $this->loanSummaryData($filters);
        $approvedRecs  = $this->approvedLoanRecordsData($filters);
        $borrowerPerf  = $this->borrowerPerformanceData($filters);

        return view('admin.reports', compact(
            'totalDisbursed', 'totalRepayments', 'activeMembersCount', 'pendingApplicationsCount',
            'loanSummary', 'approvedRecs', 'borrowerPerf', 'filters'
        ));
    }

    // -----------------------------------------------------------------
    // Internal helpers for the three report types used by the page + exports
    // -----------------------------------------------------------------

    private function buildReportFilters(Request $request): array
    {
        $type       = $request->query('report_type', 'loan_summary');
        $startDate  = $request->query('start_date');
        $endDate    = $request->query('end_date');
        $status     = $request->query('status');

        $statusWhere = null;
        if ($status !== null && $status !== '') {
            $statusWhere = fn ($q) => $q->where('status', $status);
        }

        $dateWhere = null;
        if ($startDate && $endDate) {
            $dateWhere = fn ($q) => $q->whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($startDate) {
            $dateWhere = fn ($q) => $q->whereDate('created_at', '>=', $startDate);
        } elseif ($endDate) {
            $dateWhere = fn ($q) => $q->whereDate('created_at', '<=', $endDate);
        }

        return [
            'report_type' => $type,
            'start_date'  => $startDate,
            'end_date'    => $endDate,
            'status'      => $status,
            'status_closures' => ['status' => $statusWhere, 'date' => $dateWhere],
        ];
    }

    private function applyReportFilters($query, array $filters): mixed
    {
        $closures = $filters['status_closures'] ?? [];
        if ($closures['status'] !== null) {
            $query = $closures['status']($query);
        }
        if ($closures['date'] !== null) {
            $query = $closures['date']($query);
        }
        return $query;
    }

    /** ==================================================================
     *  Loan Summary report:
     *  counts and total amounts by status and by loan type, plus risk-level
     *  distribution (Low/Medium/High).
     *  ================================================================== */
    private function loanSummaryData(array $filters): array
    {
        $query = LoanApplication::query();

        $query = $this->applyReportFilters($query, $filters);

        $countByStatus = $query->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->toArray();

        $countByType = $query->selectRaw('loan_type, count(*) as cnt')
            ->groupBy('loan_type')
            ->get()
            ->keyBy('loan_type')
            ->toArray();

        $byStatus = $query->selectRaw('status, sum(amount) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->toArray();

        $byType = $query->selectRaw('loan_type, sum(amount) as total')
            ->groupBy('loan_type')
            ->get()
            ->keyBy('loan_type')
            ->toArray();

        // Normalise
        $statusTotalMap = ['pending' => 0, 'approved' => 0, 'disbursed' => 0, 'rejected' => 0, 'paid' => 0, 'flagged' => 0];
        $countStatusMap = ['pending' => 0, 'approved' => 0, 'disbursed' => 0, 'rejected' => 0, 'paid' => 0, 'flagged' => 0];

        foreach ($byStatus as $row) {
            $statusTotalMap[$row['status']] = (float) ($row['total'] ?? 0);
        }
        foreach ($countByStatus as $row) {
            $countStatusMap[$row['status']] = (int) ($row['cnt'] ?? 0);
        }

        $typeTotalMap = [];
        $countTypeMap = [];
        foreach ($byType as $row) {
            $typeTotalMap[$row['loan_type']] = (float) ($row['total'] ?? 0);
        }
        foreach ($countByType as $row) {
            $countTypeMap[$row['loan_type']] = (int) ($row['cnt'] ?? 0);
        }

        // Risk-level distribution
        $riskDist = $query->selectRaw('risk_level, count(*) as cnt')
            ->groupBy('risk_level')
            ->get()
            ->keyBy('risk_level')
            ->toArray();

        $riskMap = [];
        foreach ($riskDist as $row) {
            $riskMap[$row['risk_level']] = (int) ($row['cnt'] ?? 0);
        }

        return [
            'type'           => $filters['report_type'],
            'status_totals'  => $statusTotalMap,
            'by_status_counts' => $countStatusMap,
            'by_type'        => $typeTotalMap,
            'by_type_counts' => $countTypeMap,
            'risk_distribution' => $riskMap,
            'total_count'    => $query->count(),
            'total_amount'   => (float) $query->sum('amount'),
        ];
    }

    /** ==================================================================
     *  Approved Loan Records report:
     *  approved/disbursed loans with member, loan type, approved amount,
     *  term, risk level, recommended amount, approval date, and approver.
     *  ================================================================== */
    private function approvedLoanRecordsData(array $filters): \Illuminate\Support\Collection
    {
        $query = LoanApplication::whereIn('status', ['approved', 'disbursed'])
            ->with(['user.coopMember']);

        $query = $this->applyReportFilters($query, $filters);

        return $query->latest('reviewed_at')
            ->get()
            ->map(function ($app) {
                $member = $app->user?->coopMember;
                $approvedAmt = $app->approved_amount ?? $app->amount;

                return (object)[
                    'member_name'     => $member?->full_name ?? 'Applicant',
                    'member_id'       => $member?->id ?? null,
                    'loan_type'       => $app->loan_type,
                    'loan_type_label' => $app->getLoanTypeLabelAttribute(),
                    'approved_amount' => Format::money($approvedAmt),
                    'term_months'     => $app->term_months,
                    'risk_level'      => $app->risk_level,
                    'recommended_amt' => Format::money($app->recommended_amount ?? 0),
                    'approved_date'   => $app->reviewed_at ? $app->reviewed_at->format('M d, Y') : 'N/A',
                    'approved_by'     => $app->admin_remarks ?? 'N/A',
                    'app_id'          => $app->app_id,
                ];
            });
    }

    /** ==================================================================
     *  Borrower Performance report:
     *  per borrower: total loans, total paid, outstanding balance, on-time vs
     *  late payments, overdue count, and a simple performance label.
     *  ================================================================== */
    private function borrowerPerformanceData(array $filters): \Illuminate\Support\Collection
    {
        $query = LoanApplication::query();
        $query = $this->applyReportFilters($query, $filters);

        $memberIds = $query->pluck('user_id')->unique()->filter()->values();

        $members = CoopMember::whereIn('user_id', $memberIds)->get();

        return $members->map(function ($member) use ($filters) {
            $loanAppsQuery = LoanApplication::where('user_id', $member->user_id);
            $loanAppsQuery = $this->applyReportFilters($loanAppsQuery, $filters);
            $loanApps = $loanAppsQuery->get();

            $totalPaid = 0;
            $totalLoanAmount = 0;
            $onTime = 0;
            $late = 0;
            $overdue = 0;

            foreach ($loanApps as $la) {
                $totalLoanAmount += (float) ($la->amount ?? 0);

                $payments = Payment::where('loan_application_id', $la->id)
                    ->when($filters['start_date'] && $filters['end_date'], fn ($q) =>
                        $q->whereBetween('payment_date', [$filters['start_date'], $filters['end_date']])
                    )
                    ->get();

                foreach ($payments as $p) {
                    $totalPaid += (float) ($p->amount_paid ?? 0);
                    $dueDate = $la->next_due_date ?? null;
                    if ($dueDate && $p->payment_date) {
                        $diffDays = (int) $p->payment_date->diff($dueDate)->days;
                        if ($diffDays <= 0) {
                            $onTime++;
                        } else {
                            $late++;
                            if ($diffDays > 30) {
                                $overdue++;
                            }
                        }
                    }
                }
            }

            $outstanding = (float) $totalLoanAmount - (float) $totalPaid;

            $label = 'Good';
            if ($overdue > 0) {
                $label = 'Poor';
            } elseif ($late > $onTime && ($onTime + $late) > 0) {
                $label = 'Fair';
            } elseif ($late > 0) {
                $label = 'Fair';
            }

            return (object)[
                'member_name'   => $member->full_name ?? 'Unknown',
                'member_id'     => $member->id,
                'total_loans'   => $loanApps->count(),
                'total_paid'    => Format::money($totalPaid),
                'outstanding'   => Format::money($outstanding),
                'on_time_count' => $onTime,
                'late_count'    => $late,
                'overdue_count' => $overdue,
                'performance_label' => $label,
            ];
        });
    }

    public function disbursements()
    {
        $applications = Application::whereIn('status', ['Approved', 'approved', 'Disbursed', 'disbursed'])->latest()->get();

        $disbursements = $applications->map(function ($app) {
            return (object)[
                'id'             => $app->id ?? 1,
                'reference_no'   => 'DISB-' . str_pad($app->id ?? 1, 5, '0', STR_PAD_LEFT),
                'applicant_name' => $app->applicant ?? $app->full_name ?? 'Applicant',
                'loan_type'      => $app->loan_type ?? 'Personal Loan',
                'amount'         => $app->amount ?? 0,
                'status'         => 'Disbursed',
                'created_at'     => $app->created_at ?? now(),
            ];
        });

        return view('admin.disbursements', compact('disbursements'));
    }

    // 25. Process a disbursement
    public function processDisbursement(Request $request)
    {
        $validated = $request->validate([
            'application_id' => 'required|exists:applications,id',
            'disbursement_date' => 'nullable|date',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $application = Application::findOrFail($validated['application_id']);
        $disbursementDate = $validated['disbursement_date'] ?? now();
        $totalPayable = $this->totalPayable((float) $application->amount, (int) ($application->term_months ?? 12));

        $application->status = 'Disbursed';
        $application->disbursement_date = $disbursementDate;
        $application->reference_number = $validated['reference_number'] ?? null;
        $application->next_due_date = Carbon::parse($disbursementDate)->copy()->addMonth();
        $application->remaining_balance = $totalPayable;
        $application->save();

        if ($application->loan_application_id) {
            LoanApplication::where('id', $application->loan_application_id)->update([
                'status'            => 'disbursed',
                'reviewed_at'       => now(),
                'remaining_balance' => $totalPayable,
                'next_due_date'     => Carbon::parse($disbursementDate)->copy()->addMonth(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Disbursement processed successfully.',
            'application_id' => $application->app_id,
        ]);
    }

    // 26. Store a repayment
    public function storeRepayment(Request $request)
    {
        $validated = $request->validate([
            'application_id' => 'required|exists:applications,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|max:100',
            'reference_no' => 'nullable|string|max:255',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $application = Application::where('id', $validated['application_id'])
            ->lockForUpdate()
            ->firstOrFail();

        // Only allow payments on approved/disbursed loans
        if (!LoanStatus::isApproved($application->status) && !LoanStatus::isDisbursed($application->status)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Repayments can only be made on approved or disbursed loans.',
                ], 422);
            }
            return back()->with('error', 'Repayments can only be made on approved or disbursed loans.');
        }

        $amount = (float) $validated['amount'];
        $remainingBalance = (float) ($application->remaining_balance ?? 0);

        // Reject overpayment
        if ($amount > $remainingBalance) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount exceeds remaining balance.',
                ], 422);
            }
            return back()->with('error', 'Payment amount exceeds remaining balance.');
        }

        // Reject duplicate reference number for same loan
        if (!empty($validated['reference_no'])) {
            $exists = Payment::where('loan_application_id', $application->loan_application_id)
                ->where('reference_no', $validated['reference_no'])
                ->exists();
            if ($exists) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A payment with this reference number already exists for this loan.',
                    ], 422);
                }
                return back()->with('error', 'A payment with this reference number already exists for this loan.');
            }
        }

        DB::transaction(function () use ($application, $validated, $remainingBalance) {
            $newBalance = $remainingBalance - (float) $validated['amount'];

            $application->remaining_balance = $newBalance;
            $application->save();

            if ($application->loan_application_id) {
                LoanApplication::where('id', $application->loan_application_id)->update([
                    'remaining_balance' => $newBalance,
                ]);
            }

            // Update status if balance reaches zero
            if (bccomp((string) $newBalance, '0', 2) <= 0) {
                $application->status = LoanStatus::write(LoanStatus::PAID, 'applications');
                $application->next_due_date = null;
                $application->save();

                if ($application->loan_application_id) {
                    LoanApplication::where('id', $application->loan_application_id)->update([
                        'status'            => LoanStatus::write(LoanStatus::PAID),
                        'next_due_date'     => null,
                    ]);
                }
            } else {
                // Advance next_due_date by one month
                $nextDue = $application->next_due_date ?? now()->endOfMonth();
                $newDueDate = Carbon::parse($nextDue)->addMonth();
                $application->next_due_date = $newDueDate;
                $application->save();

                if ($application->loan_application_id) {
                    LoanApplication::where('id', $application->loan_application_id)->update([
                        'next_due_date'     => $newDueDate,
                    ]);
                }
            }

            // Create Payment record
            Payment::create([
                'loan_application_id' => $application->loan_application_id,
                'user_id'             => $application->user_id,
                'amount_paid'         => (float) $validated['amount'],
                'remaining_balance_after' => $newBalance,
                'reference_no'        => $validated['reference_no'] ?? null,
                'payment_date'        => $validated['payment_date'] ?? now(),
                'payment_method'      => $validated['payment_method'] ?? null,
                'notes'               => $validated['notes'] ?? null,
                'encoded_by'          => Auth::id(),
            ]);

            AuditLog::log('Payment Recorded', "Repayment of ₱{$validated['amount']} recorded for {$application->app_id}");
        });

        // Dispatch event after transaction commits
        if ($application->loan_application_id) {
            DB::afterCommit(function () use ($application) {
                PaymentReceived::dispatch(
                    Payment::where('loan_application_id', $application->loan_application_id)
                        ->latest()
                        ->first()
                );
            });
        }

        return $request->expectsJson()
            ? response()->json([
                'success' => true,
                'message' => 'Repayment recorded successfully.',
                'remaining_balance' => (float) ($application->remaining_balance ?? 0),
            ])
            : redirect()->route('repayments.index')->with('success', 'Repayment recorded successfully.');
    }

    // 27. Delete a repayment record
    public function destroyRepayment(int $id)
    {
        $repayment = Payment::findOrFail($id);
        $repayment->delete();

        return response()->json(['success' => true]);
    }

    // 28. Update an existing repayment record
    public function updateRepayment(Request $request, int $id)
    {
        $repayment = Payment::findOrFail($id);

        $validated = $request->validate([
            'amount'           => 'required|numeric|min:0.01',
            'payment_method'   => 'nullable|string|max:100',
            'reference_no'    => 'nullable|string|max:255',
            'payment_date'    => 'nullable|date',
            'notes'           => 'nullable|string|max:500',
        ]);

        $repayment->update([
            'amount_paid'      => (float) $validated['amount'],
            'payment_method'   => $validated['payment_method'] ?? null,
            'reference_no'    => $validated['reference_no'] ?? null,
            'payment_date'    => $validated['payment_date'] ?? null,
            'notes'           => $validated['notes'] ?? null,
        ]);

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => 'Repayment updated successfully.'])
            : redirect()->route('repayments.index')->with('success', 'Repayment updated successfully.');
    }

    // 28. Update application settings
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'cooperative_name' => 'nullable|string|max:255',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
        ]);

        foreach ($validated as $key => $value) {
            if ($value !== null) {
                Application::where('app_id', 'settings')->update([$key => $value]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Settings updated successfully.']);
    }

    // 28. Export reports to PDF
    public function exportPdf(Request $request)
    {
        $filters = $this->buildReportFilters($request);
        $type = $filters['report_type'];

        $data = match ($type) {
            'loan_summary'         => $this->loanSummaryData($filters),
            'approved_records'     => $this->approvedLoanRecordsData($filters),
            'borrower_performance' => $this->borrowerPerformanceData($filters),
            default                => ['type' => $type, 'data' => Application::latest()->get()],
        };

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.report-pdf', [
            'data'      => $data,
            'type'      => $type,
            'filters'   => $filters,
            'generatedAt' => now(),
        ]);

        return $pdf->download("report_{$type}_" . now()->format('Y-m-d') . '.pdf');
    }

    // 29. Export reports to Excel (real .xlsx via phpoffice/phpspreadsheet)
    public function exportExcel(Request $request)
    {
        $filters = $this->buildReportFilters($request);
        $type = $filters['report_type'];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if ($type === 'loan_summary') {
            $data = $this->loanSummaryData($filters);

            $sheet->setCellValue('A1', 'Loan Summary Report');
            $sheet->setCellValue('A2', 'Generated: ' . now()->format('Y-m-d H:i:s'));

            $sheet->setCellValue('A4', 'By Status');
            $sheet->setCellValue('A5', 'Status');
            $sheet->setCellValue('B5', 'Count');
            $sheet->setCellValue('C5', 'Total Amount');
            $row = 6;
            foreach ($data['status_totals'] as $status => $total) {
                $sheet->setCellValue('A' . $row, ucfirst($status));
                $sheet->setCellValue('B' . $row, $data['by_status_counts'][$status] ?? 0);
                $sheet->setCellValue('C' . $row, (float) $total);
                $row++;
            }
            $sheet->setCellValue('A' . $row, 'Total');
            $sheet->setCellValue('B' . $row, $data['total_count']);
            $sheet->setCellValue('C' . $row, (float) $data['total_amount']);
            $row += 2;

            $sheet->setCellValue('A' . $row, 'By Loan Type');
            $row++;
            $sheet->setCellValue('A' . $row, 'Loan Type');
            $sheet->setCellValue('B' . $row, 'Count');
            $sheet->setCellValue('C' . $row, 'Total Amount');
            $row++;
            foreach ($data['by_type'] as $loanType => $total) {
                $sheet->setCellValue('A' . $row, ucfirst($loanType));
                $sheet->setCellValue('B' . $row, $data['by_type_counts'][$loanType] ?? 0);
                $sheet->setCellValue('C' . $row, (float) $total);
                $row++;
            }
            $row++;

            $sheet->setCellValue('A' . $row, 'Risk Distribution');
            $row++;
            $sheet->setCellValue('A' . $row, 'Risk Level');
            $sheet->setCellValue('B' . $row, 'Count');
            $sheet->setCellValue('C' . $row, 'Percentage');
            $row++;
            foreach (['Low', 'Medium', 'High'] as $level) {
                $count = $data['risk_distribution'][$level] ?? 0;
                $pct = $data['total_count'] > 0 ? round($count / $data['total_count'] * 100, 1) : 0;
                $sheet->setCellValue('A' . $row, $level);
                $sheet->setCellValue('B' . $row, $count);
                $sheet->setCellValue('C' . $row, $pct . '%');
                $row++;
            }
        } elseif ($type === 'approved_records') {
            $rows = $this->approvedLoanRecordsData($filters);

            $sheet->setCellValue('A1', 'Approved Loan Records');
            $sheet->setCellValue('A2', 'Generated: ' . now()->format('Y-m-d H:i:s'));

            $headers = ['App ID', 'Member', 'Loan Type', 'Approved Amount', 'Term (mo)', 'Risk Level', 'Recommended Amount', 'Approved Date', 'Approved By'];
            foreach ($headers as $i => $h) {
                $sheet->setCellValue(chr(65 + $i) . '4', $h);
            }

            $row = 5;
            foreach ($rows as $app) {
                $sheet->setCellValue('A' . $row, $app->app_id ?? '');
                $sheet->setCellValue('B' . $row, $app->member_name ?? 'N/A');
                $sheet->setCellValue('C' . $row, $app->loan_type_label ?? 'N/A');
                $sheet->setCellValue('D' . $row, $app->approved_amount ?? '0');
                $sheet->setCellValue('E' . $row, $app->term_months ?? 0);
                $sheet->setCellValue('F' . $row, $app->risk_level ?? 'N/A');
                $sheet->setCellValue('G' . $row, $app->recommended_amt ?? '0');
                $sheet->setCellValue('H' . $row, $app->approved_date ?? 'N/A');
                $sheet->setCellValue('I' . $row, $app->approved_by ?? 'N/A');
                $row++;
            }
        } elseif ($type === 'borrower_performance') {
            $rows = $this->borrowerPerformanceData($filters);

            $sheet->setCellValue('A1', 'Borrower Performance Report');
            $sheet->setCellValue('A2', 'Generated: ' . now()->format('Y-m-d H:i:s'));

            $headers = ['Member', 'Total Loans', 'Total Paid', 'Outstanding', 'On-time', 'Late', 'Overdue', 'Performance'];
            foreach ($headers as $i => $h) {
                $sheet->setCellValue(chr(65 + $i) . '4', $h);
            }

            $row = 5;
            foreach ($rows as $app) {
                $sheet->setCellValue('A' . $row, $app->member_name ?? 'N/A');
                $sheet->setCellValue('B' . $row, $app->total_loans ?? 0);
                $sheet->setCellValue('C' . $row, $app->total_paid ?? '0');
                $sheet->setCellValue('D' . $row, $app->outstanding ?? '0');
                $sheet->setCellValue('E' . $row, $app->on_time_count ?? 0);
                $sheet->setCellValue('F' . $row, $app->late_count ?? 0);
                $sheet->setCellValue('G' . $row, $app->overdue_count ?? 0);
                $sheet->setCellValue('H' . $row, $app->performance_label ?? 'N/A');
                $row++;
            }
        } else {
            $sheet->setCellValue('A1', 'Report: ' . $type);
        }

        $filename = 'report_' . $type . '_' . now()->format('Y-m-d') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response($writer->save('php://output'), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function notificationLogs()
    {
        $logs = AppNotificationLog::latest()
            ->when(request('search'), fn ($q, $s) => $q->where('subject', 'like', "%{$s}%")->orWhere('recipient_email', 'like', "%{$s}%"))
            ->when(request('status'), fn ($q, $s) => $q->where('status', $s))
            ->when(request('start_date'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when(request('end_date'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->paginate(20);

        return view('admin.notifications-log', compact('logs'));
    }

    public function showApplication($id)
    {
        $application = Application::where('app_id', $id)->first()
            ?? Application::where('id', (int) $id)->first()
            ?? Application::where('loan_application_id', (int) $id)->first();

        if (!$application) {
            return response()->json(['success' => false, 'message' => 'Application not found.'], 404);
        }

        $loanApp = $application->loan_application_id
            ? LoanApplication::find($application->loan_application_id)
            : null;

        return view('admin.application-detail', compact('application', 'loanApp'));
    }

    public function applicationData($id)
    {
        // Pending applications may be soft-deleted in the audit trail, so
        // include trashed rows to ensure the modal can still display them.
        $application = Application::withTrashed()->where('app_id', $id)->first()
            ?? Application::withTrashed()->where('id', (int) $id)->first();

        if (!$application) {
            return response()->json(['success' => false, 'message' => 'Application not found.'], 404);
        }

        $loanApp = $application->loan_application_id
            ? LoanApplication::find($application->loan_application_id)
            : null;

        // Uploaded documents (stored as a list of public-disk paths)
        $documents = [];
        if ($loanApp && !empty($loanApp->documents)) {
            $documents = is_array($loanApp->documents)
                ? $loanApp->documents
                : (json_decode($loanApp->documents, true) ?: []);
        }
        $documents = array_values(array_filter($documents));

        // Risk factors may be a JSON string or an array; always return plain-text reasons too
        $riskFactors = $application->risk_factors;
        if (is_string($riskFactors)) {
            $riskFactors = json_decode($riskFactors, true);
        }
        if (!is_array($riskFactors)) {
            $riskFactors = [];
        }
        $reasons = collect($riskFactors)->map(function ($item) {
            if (is_array($item)) {
                return $item['reason'] ?? $item['message'] ?? null;
            }
            return is_string($item) ? $item : null;
        })->filter()->values()->all();

        $lastScanned = $application->last_scanned_at ?? null;

        return response()->json([
            'success' => true,
            'application' => [
                'id' => $application->id,
                'app_id' => $application->app_id,
                'applicant' => $application->applicant,
                'loan_type' => $application->loan_type,
                'amount' => (float) $application->amount,
                'term_months' => (int) ($application->term_months ?? 12),
                'status' => $application->status,
                'recommended_amount' => $application->recommended_amount ? (float) $application->recommended_amount : null,
                'risk_level' => $application->risk_level ?? 'None',
                'risk_score' => $application->risk_score ?? null,
                'risk_factors' => $riskFactors,
                'reasons' => $reasons,
                'documents' => $documents,
                'ai_score' => $application->ai_score ?? null,
                'ai_analysis_note' => $application->ai_analysis_note,
                'has_risk_flags' => $application->has_risk_flags ?? false,
                'document_processing_status' => $loanApp?->document_processing_status ?? 'pending',
                'last_scanned_at' => $lastScanned ? \Carbon\Carbon::parse($lastScanned)->toIso8601String() : null,
                'full_name' => $loanApp?->fullName() ?? ($application->applicant ?? 'Applicant'),
                'monthly_income' => $loanApp ? (float) $loanApp->monthly_income : 0,
                'existing_monthly_obligations' => $loanApp ? (float) $loanApp->existing_monthly_obligations : 0,
                'share_capital' => $loanApp ? (float) $loanApp->share_capital : 0,
            ],
        ]);
    }

    public function amortizationSchedule($id)
    {
        $application = Application::where('app_id', $id)->first()
            ?? Application::where('id', $id)->first()
            ?? Application::where('loan_application_id', $id)->first();

        if (!$application) {
            return redirect()->back()->with('error', 'Application not found.');
        }

        $loanApp = $application->loan_application_id
            ? LoanApplication::find($application->loan_application_id)
            : null;

        $principal = (float) ($application->amount ?? 0);
        $termMonths = (int) ($loanApp?->term_months ?? 12);
        $rate = (float) config('loan_matrix.rate', 0.12);

        $service = app(\App\Services\AmortizationService::class);
        $schedule = $service->schedule($principal, $termMonths, $rate);
        $summary = $service->summary($principal, $termMonths, $rate);

        $borrower = Borrowers::where('full_name', $application->applicant)->first();
        $riskMetrics = $this->computeRiskMetrics($application, $loanApp);

        return view('admin.amortization', compact('application', 'loanApp', 'schedule', 'summary', 'borrower', 'riskMetrics'));
    }

    public function exportAmortizationPdf($id)
    {
        $application = Application::where('app_id', $id)->first()
            ?? Application::where('id', $id)->first()
            ?? Application::where('loan_application_id', $id)->first();

        if (!$application) {
            return redirect()->back()->with('error', 'Application not found.');
        }

        $loanApp = $application->loan_application_id
            ? LoanApplication::find($application->loan_application_id)
            : null;

        $principal = (float) ($application->amount ?? 0);
        $termMonths = (int) ($loanApp?->term_months ?? 12);
        $rate = (float) config('loan_matrix.rate', 0.12);

        $service = app(\App\Services\AmortizationService::class);
        $schedule = $service->schedule($principal, $termMonths, $rate);
        $summary = $service->summary($principal, $termMonths, $rate);

        $html = view('admin.amortization-pdf', compact('application', 'loanApp', 'schedule', 'summary'))->render();

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'inline; filename="amortization-' . $application->app_id . '.html"',
        ]);
    }

    public function backup()
    {
        $driver = DB::connection()->getDriverName();

        // Table list: portable across MySQL and SQLite (the test DB is SQLite).
        $tables = $driver === 'mysql'
            ? collect(DB::select('SHOW TABLES'))->map(fn($r) => (string) reset($r))->all()
            : collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
                ->map(fn($r) => (string) $r->name)->all();

        $dump = "-- San Dionisio Credit Cooperative Database Backup\n";
        $dump .= "-- Generated: " . now()->format('Y-m-d H:i:s') . "\n\n";

        foreach ($tables as $tableName) {
            $dump .= "-- Table: {$tableName}\n";
            $dump .= "DROP TABLE IF EXISTS `{$tableName}`;\n";

            $createTable = $driver === 'mysql'
                ? DB::select("SHOW CREATE TABLE `{$tableName}`")[0]->{'Create Table'}
                : (DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?", [$tableName])[0]->sql ?? '');

            if ($createTable !== '') {
                $dump .= $createTable . ";\n\n";
            }

            $rows = DB::select("SELECT * FROM `{$tableName}`");
            if (!empty($rows)) {
                $dump .= "INSERT INTO `{$tableName}` VALUES\n";
                $values = [];
                foreach ($rows as $row) {
                    $rowValues = [];
                    foreach ((array) $row as $value) {
                        $rowValues[] = $value === null ? 'NULL' : "'" . str_replace("'", "\\'", $value) . "'";
                    }
                    $values[] = '(' . implode(', ', $rowValues) . ')';
                }
                $dump .= implode(",\n", $values) . ";\n\n";
            }
        }

        $filename = 'scc_backup_' . now()->format('Y-m-d_H-i-s') . '.sql';

        return response($dump, 200, [
            'Content-Type' => 'application/x-sql',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    protected function computeRiskMetrics($application,$loanApp): array
    {
        $monthlyIncome = (float) ($loanApp?->monthly_income ?? 0);
        $amount = (float) ($application?->amount ?? 0);
        $termMonths = (int) ($loanApp?->term_months ?? 12);
        $shareCapital = (float) ($loanApp?->share_capital ?? 0);
        $savings = (float) ($loanApp?->savings ?? 0);

        // Delegate EMI calculation to AmortizationService
        $emi = $amount > 0 && $termMonths > 0 ? app(AmortizationService::class)->emi($amount, $termMonths) : 0;
        $dti = $monthlyIncome > 0 ? round(($emi / $monthlyIncome) * 100, 2) : null;
        $savingsPool = $shareCapital + $savings;
        $savingsToLoan = $amount > 0 ? round(($savingsPool / $amount) * 100, 2) : null;

        $delinquencyStatus = 'Clean';
        $delinquencyFlagged = false;
        if ($loanApp) {
            $latePayments = Payment::where('loan_application_id', $loanApp->id)
                ->where('payment_date', '<', now()->subDays(30))
                ->count();
            $delinquencyFlagged = $latePayments > 0;
            $delinquencyStatus = $delinquencyFlagged ? 'Flagged (' . $latePayments . ' late payment(s))' : 'Clean';
        }

        $managerOverride = $application?->ai_analysis_note ?? null;
        $overrideReason = ($managerOverride && str_contains(strtolower($managerOverride), 'override')) ? $managerOverride : null;

        return [
            'dti'                 => $dti,
            'savings_to_loan'     => $savingsToLoan,
            'delinquency_status'  => $delinquencyStatus,
            'delinquency_flagged' => $delinquencyFlagged,
            'override_reason'     => $overrideReason,
        ];
    }

    /**
     * Scan uploaded documents with Gemini and update the AI score via CreditScoringService.
     */
    public function triggerAiScan($id)
    {
        $application = Application::where('app_id',$id)->first()
            ?? Application::where('id', $id)->first();

        if (!$application) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Application record not found.'], 404);
            }
            return back()->with('error', 'Application record not found.');
        }

        $loanApp =$application->loan_application_id
            ? LoanApplication::find($application->loan_application_id)
            : null;

        try {
            $scorer = app(\App\Services\CreditScoringService::class);

            $documents = [];
            if ($loanApp && !empty($loanApp->documents)) {
                $documents = is_array($loanApp->documents) ? $loanApp->documents : json_decode($loanApp->documents, true);
            } elseif (!empty($application->documents)) {
                $documents = is_array($application->documents) ? $application->documents : json_decode($application->documents, true);
            }

            $targetModel = $loanApp ?? $application;
            $result =$scorer->evaluateAndScan($targetModel,$documents);

            $score =$result['score'];
            $analysisNote =$result['analysis_note'];
            $hasRiskFlags =$result['has_risk_flags'];
            $riskLevel =$result['risk_level'] ?? 'None';
            $riskFactors =$result['risk_factors'] ?? [];
            $recommendedAmount =$result['recommended_amount'] ?? 0.0;

            $application->ai_score =$score;
            $application->ai_analysis_note =$analysisNote;
            $application->risk_level =$riskLevel;
            $application->risk_score =$score;
            $application->risk_factors = json_encode($riskFactors);
            $application->recommended_amount =$recommendedAmount;
            if (Schema::hasColumn('applications', 'last_scanned_at')) {
                $application->last_scanned_at = now();
            }
            if (Schema::hasColumn('applications', 'has_risk_flags')) {
                $application->has_risk_flags =$hasRiskFlags;
            }
            $application->save();

            if ($loanApp) {$loanApp->update([
                    'ai_score'                   => $score,
                    'ai_analysis_notes'          => $analysisNote,
                    'document_processing_status' => $hasRiskFlags ? 'flagged' : 'completed',
                    'risk_level'                 => $riskLevel,
                    'risk_score'                 => $score,
                    'risk_factors'               => json_encode($riskFactors),
                    'recommended_amount'         => $recommendedAmount,
                ]);
            }

            if (request()->expectsJson()) {
                return response()->json([
                    'success'        => true,
                    'score'          => $score,
                    'risk_level'     => $riskLevel,
                    'analysis_note'  => $analysisNote,
                    'has_risk_flags' => $hasRiskFlags,
                    'message'        => 'AI Credit Scan completed successfully! Score: ' . $score . ' • Risk: ' . $riskLevel,
                    'status'         => $hasRiskFlags ? 'flagged' : $application->status,
                ]);
            }
            return back()->with('success', 'AI Credit Scan completed successfully! Score: ' . $score . ' • Risk: ' . $riskLevel);

        } catch (\Throwable $e) {
            Log::error('Admin AI Scan Error: ' . $e->getMessage(), [
                'application_id' => $id,
                'trace'          => $e->getTraceAsString(),
            ]);

            // If Gemini is unavailable, mark documents as "unverified" and
            // fall back to a rule-based score computed from the declared data
            // (income / obligations / term). We never fabricate a document
            // extraction — the note makes the source explicit.
            $fallbackScore = null;
            $riskLevel = 'None';
            $riskFactors = [];
            $recommendedAmount = 0.0;
            if ($loanApp) {
                try {
                    $eval = app(LoanEvaluationService::class)->evaluate($loanApp);
                    $fallbackScore = (int) $eval['risk_score'];
                    $riskLevel = $fallbackScore >= 70 ? 'Low' : ($fallbackScore >= 50 ? 'Medium' : 'High');
                    $riskFactors = $eval['reasons'] ?? [];
                    $recommendedAmount = $eval['recommended_amount'] ?? 0.0;
                } catch (\Throwable $de) {
                    Log::warning('Rule-based fallback score failed for application', [
                        'application_id' => $loanApp->id,
                        'error'          => $de->getMessage(),
                    ]);
                }
            }

            $application->ai_score = $fallbackScore;
            $application->ai_analysis_note = 'Document scan failed (' . $e->getMessage()
                . '). Score computed from declared data only; documents unverified.';
            $application->risk_level = $riskLevel;
            $application->risk_score = $fallbackScore;
            $application->risk_factors = json_encode($riskFactors);
            $application->recommended_amount = $recommendedAmount;
            if (Schema::hasColumn('applications', 'last_scanned_at')) {
                $application->last_scanned_at = now();
            }
            $application->save();

            if ($loanApp) {
                $loanApp->update([
                    'ai_score'                   => $fallbackScore,
                    'ai_analysis_notes'          => 'Document scan failed (' . $e->getMessage()
                        . '). Score computed from declared data only; documents unverified.',
                    'document_processing_status' => 'unverified',
                    'risk_level'                 => $riskLevel,
                    'risk_score'                 => $fallbackScore,
                    'risk_factors'               => json_encode($riskFactors),
                    'recommended_amount'         => $recommendedAmount,
                ]);
            }

            if (request()->expectsJson()) {
                return response()->json([
                    'success'        => true,
                    'score'          => null,
                    'risk_level'     => $riskLevel,
                    'analysis_note'  => 'Document scan failed. Documents marked unverified.',
                    'has_risk_flags' => true,
                    'message'        => 'AI Scan failed. Documents marked unverified.',
                ]);
            }
            return back()->with('error', 'AI Scan failed. Documents marked unverified.');
        }
    }

    /**
     * Manually trigger payment reminders (admin only).
     */
    public function runReminders(Request $request)
    {
        \Log::info("DEBUG: runReminders called");
        $result = app(PaymentReminderService::class)->run();

        $message = "{$result['sent']} reminders sent, {$result['skipped']} skipped, {$result['errors']} errors";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'sent'    => $result['sent'],
                'skipped' => $result['skipped'],
                'errors'  => $result['errors'],
                'details' => $result['details'],
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Add a review note to an application (Credit Investigator).
     */
    public function addReviewNote(Request $request, $id)
    {
        $application = Application::where('app_id', $id)->first()
            ?? Application::where('id', $id)->first();

        if (!$application) {
            return response()->json(['success' => false, 'message' => 'Application record not found.'], 404);
        }

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $existing = $application->admin_remarks;
        $entry = now()->format('Y-m-d H:i:s') . ' — ' . Auth::user()->name . ': ' . $validated['note'];
        $application->admin_remarks = $existing ? $existing . "\n" . $entry : $entry;
        $application->save();

        return response()->json([
            'success' => true,
            'note' => $validated['note'],
            'added_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Validate the recommended amount for an application (Credit Investigator).
     */
    public function validateRecommendation(Request $request, $id)
    {
        $application = Application::where('app_id', $id)->first()
            ?? Application::where('id', $id)->first();

        if (!$application) {
            return response()->json(['success' => false, 'message' => 'Application record not found.'], 404);
        }

        $validated = $request->validate([
            'valid' => ['required', 'in:0,1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $existing = $application->admin_remarks;
        $entry = now()->format('Y-m-d H:i:s') . ' — Recommendation ' . ($validated['valid'] ? 'validated' : 'flagged for review') . ' by ' . Auth::user()->name . ': ' . ($validated['note'] ?? '');
        $application->admin_remarks = $existing ? $existing . "\n" . $entry : $entry;
        $application->save();

        return response()->json([
            'success' => true,
            'valid' => (bool) $validated['valid'],
            'note' => $validated['note'] ?? null,
        ]);
    }
}
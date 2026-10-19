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
use App\Support\LoanStatus;
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

class AdminController extends Controller
{
    // 1. Dashboard Route
    public function index()
    {
        if (Auth::check()) {
            $usertype = Auth::user()->usertype;

            if ($usertype === 'admin') {
                $applications = Application::with('loanApplication.user')->latest()->get();

                $totalDisbursed = (float) LoanApplication::where('status', 'approved')->sum('amount');
                $activeMembersCount = CoopMember::where('is_registered', true)->count();
                $activeBorrowersCount = $activeMembersCount;

                $riskFlagsCount = Application::where('risk_level', 'High')->count();
                $flaggedApplications = Application::where('risk_level', 'High')->with('loanApplication.user')->latest()->get();

                $notifications = AppNotification::latest()->take(5)->get();
                $pendingApplicationsCount = Application::where('status', 'pending')->count();

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

                    $disbursed = Application::whereIn('status', ['Approved', 'Disbursed'])
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
                    'ai_credit_score' => $aiCreditScore,
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
            'total_disbursed' => (float) Application::where('status', 'Approved')->sum('amount'),
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
            'total_disbursed' => (float) Application::where('status', 'Approved')->sum('amount'),
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
                  ->orWhere('user_name', 'like', "%{$search}%")
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
            ->whereNotIn('status', ['Pending', 'pending'])
            ->whereIn('status', ['Approved', 'approved', 'Active', 'active'])
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
            'status'        => 'required|string|in:active,inactive,suspended,approved,Approved',
        ]);

        $lastId = CoopMember::withTrashed()->max('id') ?? 0;
        $memberId = 'SDCC-' . date('Y') . '-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);
        $coopMemberId = $validated['member_id'] ?? $memberId;

        CoopMember::create([
            'member_id'      => $coopMemberId,
            'full_name'      => $validated['full_name'],
            'email'          => $validated['email'],
            'date_of_birth'  => $validated['date_of_birth'] ?? now(),
            'is_registered'  => $request->boolean('is_registered'),
            'status'         => $validated['status'] ?? 'Approved',
        ]);

        return redirect()->route('active-members.index')->with('success', 'Member added successfully!');
    }

    // 12. Activate Member
    public function approveBorrower($id)
    {
        $member = CoopMember::findOrFail($id);
        $member->status = 'Approved';
        $member->save();

        return redirect()->back()->with('success', 'Member status updated to Approved.');
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

    // 15. Admin Users Page
    public function adminUsers()
    {
        $admins = User::where('usertype', 'admin')->latest()->get();
        return view('admin.admins', compact('admins'));
    }

    // 16. Store a new admin account
    public function storeAdmin(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'phone'    => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'phone'    => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);
        $user->usertype = 'admin';
        $user->save();

        return response()->json(['success' => true, 'id' => $user->id]);
    }

    // 17. Update an admin account
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

        return response()->json(['success' => true, 'id' => $id]);
    }

    // 18. Delete an admin account
    public function destroyAdmin($id)
    {
        $admin = User::where('usertype', 'admin')->findOrFail($id);

        if ($admin->id === Auth::id()) {
            return response()->json(['success' => false, 'message' => 'You cannot delete your own account.'], 422);
        }

        $admin->delete();

        return response()->json(['success' => true, 'id' => $id]);
    }

    // 19. Membership Applications — List
    public function memberships()
    {
        $applications = MemberApplication::latest()->get();
        return view('admin.memberships', compact('applications'));
    }

    // 20. Approve a membership application
    public function approveMember($id)
    {
        $application = MemberApplication::findOrFail($id);
        $existing = CoopMember::where('email', $application->email)->first();

        $userId = $application->user_id;
        if (!$userId) {
            $existingUser = User::where('email', $application->email)->first();
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
                'status'        => 'Approved',
            ]);
        } else {
            $existing->status = 'Approved';
            if ($application->user_id && !$existing->user_id) {
                $existing->user_id = $application->user_id;
            }
            $existing->save();
        }

        if ($userId) {
            $existing->is_registered = true;
            $existing->user_id = $userId;
            $existing->save();

            User::where('id', $userId)->update(['coop_member_id' => $existing->id]);
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

    // 21. Reject a membership application
    public function rejectMember($id)
    {
        $application = MemberApplication::findOrFail($id);
        $application->status      = 'rejected';
        $application->reviewed_at = now();
        $application->reviewed_by = Auth::id();
        $application->save();

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
        $repayments = Payment::latest()->get();
        $totalRepayments = Payment::sum('amount_paid');
        return view('admin.repayments', compact('repayments', 'totalRepayments'));
    }

    public function creditAssessment() { return view('admin.index'); }

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

    public function reports()
    {
        $totalDisbursed = LoanApplication::where('status', 'disbursed')->sum('amount') ?? 0;
        $totalRepayments = Payment::sum('amount_paid') ?? 0;
        $activeMembersCount = CoopMember::whereNotIn('status', ['Pending', 'pending'])->count() ?? 0;
        $pendingApplicationsCount = LoanApplication::where('status', 'pending')->count() ?? 0;

        return view('admin.reports', compact('totalDisbursed', 'totalRepayments', 'activeMembersCount', 'pendingApplicationsCount'));
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
                'channel'        => 'Bank Transfer',
                'account_number' => '—',
                'notes'          => 'Approved loan application payout.',
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

        return response()->json([
            'success' => true,
            'message' => 'Repayment recorded successfully.',
            'remaining_balance' => (float) ($application->remaining_balance ?? 0),
        ]);
    }

    // 27. Update application settings
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
        $type = $request->query('type', 'applications');
        $applications = Application::latest()->get();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.report-pdf', compact('applications', 'type'));

        return $pdf->download("report_{$type}_" . now()->format('Y-m-d') . '.pdf');
    }

    // 29. Export reports to Excel
    public function exportExcel(Request $request)
    {
        $type = $request->query('type', 'applications');
        $applications = Application::latest()->get();
        $filename = 'report_' . $type . '_' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($applications) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Application ID', 'Applicant', 'Loan Type', 'Amount', 'Status', 'AI Score']);

            foreach ($applications as $app) {
                fputcsv($file, [
                    $app->app_id,
                    $app->applicant,
                    $app->loan_type,
                    $app->amount,
                    $app->status,
                    $app->ai_score,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
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

        $user = $loanApp?->user;

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
                'risk_factors' => $application->risk_factors ? json_decode($application->risk_factors, true) : [],
                'ai_score' => $application->ai_score ?? null,
                'ai_analysis_note' => $application->ai_analysis_note,
                'has_risk_flags' => $application->has_risk_flags ?? false,
                'document_processing_status' => $loanApp?->document_processing_status ?? 'pending',
                'last_scanned_at' => $application->updated_at ? \Carbon\Carbon::parse($application->updated_at)->format('Y-m-d H:i:s') : null,
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
        $tables = DB::select('SHOW TABLES');
        $dump = "-- San Dionisio Credit Cooperative Database Backup\n";
        $dump .= "-- Generated: " . now()->format('Y-m-d H:i:s') . "\n\n";

        foreach ($tables as $table) {
            $tableName = reset($table);
            $dump .= "-- Table: {$tableName}\n";
            $dump .= "DROP TABLE IF EXISTS `{$tableName}`;\n";

            $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`")[0];
            $dump .=$createTable->{'Create Table'} . ";\n\n";

            $rows = DB::select("SELECT * FROM `{$tableName}`");
            if (!empty($rows)) {$dump .= "INSERT INTO `{$tableName}` VALUES\n";
                $values = [];
                foreach ($rows as $row) {$rowValues = [];
                    foreach ((array) $row as $value) {$rowValues[] = $value === null ? 'NULL' : "'" . str_replace("'", "\\'", $value) . "'";
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
}
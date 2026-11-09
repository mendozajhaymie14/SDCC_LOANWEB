<?php

namespace App\Http\Controllers;

use App\Events\LoanApplicationApproved;
use App\Events\PaymentReceived;
use Carbon\Carbon;
use App\Models\Application;
use App\Models\AppNotification;
use App\Models\AppNotificationLog;
use App\Mail\LoanApprovedMail;
use App\Mail\LoanRejectedMail;
use App\Mail\MemberRejectedMail;
use Illuminate\Support\Facades\Mail;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;
use App\Models\Borrowers;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\MemberApplication;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Support\Format;
use App\Support\LoanStatus;
use App\Support\Permissions;
use App\Services\AmortizationService;
use App\Services\DuplicateMemberService;
use App\Services\LoanEvaluationService;
use App\Services\CreditLimitService;
use App\Services\LoanSubmissionService;
use App\Services\PaymentReminderService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

                $totalApproved = (float) LoanApplication::where('status', LoanStatus::APPROVED)->sum('amount');
                $activeMembersCount = CoopMember::where('status', CoopMember::STATUS_ACTIVE)->count();
                $totalMembersCount = CoopMember::count();
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

                    $monthlyApproved = LoanApplication::where('status', LoanStatus::APPROVED)
                        ->whereBetween('created_at', [$monthStart, $monthEnd])
                        ->sum('amount');

                    $collected = Payment::whereBetween('payment_date', [$monthStart, $monthEnd])
                        ->sum('amount_paid');

                    $monthlyDisbursements[] = (float) $monthlyApproved;
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

                // Overdue loans: disbursed/approved with a remaining balance that
                // is past the next due date.
                $overdueLoans = LoanApplication::where('status', LoanStatus::APPROVED)
                    ->where('remaining_balance', '>', 0)
                    ->whereDate('next_due_date', '<', now()->toDateString())
                    ->with('user.coopMember')
                    ->latest()
                    ->get();
                $overdueAmount = (float) $overdueLoans->sum('remaining_balance');

                return view('admin.index', compact(
                    'applications', 'activeBorrowersCount', 'activeMembersCount', 'totalApproved',
                    'totalMembersCount', 'overdueLoans', 'overdueAmount',
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

    // 3. Staff Encode Application — encode an application for an existing member
    public function encodeApplication()
    {
        $members = CoopMember::where('status', CoopMember::STATUS_ACTIVE)->latest()->get();
        return view('admin.encode-application', compact('members'));
    }

    public function storeEncodedApplication(Request $request)
    {
        // Validate all required fields for staff encoding
        $validator = Validator::make($request->all(), [
            'member_id'                    => 'required|numeric|exists:coop_members,id',
            'loan_type'                    => 'required|in:' . implode(',', array_keys(config('loan_matrix.loan_types'))),
            'amount'                       => 'required|numeric|min:1',
            'term_months'                  => 'required|integer|min:1',
            'purpose'                      => 'required|string|max:500',
            'monthly_income'               => 'required|numeric|min:0',
            'existing_monthly_obligations' => 'required|numeric|min:0',
            'source_of_income'             => 'required|string|in:' . implode(',', config('loan_matrix.form_options.source_of_income')),
            'required_documents'           => 'nullable|array|max:10',
            'required_documents.*'         => 'file|mimes:jpg,jpeg,png,pdf|max:5120',
            'document_consent'             => 'boolean',
        ]);

        if ($validator->fails()) {
            Log::warning('Staff encode validation failed (stage 1)', [
                'errors'   => $validator->errors()->toArray(),
                'request'  => array_keys($request->all()),
                'user_id'  => Auth::id(),
            ]);
            return back()->withInput()->withErrors($validator);
        }

        $validated = $validator->validated();

        $member = CoopMember::findOrFail($validated['member_id']);

        if ($member->status !== CoopMember::STATUS_ACTIVE) {
            return back()->withInput()->with('error', 'Cannot encode an application for an inactive member.');
        }

        // Consent validation: require document_consent when documents are uploaded
        if ($request->hasFile('required_documents') && ! $request->boolean('document_consent')) {
            return back()->withInput()->withErrors([
                'document_consent' => 'You must consent to your uploaded documents being scanned for verification. The AI check is advisory; recommended amounts and risk levels are rule-based and unchanged.',
            ]);
        }

        // Resolve the user linked to this member (if any)
        $user = $member->user;

        // Validate loan product terms
        $loanTypeConfig = config('loan_matrix.matrix.' . $validated['loan_type']);
        if (! $loanTypeConfig) {
            return back()->withInput()->withErrors(['loan_type' => 'Invalid loan product selected.']);
        }

        // Validate amount against product max
        $maxAmount = UserController::maxAmountFor($validated['loan_type']);
        if ($loanTypeConfig['max_amount'] === 'share_capital_x2') {
            $maxAmount = 2000000;
        }
        if ($loanTypeConfig['max_amount'] === 'share_capital_plus_savings') {
            $maxAmount = 2000000;
        }
        $maxAmount = min($maxAmount ?? 2000000, 2000000);
        $maxAmountRule = ['max:' . $maxAmount];
        $amountMinRule = $maxAmount < 1000 ? ['min:1'] : ['min:1000'];

        // Validate term is offered by this product
        $termRule = ['required', 'integer', 'in:' . implode(',', $loanTypeConfig['terms'])];

        $rules = [
            'amount'      => ['required', 'numeric', ...$amountMinRule, ...$maxAmountRule],
            'term_months' => $termRule,
        ];

        $validator2 = Validator::make($request->all(), $rules);

        if ($validator2->fails()) {
            Log::warning('Staff encode validation failed (stage 2: amount/term)', [
                'errors'   => $validator2->errors()->toArray(),
                'request'  => array_keys($request->all()),
                'user_id'  => Auth::id(),
            ]);
            return back()->withInput()->withErrors($validator2);
        }

        // Block if member already has a pending application (only if linked to a user)
        if ($user) {
            $hasPending = LoanApplication::where('user_id', $user->id)
                ->where('status', 'pending')
                ->exists();

            if ($hasPending) {
                return back()->withInput()
                    ->with('error', 'This member already has an application under review. Wait for it to be decided before applying again.');
            }
        }

        // Store uploaded supporting documents
        $documentPaths = [];
        if ($request->hasFile('required_documents')) {
            foreach ($request->file('required_documents') as $document) {
                $path = $document->store('loan-documents', 'public');
                $documentPaths[] = $path;
            }
        }

        // Build data for LoanSubmissionService
        $data = [
            'user_id'                    => $user?->id,
            'reference'                  => LoanApplication::makeReference(),
            'full_name'                  => $member->full_name,
            'email'                      => $member->email,
            'contact_number'             => $member->contact_number ?? $user?->phone ?? 'N/A',
            'address'                    => $member->address ?? $user?->address ?? 'N/A',
            'birth_date'                 => $member->date_of_birth ?? null,
            'employment_status'          => $member->occupation ?? 'Employed',
            'source_of_income'           => $validated['source_of_income'],
            'monthly_income'             => $validated['monthly_income'],
            'existing_monthly_obligations' => $validated['existing_monthly_obligations'],
            'loan_type'                  => $validated['loan_type'],
            'purpose_of_loan'            => $validated['purpose'],
            'amount'                     => $validated['amount'],
            'term_months'                => $validated['term_months'],
            'status'                     => 'pending',
            'encoded_by'                 => Auth::id(),
            'documents'                  => $documentPaths,
        ];

        try {
            // Submit via shared service (creates LoanApplication, evaluates, mirrors)
            $application = app(LoanSubmissionService::class)->submit($data);

            // Dispatch document analysis job if documents were uploaded
            if (!empty($documentPaths)) {
                try {
                    \App\Jobs\ProcessLoanDocuments::dispatch(
                        $application->id,
                        (float) $validated['monthly_income'],
                        (float) $validated['amount'],
                        $documentPaths
                    );
                } catch (\Throwable $e) {
                    \Log::warning('Could not start document analysis: ' . $e->getMessage());
                }
            }

            AuditLog::log('Application Encoded', "Staff-encoded application {$application->reference} for {$member->full_name}");

            return redirect()
                ->route('applications.index')
                ->with('success', 'Application encoded successfully.');
        } catch (\Throwable $e) {
            Log::error('Staff encode exception: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'user_id'   => Auth::id(),
                'member_id' => $member->id ?? null,
            ]);
            return back()->withInput()->with('error', 'An error occurred while encoding the application: ' . $e->getMessage());
        }
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
        // Use the model's totalPayable() which computes principal + interest,
        // NOT the raw principal. This is the single source of truth for all flows.
        $loanApp = $application->loanApplication;
        $totalPayable = $loanApp ? $loanApp->totalPayable() : $this->totalPayable((float) $application->amount, (int) ($application->term_months ?? 12));

        DB::transaction(function () use ($application, $disbursementDate, $totalPayable, $approvedAmount, $reason, $loanApp) {
            $application->status = 'Approved';
            $application->approved_amount = $approvedAmount ?? $application->recommended_amount ?? 0.00;
            $application->next_due_date = $disbursementDate->copy()->addMonth();
            $application->remaining_balance = $totalPayable;
            // Track which staff user approved this application.
            $application->approved_by = Auth::id();
            $application->save();

            if ($application->loan_application_id) {
                LoanApplication::where('id', $application->loan_application_id)->update([
                    'status'            => 'approved',
                    'reviewed_at'       => now(),
                    'remaining_balance' => $totalPayable,
                    'next_due_date'     => $disbursementDate->copy()->addMonth(),
                    'approved_by'       => Auth::id(),
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

            // Email the borrower about the approval
            try {
                $loanApp = $application->loanApplication;
                if ($loanApp) {
                    $email = $loanApp->getRecipientEmail();
                    if ($email) {
                        Mail::to($email)->send(new LoanApprovedMail($loanApp));
                        AppNotificationLog::log(
                            $email, 'Your Loan Application Has Been Approved', 'approved', 'Sent',
                            'email', $loanApp->user_id, $loanApp->id
                        );
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Approval email failed for loan', [
                    'application_id' => $application->id,
                    'error' => $e->getMessage(),
                ]);
                $loanAppRef = $application->loan_application_id
                    ? LoanApplication::find($application->loan_application_id)
                    : null;
                $userId = $loanAppRef?->user?->id;
                AppNotificationLog::logFailed(
                    (string) ($application->loan_application_id
                        ? optional(LoanApplication::find($application->loan_application_id))->user?->email
                        : config('mail.from.address')),
                    'Application Approved',
                    'approved',
                    $e->getMessage(),
                    'email',
                    $userId,
                    $application->loan_application_id
                );
            }

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
            'total_approved' => (float) LoanApplication::where('status', LoanStatus::APPROVED)->sum('amount'),
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

            // Email the borrower about the rejection
            try {
                $loanApp = $application->loanApplication;
                if ($loanApp) {
                    $email = $loanApp->getRecipientEmail();
                    if ($email) {
                        Mail::to($email)->send(new LoanRejectedMail($loanApp, $rejectReason));
                        AppNotificationLog::log(
                            $email, 'Your Loan Application Update', 'rejected', 'Sent',
                            'email', $loanApp->user_id, $loanApp->id
                        );
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Rejection email failed for loan', [
                    'application_id' => $application->id,
                    'error' => $e->getMessage(),
                ]);
                $loanAppRef = $application->loan_application_id
                    ? LoanApplication::find($application->loan_application_id)
                    : null;
                AppNotificationLog::logFailed(
                    (string) ($application->loan_application_id
                        ? optional(LoanApplication::find($application->loan_application_id))->user?->email
                        : config('mail.from.address')),
                    'Application Rejected',
                    'rejected',
                    $e->getMessage(),
                    'email',
                    $loanAppRef?->user?->id,
                    $application->loan_application_id
                );
            }

            AuditLog::log('Application Rejected', "Application {$application->app_id} rejected for {$application->applicant}" . ($rejectReason ? ". Reason: $rejectReason" : ''));
        });

        return response()->json([
            'success'        => true,
            'app_id'         => $application->app_id,
            'status'         => $application->status,
            'total_approved' => (float) LoanApplication::where('status', LoanStatus::APPROVED)->sum('amount'),
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
        AppNotification::whereNull('read_at')
            ->where('user_id', auth()->id())
            ->update(['read_at' => now()]);
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

        // Compute credit-limit suggestions for every member so the edit modal
        // can show "Suggested: ₱X (rule)" without a per-row AJAX round-trip.
        $suggestions = [];
        $useSuggested = [];
        foreach ($members as $m) {
            $result = app(CreditLimitService::class)->calculateCreditLimit($m);
            $suggestions[$m->id] = app(CreditLimitService::class)->formatSuggestion($result['limit'], $result['cappedBy']);
            if ($result['limit'] > 0) {
                $useSuggested[$m->id] = $result['limit'];
            }
        }

        return view('admin.activemember', compact('members', 'suggestions', 'useSuggested'));
    }

    // 11. Store New Member manually
    public function storeBorrower(Request $request)
    {
        // Normalise the Member ID first so "sdcc-2026-0016 " is accepted as SDCC-2026-0016
        $request->merge([
            'member_id' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('member_id'))),
        ]);

        $validated = $request->validate([
            // Format: SDCC-YYYY-0000 (e.g. SDCC-2026-0016)
            'member_id'     => ['required', 'string', 'max:14', 'regex:/^SDCC-(19|20)\d{2}-\d{4}$/', 'unique:coop_members,member_id'],
            'full_name'     => 'required|string|max:255',
            'email'         => 'required|email|unique:coop_members,email',
            'phone'         => ['nullable', 'string', new \App\Rules\PhilippineMobileNumber, 'max:20'],
            'date_of_birth' => 'nullable|date',
            'address'       => 'nullable|string|max:255',
            'occupation'    => 'nullable|string|max:255',
            'monthly_income' => 'nullable|numeric|min:0',
            'existing_monthly_obligations' => 'nullable|numeric|min:0',
            'savings_balance' => 'nullable|numeric|min:0',
            'approved_credit_limit' => 'nullable|numeric|min:0',
            'is_registered' => 'boolean',
            'status'        => 'required|string|in:' . implode(',', CoopMember::STATUSES),
        ], [
            'member_id.regex'  => 'The Member ID must follow the format SDCC-YYYY-0000, for example SDCC-2026-0016.',
            'member_id.unique' => 'That Member ID is already in use by another member.',
        ]);

        // Duplicate detection: block on email or contact number match, warn on name-only match.
        // Editing an existing member is excluded via the blank member_id path.
        $check = app(DuplicateMemberService::class)->check(
            $validated['full_name'],
            $validated['email'],
            $validated['phone'] ?? null
        );

        if ($check['isDuplicate']) {
            return redirect()->route('active-members.index')
                ->withErrors(['email' => $check['message']])
                ->withInput();
        }

        if ($check['isWarning'] && $request->filled('confirm_continue')) {
            // Officer confirms to continue despite name-only warning
        }

        $lastId = CoopMember::withTrashed()->max('id') ?? 0;
        $memberId = 'SDCC-' . date('Y') . '-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);
        $coopMemberId = $validated['member_id'] ?? $memberId;

        CoopMember::create([
            'member_id'      => $coopMemberId,
            'full_name'      => $validated['full_name'],
            'email'          => $validated['email'],
            'contact_number' => $validated['phone'] ?? null,
            'date_of_birth'  => $validated['date_of_birth'] ?? now(),
            'address'        => $validated['address'] ?? null,
            'occupation'     => $validated['occupation'] ?? null,
            'monthly_income' => $validated['monthly_income'] ?? null,
            'existing_monthly_obligations' => $validated['existing_monthly_obligations'] ?? null,
            'savings_balance' => $validated['savings_balance'] ?? null,
            'approved_credit_limit' => $validated['approved_credit_limit'] ?? null,
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

    // 14b. Update Member Profile
    public function updateBorrower(Request $request, $id)
    {
        $member = CoopMember::findOrFail($id);

        $validated = $request->validate([
            'full_name'     => 'required|string|max:255',
            'email'         => 'required|email|max:255',
            'phone'         => ['nullable', 'string', new \App\Rules\PhilippineMobileNumber, 'max:20'],
            'date_of_birth' => 'nullable|date',
            'address'       => 'nullable|string|max:255',
            'occupation'    => 'nullable|string|max:255',
            'monthly_income' => 'nullable|numeric|min:0',
            'existing_monthly_obligations' => 'nullable|numeric|min:0',
            'savings_balance' => 'nullable|numeric|min:0',
            'approved_credit_limit' => 'nullable|numeric|min:0',
            'status'        => 'required|string|in:' . implode(',', CoopMember::STATUSES),
        ]);

        // Unique email check ignoring this member's current email
        // Manual check to avoid ValidationException redirect to referer;
        // this mirrors Rule::unique('coop_members', 'email')->ignore($member->id)
        if (CoopMember::where('email', $validated['email'])
            ->where('id', '!=', $member->id)
            ->exists()) {
            return redirect()->route('active-members.index')
                ->withErrors(['email' => 'The email has already been taken by another member.'])
                ->withInput();
        }

        // Duplicate detection: block on email or contact match with another member
        $check = app(DuplicateMemberService::class)->check(
            $validated['full_name'],
            $validated['email'],
            $validated['phone'] ?? null,
            $member->id // exclude this member (coop_members.id) from the check
        );

        if ($check['isDuplicate']) {
            return redirect()->route('active-members.index')
                ->withErrors(['email' => $check['message']])
                ->withInput();
        }

        if ($check['isWarning'] && !$request->filled('confirm_continue')) {
            return redirect()->route('active-members.index')
                ->withInput()
                ->with('warning', $check['message']);
        }

        $member->full_name = $validated['full_name'];
        $member->email = $validated['email'];
        $member->contact_number = $validated['phone'] ?? null;
        $member->date_of_birth = $validated['date_of_birth'] ?? $member->date_of_birth;
        $member->address = $validated['address'] ?? $member->address;
        $member->occupation = $validated['occupation'] ?? $member->occupation;

        // Capture old financials for recompute decision
        $oldIncome = (float) ($member->monthly_income ?? 0);
        $oldObligations = (float) ($member->existing_monthly_obligations ?? 0);
        $oldSavings = (float) ($member->savings_balance ?? 0);

        $member->monthly_income = $validated['monthly_income'] ?? $member->monthly_income;
        $member->existing_monthly_obligations = $validated['existing_monthly_obligations'] ?? $member->existing_monthly_obligations;
        $member->savings_balance = $validated['savings_balance'] ?? $member->savings_balance;

        $storedLimit = $member->approved_credit_limit;
        $submittedLimit = $validated['approved_credit_limit'] ?? $storedLimit;

        // Server-side source decision: never trust hidden browser field.
        // If submitted limit differs from stored, set source='auto' ONLY if it matches computed suggestion.
        if (Schema::hasColumn('coop_members', 'credit_limit_source')
            && $submittedLimit != $storedLimit) {
            $result = app(CreditLimitService::class)->calculateCreditLimit($member);
            $member->credit_limit_source = ($submittedLimit == $result['limit']) ? 'auto' : 'manual';
        }

        // Recompute when limit unchanged, source is 'auto', and financials changed
        if (Schema::hasColumn('coop_members', 'credit_limit_source')
            && $submittedLimit == $storedLimit
            && ($member->credit_limit_source ?? null) === 'auto'
            && app(CreditLimitService::class)->needsRecompute($member, (float) ($member->monthly_income ?? 0), (float) ($member->existing_monthly_obligations ?? 0), (float) ($member->savings_balance ?? 0))) {
            $result = app(CreditLimitService::class)->calculateCreditLimit($member);
            if ($result['limit'] > 0) {
                $member->approved_credit_limit = $result['limit'];
                $member->credit_limit_computed_at = now();
            }
            // Never auto-store a computed 0 — leave DB empty
        } elseif ($submittedLimit != $storedLimit) {
            // Staff explicitly changed the limit; use their value
            $member->approved_credit_limit = $submittedLimit;
        }

        $member->status = $validated['status'] ?? $member->status;
        $member->save();

        // Also update the linked user's email and name if it exists
        if ($member->user_id) {
            $user = User::find($member->user_id);
            if ($user) {
                $user->email = $validated['email'];
                $user->name = $validated['full_name'];
                $user->save();
            }
        }

        AuditLog::log('Member Updated', "Member {$member->member_id} ({$member->full_name}) updated by " . Auth::user()->name);

        return redirect()->route('active-members.index')->with('success', 'Member updated successfully!');
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
            'phone'    => ['nullable', 'string', new \App\Rules\PhilippineMobileNumber, 'max:20'],
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
            'phone'    => ['nullable', 'string', new \App\Rules\PhilippineMobileNumber, 'max:20'],
            'password' => 'nullable|string|min:8',
            'role'     => 'required|string|in:' . implode(',', Permissions::STAFF_ROLES),
        ]);

        Validator::make(
            ['email' => $validated['email']],
            ['email' => 'required|email|max:255|unique:users,email,' . $user->id]
        )->validated();

        $oldRole = $user->role;

        $user->name  = $validated['name'];
        $user->email = $validated['email'] ?? $user->email;
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
        $applications = MemberApplication::with('approvedMember')->latest()->get();
        return view('admin.memberships', compact('applications'));
    }

    // 20. Approve a membership application
    //
    // One transaction, one outcome: the cooperative membership exists, the
    // account is linked to it, the account's role becomes 'member', the
    // membership status is the canonical 'active', the audit trail records
    // it and the applicant is told in the app.
    public function approveMember(Request $request, $id)
    {
        $application = MemberApplication::findOrFail($id);

        // Duplicate detection: block on email or contact number match (other than this application's own email)
        // warn on name-only match. Exclude this application's own ID from member_applications,
        // and any CoopMember already linked to this application (approved_member_id).
        $check = app(DuplicateMemberService::class)->check(
            $application->fullName,
            $application->email,
            $application->contact_number,
            $application->approved_member_id ?? null, // exclude this member (coop_members.id) if already linked
            $application->id // exclude this application (member_applications.id) from pending check
        );

        if ($check['isDuplicate']) {
            // If the match is the same email, it's likely this application's own record
            // being found in coop_members (already exists but inactive). Allow that.
            if (!($check['matchedFields'] === ['email'] && $check['existingMember'] instanceof \App\Models\CoopMember)) {
                return response()->json([
                    'success' => false,
                    'message' => $check['message'],
                ], 422);
            }
        }

        // Credit limit validation BEFORE the transaction so validation errors
        // return proper HTTP responses rather than 500s from inside the transaction.
        $approvedAmount = $request->input('approved_amount');
        $recommendation = $this->creditLimitRecommendationForApplication($application);

        if ($approvedAmount !== null && $approvedAmount !== '' && $approvedAmount != 0) {
            $submittedAmount = (float) $approvedAmount;

            if ($submittedAmount > $recommendation['limit']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Approved credit limit cannot exceed the recommended limit.',
                ], 422);
            }
        }

        try {
            $member = DB::transaction(function () use ($application, $request, $approvedAmount, $recommendation) {
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
                    'email'          => $application->email,
                    'contact_number' => $application->contact_number,
                    'occupation'     => $application->occupation ?? '',
                    'is_registered'  => true,
                    'user_id'        => $userId,
                    'status'         => CoopMember::STATUS_ACTIVE,
                    'monthly_income' => $application->monthly_income,
                    'existing_monthly_obligations' => $application->existing_monthly_obligations,
                    'savings_balance' => $application->savings_balance,
                    'date_of_birth'  => $application->date_of_birth,
                ]);
            } else {
                // Copy contact details from the application where the member row
                // is empty, then activate the member regardless of whether an
                // account is linked (the Active Members page counts is_registered).
                if (empty($coopMember->email) && $application->email) {
                    $coopMember->email = $application->email;
                }
                if (empty($coopMember->contact_number) && $application->contact_number) {
                    $coopMember->contact_number = $application->contact_number;
                }
                if (empty($coopMember->monthly_income) && $application->monthly_income) {
                    $coopMember->monthly_income = $application->monthly_income;
                }
                if (empty($coopMember->existing_monthly_obligations) && $application->existing_monthly_obligations) {
                    $coopMember->existing_monthly_obligations = $application->existing_monthly_obligations;
                }

                // Copy savings_balance only when empty
                if (empty($coopMember->savings_balance) && $application->savings_balance) {
                    $coopMember->savings_balance = $application->savings_balance;
                }

                // Copy date_of_birth only when empty
                if (empty($coopMember->date_of_birth) && $application->date_of_birth) {
                    $coopMember->date_of_birth = $application->date_of_birth;
                }

                $coopMember->status        = CoopMember::STATUS_ACTIVE;
                $coopMember->is_registered  = true;

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

            // Compose the address for coop_members from the application's CURRENT address.
            // If current address is empty, fall back to permanent address fields.
            if (empty($coopMember->address)) {
                $currentParts = array_filter([
                    $application->house_no,
                    $application->street,
                    $application->barangay,
                    $application->municipality,
                    $application->province,
                    $application->zip_code,
                ]);

                $addressParts = $currentParts;

                // Fall back to permanent address only if current is empty
                if (empty($addressParts)) {
                    $addressParts = array_filter([
                        $application->perm_street,
                        $application->perm_barangay,
                        $application->perm_municipality,
                        $application->perm_province,
                        $application->perm_zip_code,
                    ]);
                }

                if (!empty($addressParts)) {
                    $coopMember->address = implode(', ', $addressParts);
                    $coopMember->save();
                }
            }

            // Copy Date of Birth from the application to the member record only
            // when the member's DOB is empty (never overwrite staff-entered data).
            // (This is now handled inside the transaction)

            $application->status             = 'approved';
            $application->reviewed_at        = now();
            $application->reviewed_by        = Auth::id();
            $application->approved_member_id = $coopMember->id;
            $application->save();

            // Credit limit validation handled before the transaction starts.
            $submittedAmount = (float) $approvedAmount;

            // If approved amount is blank, treat as not assessed — do not write
            // any credit limit fields; the member is still activated.
            if ($approvedAmount === null || $approvedAmount === '') {
                $source = 'not_assessed';
            } else {
                $source = $submittedAmount === $recommendation['limit']
                    ? 'auto'
                    : 'manual';
            }

                if ($source === 'not_assessed') {
                    // explicitly handled below — falls through without writing
                } elseif ($source === 'auto') {
                    $coopMember->approved_credit_limit = $recommendation['limit'];
                    $coopMember->credit_limit_source = 'auto';
                    $coopMember->credit_limit_computed_at = now();
                    $coopMember->save();
                } else {
                    $coopMember->approved_credit_limit = $submittedAmount;
                    $coopMember->credit_limit_source = 'manual';
                    $coopMember->credit_limit_computed_at = now();
                    $coopMember->save();
                }

                AuditLog::log(
                    'Membership Approved',
                    'Membership application #' . $application->id . ' approved. Cooperative member '
                    . $coopMember->member_id . ' (' . $coopMember->full_name . ') is now active.'
                    . ($coopMember->credit_limit_source === 'manual'
                        ? ' Manual limit: ' . $coopMember->approved_credit_limit
                        : ''),
                    $userId
                );

                $userId = $application->user_id
                    ?: \App\Models\User::where('email', $application->email)->value('id');

                if ($userId) {
                    $coopMember->is_registered = true;
                    $coopMember->user_id = $userId;
                    $coopMember->save();

                    $user = \App\Models\User::where('id', $userId)->lockForUpdate()->first();

                    if ($user) {
                        $user->coop_member_id = $coopMember->id;
                        $user->role     = \App\Support\Permissions::ROLE_MEMBER;
                        $user->usertype = \App\Support\Permissions::ROLE_MEMBER;
                        $user->save();
                    }

                    \App\Models\AppNotification::create([
                        'user_id' => $userId,
                        'title'   => 'Membership approved',
                        'message' => 'Your cooperative membership application has been approved. You can now apply for a loan.',
                        'type'    => \App\Models\AppNotification::TYPE_MEMBERSHIP_APPROVED,
                    ]);
                }

                // Copy Date of Birth from the application to the member record only
                // when the member's DOB is empty (never overwrite staff-entered data).
                if (empty($coopMember->date_of_birth) && !empty($application->date_of_birth)) {
                    $coopMember->date_of_birth = $application->date_of_birth;
                    $coopMember->save();
                }

                return $coopMember;
            });

            try {
                Mail::to($application->email)->send(new \App\Mail\MemberApprovedMail(
                $member->full_name,
                $member->member_id,
                (bool) $member->user_id
            ));
        } catch (\Throwable $e) {
            \Log::error('Member approval email failed: ' . $e->getMessage());
        }
    } catch (\Throwable $e) {
        \Log::error('Member approval failed: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Could not approve this membership application. The database may be missing a required column. Please contact the system administrator.',
        ], 500);
    }

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
    public function rejectMember(Request $request, $id)
    {
        $rejectionReason = $request->input('rejection_reason');

        // Validation: rejection reason required, min 10 / max 500 characters.
        if (blank($rejectionReason)) {
            return response()->json([
                'success' => false,
                'message' => 'A rejection reason is required (min 10 characters).',
            ], 422);
        }
        if (mb_strlen($rejectionReason) < 10) {
            return response()->json([
                'success' => false,
                'message' => 'Rejection reason must be at least 10 characters.',
            ], 422);
        }
        if (mb_strlen($rejectionReason) > 500) {
            return response()->json([
                'success' => false,
                'message' => 'Rejection reason must not exceed 500 characters.',
            ], 422);
        }

        $application = MemberApplication::findOrFail($id);

        $userId = $application->user_id
            ?: User::where('email', $application->email)->value('id');

        try {
            DB::transaction(function () use ($application, $rejectionReason, $userId) {
                $application->status      = 'rejected';
                $application->rejection_reason = $rejectionReason;
                $application->reviewed_at = now();
                $application->reviewed_by = Auth::id();
                $application->save();

            // Deactivate the linked cooperative membership so a rejected
            // application is not shown as an active member. Idempotent: a row
            // that is already non-active is left alone.
            $member = CoopMember::where('id', $application->approved_member_id)
                ->orWhere('email', $application->email)
                ->first();

            if ($member && $member->status === CoopMember::STATUS_ACTIVE) {
                $member->status       = CoopMember::STATUS_PENDING;
                $member->is_registered = false;
                $member->save();
            }

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
                'Membership application #' . $application->id . ' rejected by ' . Auth::user()->name
                . ' (ID: ' . Auth::id() . ') for ' . $application->email
                . '. Reason: ' . $rejectionReason,
                $userId
            );

            // Send rejection email to applicant (first non-blank email).
            $applicantEmail = $application->email;
            if (filled($applicantEmail)) {
                try {
                    Mail::to($applicantEmail)->send(new MemberRejectedMail(
                        $application->fullName,
                        $rejectionReason
                    ));
                    AppNotificationLog::log(
                        $applicantEmail, 'Your SDCC membership application was not approved',
                        'member_rejected', 'Sent', 'email', $userId, null
                    );
                } catch (\Throwable $e) {
                    Log::error('Member rejection email failed', [
                        'application_id' => $application->id,
                        'error' => $e->getMessage(),
                    ]);
                    AppNotificationLog::logFailed(
                        $applicantEmail, 'Your SDCC membership application was not approved',
                        'member_rejected', $e->getMessage(), 'email', $userId, null
                    );
                }
            }
        });

        } catch (\Throwable $e) {
            \Log::error('Member rejection failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Could not reject this membership application. The database may be missing a required column. Please contact the system administrator.',
            ], 500);
        }

        return response()->json(['success' => true, 'id' => $id, 'status' => 'rejected']);
    }

    // 24. Member Profile — Show (profile, financials, loans, payments, balance)
    public function memberShow($memberId)
    {
        $member = CoopMember::with('user')->findOrFail($memberId);

        if (! $member->user_id) {
            $loans = collect();
        } else {
            $loans = LoanApplication::where('user_id', $member->user_id)
                ->latest()
                ->get();
        }

        // Mirror applications for app_id display
        $mirrors = Application::whereIn('loan_application_id', $loans->pluck('id'))
            ->get()
            ->keyBy('loan_application_id');

        // Calculate outstanding balance
        $outstanding = $loans
            ->filter(fn($a) => $a->status === 'approved')
            ->sum('remaining_balance');

        // Get overdue loans
        $overdue = $loans
            ->filter(fn($a) => $a->status === 'approved' && $a->next_due_date && $a->next_due_date < now())
            ->values();

        // Get payments
        $payments = Payment::whereIn('loan_application_id', $loans->pluck('id'))
            ->latest()
            ->get();

        // Total paid
        $totalPaid = $payments->sum('amount_paid');

        // Active loans
        $activeLoans = $loans->filter(fn($a) => $a->status === 'approved');

        // Next due date
        $nextDue = $loans
            ->filter(fn($a) => $a->status === 'approved' && $a->next_due_date)
            ->sortBy('next_due_date')
            ->first();

        return view('admin.member-profile', compact(
            'member', 'loans', 'mirrors', 'overdue', 'payments',
            'totalPaid', 'activeLoans', 'outstanding', 'nextDue'
        ));
    }

    // 25. Member Profile — Update (profile and financials)
    public function memberUpdate(Request $request, $memberId)
    {
        $member = CoopMember::findOrFail($memberId);

        $validated = $request->validate([
            'full_name'     => 'nullable|string|max:255',
            'email'         => 'nullable|email|max:255',
            'contact_number' => 'nullable|string|max:20',
            'address'       => 'nullable|string|max:500',
            'occupation'    => 'nullable|string|max:255',
            'monthly_income' => 'nullable|numeric|min:0',
            'existing_monthly_obligations' => 'nullable|numeric|min:0',
            'savings_balance' => 'nullable|numeric|min:0',
            'approved_credit_limit' => 'nullable|numeric|min:0',
        ]);

        $member->full_name = $validated['full_name'] ?? $member->full_name;
        $member->email = $validated['email'] ?? $member->email;
        $member->contact_number = $validated['contact_number'] ?? $member->contact_number;
        $member->address = $validated['address'] ?? $member->address;
        $member->occupation = $validated['occupation'] ?? $member->occupation;
        $member->monthly_income = $validated['monthly_income'] ?? $member->monthly_income;
        $member->existing_monthly_obligations = $validated['existing_monthly_obligations'] ?? $member->existing_monthly_obligations;
        $member->savings_balance = $validated['savings_balance'] ?? $member->savings_balance;
        $member->approved_credit_limit = $validated['approved_credit_limit'] ?? $member->approved_credit_limit;
        $member->save();

        // Also update the linked user's email if it exists
        if ($member->user_id) {
            $user = User::find($member->user_id);
            if ($user) {
                $user->email = $validated['email'] ?? $user->email;
                $user->name = $validated['full_name'] ?? $user->name;
                $user->save();
            }
        }

        return redirect()->route('admin.members.show', $member->id)->with('success', 'Member profile updated successfully.');
    }

    // 26. Member Financial Profile — JSON endpoint for modal
    public function memberFinancial($memberId)
    {
        $member = CoopMember::findOrFail($memberId);

        return response()->json([
            'success' => true,
            'member_name' => $member->full_name,
            'financial' => [
                'monthly_income' => $member->monthly_income,
                'existing_monthly_obligations' => $member->existing_monthly_obligations,
                'savings_balance' => $member->savings_balance,
                'approved_credit_limit' => $member->approved_credit_limit,
            ],
        ]);
    }

    // 27. Navigation Routes
    public function repayments()
    {
        $repayments = Payment::with('loanApplication.user')
            ->latest()
            ->get();
        $totalRepayments = Payment::sum('amount_paid');
        $approvedLoans = Application::with('loanApplication.user')
            ->where('status', 'Approved')
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
        // Editable settings (read from Setting table with config fallback)
        $editable = [
            'cooperative_name'     => Setting::get('cooperative_name', config('cooperative.name', 'San Dionisio Credit Cooperative')),
            'cooperative_email'    => Setting::get('cooperative_email', config('cooperative.email', 'info@sandionisiocoop.com')),
            'cooperative_address'  => Setting::get('cooperative_address', config('cooperative.address', 'San Dionisio, Parañaque City')),
            'cooperative_phone'    => Setting::get('cooperative_phone', config('cooperative.phone', '+63 2 8XXX XXXX')),
            'annual_interest_rate' => Setting::get('annual_interest_rate', config('loan_matrix.rate', 12)),
            'template_due_soon'    => Setting::get('template_due_soon', ''),
            'template_due_today'   => Setting::get('template_due_today', ''),
            'template_overdue'     => Setting::get('template_overdue', ''),
            'template_approval'    => Setting::get('template_approval', ''),
            'template_rejection'   => Setting::get('template_rejection', ''),
        ];

        // Read-only config-driven settings (selection criteria per paper)
        $readonly = [
            'default_loan_term'    => config('loan_matrix.default_term', 12),
            'penalty_rate'         => config('loan_matrix.penalty_rate', 5),
            'min_approval_score'   => config('loan_matrix.min_score', 50),
            'high_risk_threshold'  => config('loan_matrix.high_risk', 30),
            'grace_period_days'    => config('loan_matrix.grace_days', 7),
            'reminder_frequency'   => config('loan_matrix.reminder_freq', 'weekly'),
            'dti_limit'            => config('loan_evaluation.dti_limit', 0.40),
            'risk_low'             => config('loan_evaluation.risk_low', 70),
            'risk_medium'          => config('loan_evaluation.risk_medium', 50),
        ];

        $recentAuditLogs = AuditLog::latest()->take(5)->get();
        return view('admin.settings', compact('editable', 'readonly', 'recentAuditLogs'));
    }

    public function reports(Request $request)
    {
        view()->share('format', new \App\Support\Format());
        $filters = $this->buildReportFilters($request);

        // KPI cards (always show these, unaffected by report-type filters for now)
        $totalApproved = LoanApplication::where('status', LoanStatus::APPROVED)->sum('amount') ?? 0;
        $totalRepayments = Payment::sum('amount_paid') ?? 0;
        $activeMembersCount = CoopMember::where('status', CoopMember::STATUS_ACTIVE)->count() ?? 0;
        $pendingApplicationsCount = Application::where('status', 'Pending')->count() ?? 0;

        // Build the four report sections
        $loanSummary      = $this->loanSummaryData($filters);
        $approvedRecs     = $this->approvedLoanRecordsData($filters);
        $borrowerPerf     = $this->borrowerPerformanceData($filters);
        $overduePayments  = $this->overduePaymentsData($filters);

        return view('admin.reports', compact(
            'totalApproved', 'totalRepayments', 'activeMembersCount', 'pendingApplicationsCount',
            'loanSummary', 'approvedRecs', 'borrowerPerf', 'overduePayments', 'filters'
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
        $riskLevel  = $request->query('risk_level');

        $statusWhere = null;
        if ($status !== null && $status !== '') {
            $statusWhere = fn ($q) => $q->where('status', $status);
        }

        $riskWhere = null;
        if ($riskLevel !== null && $riskLevel !== '') {
            $riskWhere = fn ($q) => $q->where('risk_level', $riskLevel);
        }

        $dateWhere = null;
        if ($startDate && $endDate) {
            // Issue B: inclusive of the end date (whereBetween treats date-only
            // end as midnight, dropping records created on the end date itself).
            $dateWhere = fn ($q) => $q->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate);
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
            'risk_level'  => $riskLevel,
            'status_closures' => ['status' => $statusWhere, 'risk' => $riskWhere, 'date' => $dateWhere],
        ];
    }

    private function applyReportFilters($query, array $filters): mixed
    {
        $closures = $filters['status_closures'] ?? [];
        if ($closures['status'] !== null) {
            $query = $closures['status']($query);
        }
        if ($closures['risk'] !== null) {
            $query = $closures['risk']($query);
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
        $base = LoanApplication::query();
        $base = $this->applyReportFilters($base, $filters);

        // ---- Status counts / sums (each on its own clone) ----
        $qStatusCount = clone $base;
        $countByStatus = $qStatusCount->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->toArray();

        $qStatusSum = clone $base;
        $byStatus = $qStatusSum->selectRaw('status, sum(amount) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->toArray();

        $qTypeCount = clone $base;
        $countByType = $qTypeCount->selectRaw('loan_type, count(*) as cnt')
            ->groupBy('loan_type')
            ->get()
            ->keyBy('loan_type')
            ->toArray();

        $qTypeSum = clone $base;
        $byType = $qTypeSum->selectRaw('loan_type, sum(amount) as total')
            ->groupBy('loan_type')
            ->get()
            ->keyBy('loan_type')
            ->toArray();

        // Normalise
        $statusTotalMap = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'paid' => 0, 'flagged' => 0];
        $countStatusMap = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'paid' => 0, 'flagged' => 0];

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

        // ---- Risk-level distribution (fresh clone) ----
        $qRisk = clone $base;
        $riskDist = $qRisk->selectRaw('risk_level, count(*) as cnt')
            ->groupBy('risk_level')
            ->get()
            ->keyBy('risk_level')
            ->toArray();

        $riskMap = [];
        foreach ($riskDist as $row) {
            $riskMap[$row['risk_level']] = (int) ($row['cnt'] ?? 0);
        }

        // ---- Totals from a fresh query (not the cloned $base) ----
        $totalCount = clone $base;
        $totalAmount = (float) $totalCount->sum('amount');

        return [
            'type'           => $filters['report_type'],
            'status_totals'  => $statusTotalMap,
            'by_status_counts' => $countStatusMap,
            'by_type'        => $typeTotalMap,
            'by_type_counts' => $countTypeMap,
            'risk_distribution' => $riskMap,
            'total_count'    => $totalCount->count(),
            'total_amount'   => $totalAmount,
        ];
    }

    /** ==================================================================
     *  Approved Loan Records report:
     *  approved/disbursed loans with member, loan type, approved amount,
     *  term, risk level, recommended amount, approval date, and approver.
     *  ================================================================== */
    private function approvedLoanRecordsData(array $filters): \Illuminate\Support\Collection
    {
        $query = LoanApplication::whereIn('status', ['approved'])
            ->with(['user.coopMember', 'application.approver']);

        $query = $this->applyReportFilters($query, $filters);

        return $query->latest('reviewed_at')
            ->get()
            ->map(function ($app) {
                $member = $app->user?->coopMember;
                $approvedAmt = $app->approved_amount ?? $app->amount;

                // Approver name (falls back to "—").
                $approverName = null;
                if ($app->application && $app->application->approver) {
                    $approverName = $app->application->approver->name ?? null;
                }

                return (object)[
                    'member_name'     => $app->fullName(),
                    'member_id'       => $app->user?->id ?? null,
                    'loan_type'       => $app->loan_type,
                    'loan_type_label' => $app->getLoanTypeLabelAttribute(),
                    'approved_amount' => Format::money($approvedAmt),
                    'term_months'     => $app->term_months,
                    'risk_level'      => $app->risk_level,
                    'recommended_amt' => Format::money($app->recommended_amount ?? 0),
                    'approved_date'   => $app->reviewed_at ? $app->reviewed_at->format('M d, Y') : 'N/A',
                    'approved_by'     => $approverName ?? '—',
                    'app_id'          => $app->application?->app_id ?? $app->reference,
                ];
            });
    }

    /** ==================================================================
     *  Overdue Payments report:
     *  disbursed/approved loans with remaining balance > 0 AND next_due_date
     *  in the past. Each row shows member, loan type, due date, overdue amount.
     *  ================================================================== */
    private function overduePaymentsData(array $filters): \Illuminate\Support\Collection
    {
        $query = LoanApplication::where('status', LoanStatus::APPROVED)
            ->where('remaining_balance', '>', 0)
            ->whereDate('next_due_date', '<', now()->toDateString())
            ->with(['user.coopMember']);

        $query = $this->applyReportFilters($query, $filters);

        return $query->latest('reviewed_at')
            ->get()
            ->map(function ($app) {
                $overdueDays = (int) now()->startOfDay()->diff(Carbon::parse($app->next_due_date)->startOfDay())->days;

                return (object)[
                    'member_name'   => $app->fullName(),
                    'member_id'     => $app->user?->id ?? null,
                    'loan_type'     => $app->loan_type,
                    'loan_type_label' => $app->getLoanTypeLabelAttribute(),
                    'amount'         => Format::money((float) $app->remaining_balance),
                    'due_date'      => $app->next_due_date?->format('M d, Y'),
                    'overdue_days'  => $overdueDays,
                    'app_id'        => $app->reference,
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
            $totalApprovedAmount = 0;
            $outstandingBalance = 0;
            $onTime = 0;
            $late = 0;
            $overdue = 0;

            foreach ($loanApps as $la) {
                // Issue C: only count approved loans for approved amount
                if ($la->status === LoanStatus::APPROVED) {
                    $totalApprovedAmount += (float) ($la->amount ?? 0);
                    $outstandingBalance += (float) ($la->remaining_balance ?? 0);
                }

                // Get ALL payments ordered chronologically for correct ordinal positions
                $allPayments = Payment::where('loan_application_id', $la->id)
                    ->orderBy('payment_date', 'asc')
                    ->get();
                $paymentCount = $allPayments->count();
                $nextDue = $la->next_due_date ?? null;

                // Issue A follow-up: when the loan is fully paid and next_due_date
                // is null, anchor the per-payment due dates using PaymentService's
                // first-due-date priority: next_due_date → disbursement_date →
                // created_at->endOfMonth().
                if (!$nextDue) {
                    $firstDueDate = $la->disbursement_date
                        ? Carbon::parse($la->disbursement_date)
                        : Carbon::parse($la->created_at)->endOfMonth();
                    $totalPaid = (float) $la->totalPaid();
                    $emi = (float) $la->monthly_payment;
                    $installmentsCovered = $emi > 0 ? (int) floor($totalPaid / $emi) : 0;
                    $computedNextDue = $firstDueDate->copy();
                    if ($installmentsCovered > 0) {
                        $computedNextDue = $computedNextDue->addMonths($installmentsCovered);
                    }
                    $nextDue = $computedNextDue->format('Y-m-d');
                }

                foreach ($allPayments as $index => $p) {
                    // Issue B: inclusive end-date filter on payment_date
                    if ($filters['start_date'] && $filters['end_date']) {
                        $pd = $p->payment_date;
                        if (!$pd || !$pd->startOfDay()->gte($filters['start_date']) || !$pd->startOfDay()->lte($filters['end_date'])) {
                            continue;
                        }
                    }

                    $totalPaid += (float) ($p->amount_paid ?? 0);

                    // Issue A: anchor per-payment due date backwards from next_due_date
                    // next_due_date = due date for installment ($paymentCount + 1).
                    // Payment (#$index+1) due date = next_due_date - ($paymentCount - $index) months.
                    $dueDate = null;
                    if ($nextDue) {
                        $dueDate = Carbon::parse($nextDue)->subMonths($paymentCount - $index)->startOfDay();
                    }

                    if ($dueDate && $p->payment_date) {
                        // diffInDays(signed): payment_date - dueDate
                        //   >= 0 = payment on or before due date → on-time
                        //   < 0  = payment after due date → late
                        // Overdue when > 30 days late (diffDays < -30)
                        $diffDays = (int) $p->payment_date->startOfDay()->diffInDays($dueDate, false);
                        if ($diffDays >= 0) {
                            $onTime++;
                        } else {
                            $late++;
                            if ($diffDays < -30) {
                                $overdue++;
                            }
                        }
                    }
                }
            }

            $label = 'Good';
            if ($overdue > 0) {
                $label = 'Poor';
            } elseif ($late > $onTime) {
                $label = 'Fair';
            }

            return (object)[
                'member_name'   => $member->full_name ?? 'Unknown',
                'member_id'     => $member->id,
                'total_loans'   => $loanApps->count(),
                'total_approved_amount' => Format::money($totalApprovedAmount),
                'total_paid'    => Format::money($totalPaid),
                'outstanding'   => Format::money($outstandingBalance),
                'on_time_count' => $onTime,
                'late_count'    => $late,
                'overdue_count' => $overdue,
                'performance_label' => $label,
            ];
        });
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

        // Only allow payments on approved loans
        if (!LoanStatus::isApproved($application->status)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Repayments can only be made on approved loans.',
                ], 422);
            }
            return back()->with('error', 'Repayments can only be made on approved loans.');
        }

        $amount = (float) $validated['amount'];

        // Over-payment guard: payment cannot exceed remaining balance
        if ($amount > ($application->remaining_balance ?? 0)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount exceeds remaining balance.',
                ], 422);
            }
            return back()->with('error', 'Payment amount exceeds remaining balance.');
        }

        // Duplicate reference check: prevent duplicate reference numbers for the same loan
        if (! empty($validated['reference_no'])) {
            $duplicateRef = Payment::where('loan_application_id', $application->loan_application_id)
                ->where('reference_no', $validated['reference_no'])
                ->exists();
            if ($duplicateRef) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A payment with this reference number already exists for this loan.',
                    ], 422);
                }
                return back()->with('error', 'A payment with this reference number already exists for this loan.');
            }
        }

        // Duplicate amount+date check: prevent payments with the same amount and date
        $paymentDate = $validated['payment_date'] ?? now()->toDateString();
        $duplicateAmountDate = Payment::where('loan_application_id', $application->loan_application_id)
            ->where('amount_paid', $amount)
            ->whereDate('payment_date', $paymentDate)
            ->exists();
        if ($duplicateAmountDate) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'A payment with the same amount and date already exists for this loan.',
                ], 422);
            }
            return back()->with('error', 'A payment with the same amount and date already exists for this loan.');
        }

        $payment = null;

        DB::transaction(function () use ($application, $validated, $amount, &$payment) {
            // Create Payment record
            $newRemainingBalance = (float) ($application->remaining_balance ?? 0) - $amount;

            $payment = Payment::create([
                'loan_application_id' => $application->loan_application_id,
                'user_id'             => $application->user_id,
                'amount_paid'         => $amount,
                'remaining_balance_after' => $newRemainingBalance,
                'reference_no'        => $validated['reference_no'] ?? null,
                'payment_date'        => $validated['payment_date'] ?? now(),
                'payment_method'      => $validated['payment_method'] ?? null,
                'notes'               => $validated['notes'] ?? null,
                'encoded_by'          => Auth::id(),
            ]);

            // Recompute loan state using the single source of truth service.
            // The new payment is now in the DB, so it will be counted in total_paid.
            $loanApplication = $application->loan_application_id
                ? LoanApplication::where('id', $application->loan_application_id)->lockForUpdate()->first()
                : null;

            if ($loanApplication) {
                app(PaymentService::class)->recomputeLoanState($loanApplication);
            }

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
                'payment_id' => $payment->id,
                'remaining_balance' => (float) ($application->remaining_balance ?? 0),
            ])
            : redirect()->route('repayments.index')->with('success', 'Repayment recorded successfully.');
    }

    // 27. Update an existing repayment record
    public function updateRepayment(Request $request, int $id)
    {
        $repayment = Payment::findOrFail($id);

        $loanApplication = null;
        if ($repayment->loan_application_id) {
            $loanApplication = LoanApplication::where('id', $repayment->loan_application_id)
                ->lockForUpdate()
                ->first();
        }

        $application = null;
        if ($loanApplication) {
            $application = Application::where('loan_application_id', $loanApplication->id)->first();
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|max:100',
            'reference_no' => 'nullable|string|max:255',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $newAmount = (float) $validated['amount'];
        $oldAmount = (float) $repayment->amount_paid;

        // Duplicate reference check: excludes the payment being edited
        if (!empty($validated['reference_no'])) {
            $exists = Payment::where('loan_application_id', $repayment->loan_application_id)
                ->where('reference_no', $validated['reference_no'])
                ->where('id', '!=', $repayment->id)
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

        // Duplicate amount+date check: excludes the payment being edited
        $paymentDate = $validated['payment_date'] ?? $repayment->payment_date;
        $exists = Payment::where('loan_application_id', $repayment->loan_application_id)
            ->where('amount_paid', $newAmount)
            ->whereDate('payment_date', $paymentDate)
            ->where('id', '!=', $repayment->id)
            ->exists();
        if ($exists) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'A payment with the same amount and date already exists for this loan.',
                ], 422);
            }
            return back()->with('error', 'A payment with the same amount and date already exists for this loan.');
        }

        // Over-payment rejection: total_paid_after_edit = current total paid - old_amount + new_amount
        if ($loanApplication) {
            $totalPayable = $loanApplication->totalPayable();
            $totalPaid = Payment::where('loan_application_id', $loanApplication->id)
                ->sum('amount_paid');
            $totalPaidAfterEdit = $totalPaid - $oldAmount + $newAmount;

            if ($totalPaidAfterEdit > $totalPayable) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Total paid would exceed total payable.',
                    ], 422);
                }
                return back()->with('error', 'Total paid would exceed total payable.');
            }
        }

        // Capture old values for audit log before update
        $oldValues = $repayment->toArray();

        DB::transaction(function () use ($repayment, $validated, $newAmount, $oldAmount, $loanApplication, $oldValues) {
            // Update only editable fields.
            $repayment->update([
                'amount_paid' => $newAmount,
                'payment_method' => $validated['payment_method'] ?? null,
                'reference_no' => $validated['reference_no'] ?? null,
                'payment_date' => $validated['payment_date'] ?? $repayment->payment_date,
                'notes' => $validated['notes'] ?? null,
            ]);

            AuditLog::log('Repayment Updated', "Repayment #{$repayment->id} updated for loan application #{$repayment->loan_application_id}", $repayment->user_id ?? null,
                $oldValues,
                $repayment->toArray());
        });

        // Recompute loan state after update. The payment has already been
        // updated in the DB with the new amount, so recomputeLoanState will
        // sum all payments including the updated one — no exclusion needed.
        if ($loanApplication) {
            app(PaymentService::class)->recomputeLoanState($loanApplication);
        }

        return $request->expectsJson()
            ? response()->json([
                'success' => true,
                'message' => 'Repayment updated successfully.',
                'remaining_balance' => $loanApplication ? (float) ($loanApplication->remaining_balance ?? 0) : 0,
            ])
            : redirect()->route('repayments.index')->with('success', 'Repayment updated successfully.');
    }

    // 28. Update application settings (persists to the Setting model)
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            // Editable settings
            'cooperative_name'     => 'required|string|max:255',
            'cooperative_email'    => 'required|email|max:255',
            'cooperative_address'  => 'required|string|max:500',
            'cooperative_phone'    => ['nullable', 'string', new \App\Rules\PhilippineMobileNumber, 'max:20'],
            'annual_interest_rate' => 'required|integer|min:1|max:100',
            'template_due_soon'    => 'sometimes|string',
            'template_due_today'   => 'sometimes|string',
            'template_overdue'     => 'sometimes|string',
            'template_approval'    => 'sometimes|string',
            'template_rejection'   => 'sometimes|string',
            // Read-only settings (displayed but not persisted via this form)
            'default_loan_term'    => 'sometimes|integer|min:1|max:60',
            'penalty_rate'         => 'sometimes|integer|min:0|max:100',
            'min_approval_score'   => 'sometimes|integer|min:1|max:100',
            'high_risk_threshold'  => 'sometimes|integer|min:1|max:100',
            'grace_period_days'    => 'sometimes|integer|min:0|max:365',
            'reminder_frequency'   => 'sometimes|string',
        ]);

        // Persist editable settings to the Setting model (audit-logged via the model's booted hooks)
        Setting::set('cooperative_name', $validated['cooperative_name']);
        Setting::set('cooperative_email', $validated['cooperative_email']);
        Setting::set('cooperative_address', $validated['cooperative_address']);
        Setting::set('cooperative_phone', $validated['cooperative_phone'] ?? '');
        Setting::set('annual_interest_rate', $validated['annual_interest_rate']);
        Setting::set('template_due_soon', $validated['template_due_soon'] ?? '');
        Setting::set('template_due_today', $validated['template_due_today'] ?? '');
        Setting::set('template_overdue', $validated['template_overdue'] ?? '');
        Setting::set('template_approval', $validated['template_approval'] ?? '');
        Setting::set('template_rejection', $validated['template_rejection'] ?? '');

        // Read-only settings are config-driven and not persisted here.

        return back()
            ->with('success', 'Settings updated successfully.');
    }

    // 28. Export reports to PDF
    public function exportPdf(Request $request)
    {
        $filters = $this->buildReportFilters($request);
        $type = $filters['report_type'];

        $loanSummary      = $this->loanSummaryData($filters);
        $approvedRecs     = $this->approvedLoanRecordsData($filters);
        $borrowerPerf     = $this->borrowerPerformanceData($filters);
        $overduePayments  = $this->overduePaymentsData($filters);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.report-pdf', [
            'type'           => $type,
            'filters'        => $filters,
            'generatedAt'    => now(),
            'loanSummary'    => $loanSummary,
            'approvedRecs'   => $approvedRecs,
            'borrowerPerf'   => $borrowerPerf,
            'overduePayments'=> $overduePayments,
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

            $headers = ['Member', 'Total Loans', 'Total Approved', 'Total Paid', 'Outstanding', 'On-time', 'Late', 'Overdue', 'Performance'];
            foreach ($headers as $i => $h) {
                $sheet->setCellValue(chr(65 + $i) . '4', $h);
            }

            $row = 5;
            foreach ($rows as $app) {
                $sheet->setCellValue('A' . $row, $app->member_name ?? 'N/A');
                $sheet->setCellValue('B' . $row, $app->total_loans ?? 0);
                $sheet->setCellValue('C' . $row, $app->total_approved_amount ?? '0');
                $sheet->setCellValue('D' . $row, $app->total_paid ?? '0');
                $sheet->setCellValue('E' . $row, $app->outstanding ?? '0');
                $sheet->setCellValue('F' . $row, $app->on_time_count ?? 0);
                $sheet->setCellValue('G' . $row, $app->late_count ?? 0);
                $sheet->setCellValue('H' . $row, $app->overdue_count ?? 0);
                $sheet->setCellValue('I' . $row, $app->performance_label ?? 'N/A');
                $row++;
            }
        } elseif ($type === 'overdue_payments') {
            $rows = $this->overduePaymentsData($filters);

            $sheet->setCellValue('A1', 'Overdue Payments Report');
            $sheet->setCellValue('A2', 'Generated: ' . now()->format('Y-m-d H:i:s'));

            $headers = ['App ID', 'Member', 'Loan Type', 'Overdue Amount', 'Due Date', 'Overdue Days'];
            foreach ($headers as $i => $h) {
                $sheet->setCellValue(chr(65 + $i) . '4', $h);
            }

            $row = 5;
            foreach ($rows as $app) {
                $sheet->setCellValue('A' . $row, $app->app_id ?? '');
                $sheet->setCellValue('B' . $row, $app->member_name ?? 'N/A');
                $sheet->setCellValue('C' . $row, $app->loan_type_label ?? 'N/A');
                $sheet->setCellValue('D' . $row, (float) ($app->amount ?? 0));
                $sheet->setCellValue('E' . $row, $app->due_date ?? 'N/A');
                $sheet->setCellValue('F' . $row, $app->overdue_days ?? 0);
                $row++;
            }
        } else {
            $sheet->setCellValue('A1', 'Report: ' . $type);
        }

        $filename = 'report_' . $type . '_' . now()->format('Y-m-d') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        // Write to a temp file instead of php://output so the binary bytes
        // never leak into stdout (which is what made Excel exports noisy in
        // the test runner). The file is read back and deleted immediately.
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $writer->save($tmpFile);
        $content = file_get_contents($tmpFile);
        unlink($tmpFile);

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function notificationLogs()
    {
        $logs = AppNotificationLog::latest()
            ->when(request('search'), fn ($q, $s) => $q->where('subject', 'like', "%{$s}%")->orWhere('recipient_email', 'like', "%{$s}%"))
            ->when(request('status'), fn ($q, $s) => $q->where('status', $s))
            ->when(request('type'), fn ($q, $t) => $q->where('notification_type', $t))
            ->when(request('channel'), fn ($q, $c) => $q->where('channel', $c))
            ->when(request('start_date'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when(request('end_date'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->paginate(20)
            ->withQueryString();

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

    /**
     * Compute the credit limit recommendation for a membership application.
     *
     * Uses raw income/obligations/savings from the application so the
     * recommendation does not depend on an existing CoopMember record.
     *
     * @return array{limit: float, cappedBy: string|int|null}
     */
    private function creditLimitRecommendationForApplication(MemberApplication $application): array
    {
        $income = (float) ($application->monthly_income ?? 0);
        $obligations = (float) ($application->existing_monthly_obligations ?? 0);
        $savings = (float) ($application->savings_balance ?? 0);

        return app(CreditLimitService::class)->calculateCreditLimitRaw(
            $income,
            $obligations,
            $savings,
            config('loan_matrix.credit_limit.term', 36),
            config('loan_matrix.interest.monthly_rate', 0.01)
        );
    }

    // 22. Recommendation for a membership application
    //
    // Returns the credit-limit recommendation (limit and cappedBy) for a
    // membership application, so the membership modal can render
    // "Recommended credit limit: ₱X (affordability)" and the inline approve
    // panel can pre-fill the amount input with this value.
    public function membershipCreditLimitRecommendation($id)
    {
        $application = MemberApplication::findOrFail($id);
        $recommendation = $this->creditLimitRecommendationForApplication($application);

        return response()->json([
            'success' => true,
            'application_id' => $application->id,
            'recommendation' => $recommendation,
            'monthly_income' => (float) ($application->monthly_income ?? 0),
            'existing_monthly_obligations' => (float) ($application->existing_monthly_obligations ?? 0),
            'savings_balance' => (float) ($application->savings_balance ?? 0),
        ]);
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

        // Credit limit data for the member linked to this application
        $approvedLimit = null;
        $outstanding = 0;
        if ($loanApp) {
            // Find coopMember via user relationship or email fallback
            $coopMember = $loanApp->user?->coopMember;
            if (!$coopMember && $loanApp->email) {
                $coopMember = CoopMember::where('email', $loanApp->email)->first();
            }
            if ($coopMember) {
                $approvedLimit = $coopMember->approved_credit_limit;
                // Sum remaining_balance of all approved loans for this member via
                // the shared CoopMember::outstandingBalance() helper, which covers
                // both user_id-linked loans and staff-encoded user_id=null loans.
                $outstanding = CoopMember::outstandingBalance($coopMember);
            }
        }

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
                'savings_balance' => $loanApp ? (float) $loanApp->savings_balance : 0,
                'approved_credit_limit' => $approvedLimit ? (float) $approvedLimit : null,
                'outstanding_balance' => $outstanding,
                'available_credit' => $approvedLimit ? (float) $approvedLimit - $outstanding : null,
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
        $rate = (float) (Setting::get('annual_interest_rate', config('loan_matrix.rate', 12)) / 100);

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
        $rate = (float) (Setting::get('annual_interest_rate', config('loan_matrix.rate', 12)) / 100);

        $service = app(\App\Services\AmortizationService::class);
        $schedule = $service->schedule($principal, $termMonths, $rate);
        $summary = $service->summary($principal, $termMonths, $rate);

        $html = view('admin.amortization-pdf', compact('application', 'loanApp', 'schedule', $summary))->render();

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'inline; filename="amortization-' . $application->app_id . '.html"',
        ]);
    }

    public function backup()
    {
        $backupDir = base_path('storage/app/backups');
        $backups = [];

        if (is_dir($backupDir)) {
            $files = glob("{$backupDir}/backup_*.sql.gz");
            usort($files, function ($a, $b) {
                return filemtime($b) <=> filemtime($a);
            });

            foreach ($files as $file) {
                $backups[] = [
                    'filename' => basename($file),
                    'size'     => filesize($file),
                    'date'     => date('Y-m-d H:i:s', filemtime($file)),
                ];
            }
        }

        return view('admin.backup', compact('backups'));
    }

public function createBackup(Request $request)
{
    try {
        $backupDir = base_path('storage/app/backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $database = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
        $filename = "backup_{$database}_" . now()->format('Y-m-d_H-i-s') . '.sql.gz';

        \App\Support\DatabaseDumper::dumpToGzip($backupDir . '/' . $filename);

        // Keep only the 14 newest backups
        $files = glob($backupDir . '/backup_*.sql.gz') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, 14) as $old) {
            @unlink($old);
        }

        return redirect()->route('admin.backup')
            ->with('status', "Backup created: {$filename}");
    } catch (\Throwable $e) {
        \Log::error('Manual backup failed: ' . $e->getMessage());

        return redirect()->route('admin.backup')
            ->with('error', 'Backup failed: ' . $e->getMessage());
    }
}

    /**
     * Download a stored backup file.
     * Validates the filename against a strict whitelist pattern — no path traversal.
     */
    public function downloadBackup($filename)
    {
        // Validate filename: backup_{database}_{timestamp}.sql.gz — no directory traversal
        if (!preg_match('/^backup_[a-zA-Z0-9_]+_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql\.gz$/', $filename)) {
            abort(404, 'Backup file not found.');
        }

        $backupDir = base_path('storage/app/backups');
        $filepath = $backupDir . '/' . $filename;

        if (!file_exists($filepath) || realpath($filepath) !== realpath($filepath) || strpos(realpath($filepath), realpath($backupDir)) !== 0) {
            abort(404, 'Backup file not found.');
        }

        return response()->download($filepath);
    }

    /**
     * Restore the database from a selected backup file.
     *
     * Safeguards:
     * - Admin-only (route middleware enforces role)
     * - Requires confirmation text "RESTORE"
     * - Creates a fresh backup before restoring
     * - Filename validated against strict whitelist — no path traversal, no arbitrary files
     * - Respects the DatabaseProtectionProvider: restore of the real 'sdcc' database
     *   is refused unless ALLOW_PROTECTED_DB=1 is set explicitly on the command line.
     * - Writes an audit log entry
     */
    public function restoreBackup(Request $request)
    {
        $validated = $request->validate([
            'filename'    => 'required|string|max:255',
            'confirm'     => 'required|string',
            'confirmation' => 'nullable|string',
        ]);

        // Require explicit confirmation text "RESTORE"
        $confirmation = $validated['confirmation'] ?? $validated['confirm'] ?? '';
        if (strtoupper(trim($confirmation)) !== 'RESTORE') {
            return response()->json([
                'success' => false,
                'message' => 'Confirmation failed. Please type RESTORE to confirm.',
            ], 422);
        }

        $filename = $validated['filename'];

        // Strict filename validation: no path traversal, only managed backup files
        if (!preg_match('/^backup_[a-zA-Z0-9_]+_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql\.gz$/', $filename)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid backup file name.',
            ], 422);
        }

        $backupDir = base_path('storage/app/backups');
        $filepath = $backupDir . '/' . $filename;

        // Final safety: ensure the file is within the managed backups directory
        if (!file_exists($filepath)) {
            return response()->json([
                'success' => false,
                'message' => 'Selected backup file does not exist.',
            ], 404);
        }

        $realPath = realpath($filepath);
        $realDir = realpath($backupDir);
        if ($realPath === false || $realDir === false || strpos($realPath, $realDir) !== 0) {
            return response()->json([
                'success' => false,
                'message' => 'Selected file is outside the backup directory.',
            ], 422);
        }

        try {
            // ── Check DatabaseProtectionProvider guard ──
            $databaseName = config('database.connections.' . config('database.default') . '.database');
            $protectedDatabases = config('sdcc.protected_databases', ['sdcc']);
            $protectedDatabases = array_map('strtolower', array_map('trim', $protectedDatabases));

            $isProtected = in_array(strtolower($databaseName), $protectedDatabases, true);
            $allowProtected = (bool) env('ALLOW_PROTECTED_DB', false);

            if ($isProtected && !$allowProtected) {
                AuditLog::log('DB Restore Blocked', "Attempt to restore from {$filename} on protected database '{$databaseName}' was blocked by DatabaseProtectionProvider.", null, ['filename' => $filename, 'database' => $databaseName]);

                return response()->json([
                    'success' => false,
                    'message' => "This action is blocked. Restoring the real '{$databaseName}' database requires ALLOW_PROTECTED_DB=1. Use --env=scratch for testing.",
                ], 403);
            }

            // ── Pre-restore: create a fresh backup ──
            // Only the mysqldump-based sdcc:backup command is available; on
            // SQLite there is no dump tool, so we skip the pre-restore backup
            // there rather than fail the restore.
            $driver = DB::connection()->getDriverName();
            if ($driver === 'mysql') {
                \Artisan::call('sdcc:backup');
            }

            // ── Perform restore ──

            if ($driver === 'mysql') {
                $host = config('database.connections.mysql.host', 'localhost');
                $port = config('database.connections.mysql.port', 3306);
                $username = config('database.connections.mysql.username', 'root');
                $password = config('database.connections.mysql.password', '');
                $database = $databaseName;

                $mysqldumpPath = $this->findMysqldumpPath();
                if (!$mysqldumpPath) {
                    throw new \RuntimeException('Could not find mysqldump for restore.');
                }

                $cmd = "{$mysqldumpPath} -h {$host} -P {$port} -u {$username}";
                if ($password) {
                    $cmd .= " -p{$password}";
                }
                $cmd .= " {$database} < " . escapeshellarg($filepath);

                $process = new \Symfony\Component\Process\Process($cmd);
                $process->run();

                if (!$process->isSuccessful()) {
                    throw new \RuntimeException('Restore failed: ' . $process->getErrorOutput());
                }
            } else {
                // SQLite: decompress and execute
                $sql = gzdecode(file_get_contents($filepath));
                if ($sql === false) {
                    throw new \RuntimeException('Could not decompress backup file.');
                }

                DB::unprepared($sql);
            }

            AuditLog::log('DB Restore', "Restored database from backup file: {$filename}", null, ['filename' => $filename, 'database' => $databaseName]);

            return response()->json([
                'success' => true,
                'message' => "Database successfully restored from {$filename}.",
            ]);
        } catch (\Throwable $e) {
            \Log::error('DB Restore error: ' . $e->getMessage(), ['exception' => $e]);

            AuditLog::log('DB Restore Failed', "Failed to restore from {$filename}: {$e->getMessage()}", null, ['filename' => $filename, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Restore failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Find mysqldump binary path.
     */
    protected function findMysqldumpPath(): ?string
    {
        $path = exec('which mysqldump 2>/dev/null');
        if ($path) {
            return trim($path);
        }
        $xamppPath = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        if (file_exists($xamppPath)) {
            return $xamppPath;
        }
        return null;
    }

    public function checkDuplicate(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email'     => 'required|email|max:255',
            'phone'     => ['nullable', 'string', new \App\Rules\PhilippineMobileNumber, 'max:20'],
            'exclude_id' => 'nullable|integer',
        ]);

        $check = app(DuplicateMemberService::class)->check(
            $validated['full_name'],
            $validated['email'],
            $validated['phone'] ?? null,
            $validated['exclude_id'], // exclude this member (coop_members.id) from the check
            null
        );

        return response()->json([
            'is_duplicate' => $check['isDuplicate'],
            'is_warning'   => $check['isWarning'],
            'message'      => $check['message'],
            'matched_fields' => $check['matchedFields'],
        ]);
    }

    protected function computeRiskMetrics($application,$loanApp): array
    {
        $monthlyIncome = (float) ($loanApp?->monthly_income ?? 0);
        $amount = (float) ($application?->amount ?? 0);
        $termMonths = (int) ($loanApp?->term_months ?? 12);
        $memberSavings = (float) ($loanApp?->savings_balance ?? 0);
        $existingOblig = (float) ($loanApp?->existing_monthly_obligations ?? 0);
        $monthlyIncome = (float) ($loanApp?->monthly_income ?? 0);
        $amount = (float) ($application?->amount ?? 0);
        $termMonths = (int) ($loanApp?->term_months ?? 12);

        // Delegate EMI calculation to AmortizationService
        $emi = $amount > 0 && $termMonths > 0 ? app(AmortizationService::class)->emi($amount, $termMonths) : 0;
        $dti = $monthlyIncome > 0 ? round(($emi / $monthlyIncome) * 100, 2) : null;
        $savingsPool = $memberSavings;  // savings balance replaces share capital + savings
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
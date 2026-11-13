<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessLoanDocuments;
use App\Models\AppNotification;
use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\MemberApplication;
use App\Models\Payment;
use App\Rules\MultipleOfThousand;
use App\Support\LoanStatus;
use App\Services\AmortizationService;
use App\Services\CreditLimitService;
use App\Services\LoanEvaluationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

class UserController extends Controller
{
    /**
     * The member portal dashboard.
     */
    public function dashboard()
    {
        $user = Auth::user();

        $applications = LoanApplication::where('user_id', $user->id)
            ->latest()
            ->get();

        $summary = [
            'total'    => $applications->count(),
            'pending'  => $applications->where('status', 'pending')->count(),
            'approved' => $applications->where('status', 'approved')->count(),
            'borrowed' => $applications->where('status', 'approved')->sum('amount'),
        ];

        // Unread payment reminders for the bell badge and the
        // "Active Payment Reminders" panel (created by reminders:send-due).
        $unreadNotifications = AppNotification::where('user_id', $user->id)
            ->where(function ($q) {
                $q->where('is_read', false)->orWhereNull('is_read');
            })
            ->latest()
            ->take(10)
            ->get();

        return view('user.index', compact('user', 'applications', 'summary', 'unreadNotifications'));
    }

    /**
     * The applicant's own membership status.
     *
     * A registered non-member sees the state of their membership application
     * and the link to the form. One pending application at a time.
     */
    public function membership()
    {
        $user = Auth::user();

        // If the user already has the 'member' role, redirect to dashboard
        if ($user->isActiveCoopMember()) {
            return redirect()->route('user.dashboard')
                ->with('info', 'You are already a member.');
        }

        $application = MemberApplication::where('user_id', $user->id)
            ->latest('id')
            ->first();

        return view('user.membership', compact('user', 'application'));
    }

    /**
     * Show the loan application form.
     */
    public function createApplication()
    {
        $user = Auth::user();

        // If user already has the 'member' role (active coop membership), proceed
        if ($user->isActiveCoopMember()) {
            $coopMember = $user->coopMember;

            // Sum outstanding balance of all approved loans for this member.
            // Shared with the admin side via CoopMember::outstandingBalance()
            // so the email fallback for staff-encoded loans is identical.
            $outstanding = $coopMember ? CoopMember::outstandingBalance($coopMember) : 0;

            return view('user.loan', [
                'user'      => $user,
                'loanTypes' => Config::get('loan_matrix.loan_types'),
                'coopMember' => $coopMember,
                'outstanding' => $outstanding,
            ]);
        }

        // If user has APPLY_MEMBERSHIP permission (role 'user'), send to membership page
        if (\App\Support\Permissions::has($user, \App\Support\Permissions::APPLY_MEMBERSHIP)) {
            return redirect()
                ->route('user.membership')
                ->with('error', 'You must become an active cooperative member first before you can apply for a loan. Click “Become a member” to start your membership application.');
        }

        // Inactive coop member or member without active record: redirect to membership page
        // so they can reactivate/renew their membership rather than going to dashboard.
        if ($user->usertype === 'member') {
            return redirect()
                ->route('user.membership')
                ->with('error', 'Your membership is currently inactive. Please reactivate your membership before applying for a loan.');
        }

        // Registered non-member with no membership link
        return redirect()->route('user.dashboard')
            ->with('error', 'Your account is not linked to an active membership record. Please contact the cooperative office.');
    }

    /**
     * Resolve the numeric maximum loan amount for a matrix product.
     * Returns null when the limit is dynamic (depends on share capital).
     */
    public static function maxAmountFor(string $loanType): ?float
    {
        $rule = Config::get('loan_matrix.matrix.' . $loanType . '.max_amount');
        if (is_numeric($rule)) {
            return (float) $rule;
        }
        return null;
    }

    /**
     * Validate and store a submitted application.
     */
    public function storeApplication(Request $request)
    {
        // Defense-in-depth: never accept a loan submission from somebody who
        // is not an active cooperative member, even if the form page is
        // bypassed by a direct POST.
        $user = Auth::user();
        if (! $user->isActiveCoopMember()) {
            if (\App\Support\Permissions::has($user, \App\Support\Permissions::APPLY_MEMBERSHIP)) {
                return redirect()
                    ->route('user.membership')
                    ->with('error', 'You must become an active cooperative member first before you can apply for a loan.');
            }

            return redirect()->route('user.dashboard')
                ->with('error', 'Your account is not linked to an active membership record. Please contact the cooperative office.');
        }

        $loanType = $request->input('loan_type');
        $matrix   = Config::get('loan_matrix.matrix.' . $loanType);

        if (!$matrix) {
            return back()->withInput()
                ->withErrors(['loan_type' => 'Invalid loan product selected.']);
        }

        $maxAmount = self::maxAmountFor($loanType);
        $maxAmount = min($maxAmount ?? 2000000, 2000000);
        $maxAmountRule = ['max:' . $maxAmount];
        $amountMinRule = $maxAmount < 1000 ? ['min:1'] : ['min:1000'];

        $termRule = ['required', 'integer', 'in:' . implode(',', $matrix['terms'])];

        $rules = [
            'full_name'                  => ['required', 'string', 'max:255'],
            'email'                      => ['required', 'email', 'max:255'],
            'contact_number'             => ['nullable', new \App\Rules\PhilippineMobileNumber],
            'address'                    => ['nullable', 'string', 'max:500'],
            'birth_date'                 => ['nullable', 'date'],
            'source_of_income'           => ['required', 'in:' . implode(',', Config::get('loan_matrix.form_options.source_of_income'))],
            'monthly_income'             => ['required', 'numeric', 'min:1'],
            'existing_monthly_obligations' => ['required', 'numeric', 'min:0'],
            'purpose_of_loan'            => ['required', 'in:Educational,Business,Personal,Emergency'],
            'loan_type'                  => ['required', 'in:' . implode(',', array_keys(Config::get('loan_matrix.loan_types')))],
            'amount'                     => ['required', 'numeric', new MultipleOfThousand, ...$amountMinRule, ...$maxAmountRule],
            'term_months'                => $termRule,
            'required_documents'         => ['nullable', 'array', 'max:10'],
            'required_documents.*'       => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'agree'                      => ['accepted'],
        ];

        $messages = [
            'agree.accepted'      => 'Please confirm your details are true and correct.',
            'amount.min'          => 'The smallest loan we release is ₱1,000.',
            'amount.max'          => 'Maximum amount for this product is ₱' . number_format($maxAmount ?? 2000000, 2),
            'term_months.in'      => 'This product only supports ' . implode(', ', $matrix['terms']) . '-month terms.',
        ];

        $data = $request->validate($rules, $messages);

        // Override full_name and email from member profile — these are read-only
        // on the form for logged-in members, so the server trusts the profile.
        $data['full_name'] = Auth::user()->coopMember ? Auth::user()->coopMember->full_name : Auth::user()->name;
        $data['email'] = Auth::user()->coopMember ? Auth::user()->coopMember->email : Auth::user()->email;

        // Consent validation: require document_consent when documents are uploaded
        if ($request->hasFile('required_documents') && ! $request->boolean('document_consent')) {
            return back()->withInput()->withErrors([
                'document_consent' => 'You must consent to your uploaded documents being scanned for verification. '
                    . 'The AI check is advisory; recommended amounts and risk levels are rule-based and unchanged.',
            ]);
        }

        // Auto-fill from member application record
        $memberApp = Auth::user()->memberApplication;
        $data['contact_number'] = ($memberApp?->contact_number ?? Auth::user()->phone ?? 'N/A');
        $data['address'] = ($memberApp?->street ?? Auth::user()->address ?? 'N/A');
        $data['birth_date'] = (Auth::user()->birthdate ?? null);

        // Set employment_status from memberApplication's occupation field
        $data['employment_status'] = ($memberApp?->occupation ?? 'Employed');

        // Block a second application while one is still under review.
        $hasPending = LoanApplication::where('user_id', Auth::id())
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return back()
                ->withInput()
                ->with('error', 'You already have an application under review. Wait for it to be decided before applying again.');
        }

        unset($data['agree']);

        $requestedAmount = (float) $data['amount'];
        $declaredIncome  = (float) $data['monthly_income'];
        $termMonths      = (int) ($data['term_months'] ?? 12);

        // ─── MONTHLY CAPACITY GATE (BEFORE ANY SIDE EFFECT) ──────────────────
        // One shared rule (CoopMember::maxRequestablePrincipal) so this path
        // agrees with staff encode, approve and evaluation.
        $coopMember = Auth::user()->coopMember;
        if ($coopMember) {
            $monthlyAvailable = $coopMember->monthlyAvailable();
            if ($monthlyAvailable === null) {
                return back()->withInput()
                    ->withErrors(['amount' => 'Your monthly credit capacity has not been assessed. Please contact the cooperative office.']);
            }
            if ($monthlyAvailable <= 0) {
                return back()->withInput()
                    ->withErrors(['amount' => sprintf(
                        'No monthly capacity left: used ₱%s of ₱%s.',
                        number_format($coopMember->monthlyUsed(), 2),
                        number_format((float) $coopMember->monthlyCapacity(), 2)
                    )]);
            }
            $maxRequestable = $coopMember->maxRequestablePrincipal($termMonths);
            if ($maxRequestable !== null && $requestedAmount > $maxRequestable) {
                return back()->withInput()
                    ->withErrors(['amount' => sprintf(
                        'Your amount exceeds the maximum your monthly capacity allows (₱%s for %d months).',
                        number_format($maxRequestable, 2),
                        $termMonths
                    )]);
            }
        }

        // ─── AVAILABLE CREDIT CHECK (BEFORE ANY SIDE EFFECT) ───────────────
        // For limit-based products, reject requests above the member's
        // available credit. The form's max hint is advisory only.
        if ($coopMember && \App\Support\LoanProductHelper::requiresCreditLimit($loanType)) {
            $availableCredit = $coopMember->availableCredit();
            if ($availableCredit !== null && $requestedAmount > $availableCredit) {
                return back()->withInput()
                    ->with('error', 'Your requested amount exceeds your available credit of ₱' . number_format($availableCredit, 2) . '. Please contact the cooperative office.');
            }
        }

        // ─── STORE UPLOADED DOCUMENTS (only after every gate passes) ───────
        $documentPaths = [];
        if ($request->hasFile('required_documents')) {
            foreach ($request->file('required_documents') as $document) {
                $path = $document->store('loan-documents', 'public');
                $documentPaths[] = $path;
            }
        }
        $data['documents'] = $documentPaths;

        $data['user_id']   = Auth::id();
        $data['reference'] = LoanApplication::makeReference();
        $data['status']    = 'pending';

        $application = LoanApplication::create($data);

        // ─── RULE-BASED EVALUATION (SYNCHRONOUS) ───
        $evaluation  = app(LoanEvaluationService::class)->evaluate($application);
        $riskFactors = is_array($evaluation['details'] ?? null)
            ? json_encode($evaluation['details'])
            : null;

        $application->update([
            'recommended_amount'  => $evaluation['recommended_amount'],
            'risk_level'          => $evaluation['risk_level'],
            'risk_score'          => $evaluation['risk_score'],
            'risk_factors'        => $riskFactors,
            'evaluation_reasons'  => json_encode($evaluation['evaluation_reasons'] ?? $evaluation['reasons']),
        ]);

        // ─── MIRROR INTO THE ADMIN APPLICATIONS TABLE ───
        $lastId = Application::withTrashed()->max('id') ?? 0;
        $appId  = 'APP-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

        Application::create([
            'loan_application_id'   => $application->id,
            'app_id'                => $appId,
            'applicant'             => $application->full_name,
            'loan_type'             => $application->loan_type,
            'amount'                => $application->amount,
            'term_months'           => $application->term_months,
            'status'                => 'Pending',
            'recommended_amount'    => $evaluation['recommended_amount'],
            'risk_level'            => $evaluation['risk_level'],
            'risk_score'            => $evaluation['risk_score'],
            'risk_factors'          => $riskFactors,
            'evaluation_reasons'    => json_encode($evaluation['evaluation_reasons'] ?? $evaluation['reasons']),
            'ai_score'              => null, // filled in by the document analysis job
        ]);

        // ─── AI DOCUMENT ANALYSIS ───
        // Runs after the mirror row exists so the job can copy ai_score into it.
        // A scan problem must never break the member's submission.
        try {
            ProcessLoanDocuments::dispatch($application->id, (float) $declaredIncome, (float) $requestedAmount, $documentPaths);
        } catch (\Throwable $e) {
            \Log::warning('Could not start document analysis: ' . $e->getMessage());
        }

        return redirect()
            ->route('user.dashboard')
            ->with('success', "Application {$application->reference} submitted. We'll email you once it's reviewed.");
    }

    /**
     * Let a member withdraw an application that hasn't been decided yet.
     */
    public function cancelApplication(LoanApplication $application)
    {
        abort_unless($application->user_id === Auth::id(), 403);

        if ($application->status !== 'pending') {
            return back()->with('error', 'Only applications still under review can be withdrawn.');
        }

        // Withdraw the mirrored admin-side row too, so it disappears from
        // the admin queue instead of sitting there orphaned.
        Application::where('loan_application_id', $application->id)->delete();

        $application->delete();

        return back()->with('success', 'Application withdrawn.');
    }

    
    /**
     * Show the member's amortization schedule for a loan application.
     */
    public function amortizationSchedule(LoanApplication $application)
    {
        abort_unless($application->user_id === Auth::id(), 403);

        $schedule = $application->amortizationSchedule();
        $summary = app(AmortizationService::class)->summary(
            (float) $application->amount,
            (int) ($application->term_months ?? 12)
        );

        return view('user.amortization', compact('application', 'schedule', 'summary'));
    }

    /**
     * Get the member's notification inbox data.
     */
    public function memberNotifications()
    {
        $notifications = AppNotification::where('user_id', Auth::id())
            ->latest()
            ->take(10)
            ->get();

        return response()->json($notifications);
    }

    /**
     * List every reminder addressed to the signed-in member.
     */
    public function notifications()
    {
        $notifications = AppNotification::where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('user.notifications', compact('notifications'));
    }

    /**
     * Mark one of the signed-in member's own reminders as read.
     * Notifications belonging to somebody else are refused.
     */
    public function markNotificationRead($notification)
    {
        $own = AppNotification::where('user_id', Auth::id())
            ->whereKey($notification)
            ->first();

        abort_if($own === null, 403, 'Unauthorized action.');

        $own->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Export amortization schedule as PDF for member's own loan.
     */
    public function exportAmortizationPdf(LoanApplication $application)
    {
        // Check ownership - member can only export their own loans
        abort_unless($application->user_id === Auth::id(), 403);

        $principal = (float) ($application->amount ?? 0);
        $termMonths = (int) ($application->term_months ?? 12);
        $rate = (float) (\App\Models\Setting::get('annual_interest_rate', config('loan_matrix.rate', 12)) / 100);

        $service = app(\App\Services\AmortizationService::class);
        $schedule = $service->schedule($principal, $termMonths, $rate);
        $summary = $service->summary($principal, $termMonths, $rate);

        $html = view('user.amortization-pdf', compact('application', 'schedule', 'summary'))->render();

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'inline; filename="amortization-' . $application->reference . '.html"',
        ]);
    }
}
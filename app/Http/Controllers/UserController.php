<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessLoanDocuments;
use App\Models\Application;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Services\AmortizationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

class UserController extends Controller
{
    /**
     * Member landing page after logging in.
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

        return view('user.index', compact('user', 'applications', 'summary'));
    }

    /**
     * Show the loan application form.
     */
    public function createApplication()
    {
        $user = Auth::user();

        // Non-members must complete the membership application before they
        // can borrow. Send them back with a clear, actionable message.
        if (!$user->coopMember) {
            return redirect()
                ->route('user.dashboard')
                ->with('error', 'You must become a cooperative member first before you can apply for a loan. Click “Become a member” to start your membership application.');
        }

        return view('user.loan', [
            'user'      => $user,
            'loanTypes' => Config::get('loan_matrix.loan_types'),
            'coopMember' => $user->coopMember,
        ]);
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
        // Defense-in-depth: never accept a loan submission from a non-member,
        // even if the form page is bypassed by a direct POST.
        if (!Auth::user()->coopMember) {
            return back()
                ->withInput()
                ->with('error', 'You must become a cooperative member first before you can apply for a loan.');
        }

        $loanType = $request->input('loan_type');
        $matrix   = Config::get('loan_matrix.matrix.' . $loanType);

        if (!$matrix) {
            return back()->withInput()
                ->withErrors(['loan_type' => 'Invalid loan product selected.']);
        }

        $maxAmount = self::maxAmountFor($loanType);
        if ($matrix['max_amount'] === 'share_capital_x2') {
            $maxAmount = 2000000;
        }
        if ($matrix['max_amount'] === 'share_capital_plus_savings') {
            $maxAmount = 2000000;
        }
        $maxAmount = min($maxAmount ?? 2000000, 2000000);
        $maxAmountRule = ['max:' . $maxAmount];
        $amountMinRule = $maxAmount < 1000 ? ['min:1'] : ['min:1000'];

        $termRule = ['required', 'integer', 'in:' . implode(',', $matrix['terms'])];

        $rules = [
            'full_name'                  => ['required', 'string', 'max:255'],
            'email'                      => ['required', 'email', 'max:255'],
            'contact_number'             => ['nullable', 'string', 'size:11', 'regex:/^[0-9]{11}$/'],
            'address'                    => ['nullable', 'string', 'max:500'],
            'birth_date'                 => ['nullable', 'date'],
            'source_of_income'           => ['required', 'in:' . implode(',', Config::get('loan_matrix.form_options.source_of_income'))],
            'employer_name'              => ['nullable', 'string', 'max:255'],
            'monthly_income'             => ['required', 'numeric', 'in:' . implode(',', array_column(Config::get('loan_matrix.form_options.monthly_income'), 0))],
            'existing_monthly_obligations' => ['required', 'numeric', 'min:0'],
            'purpose_of_loan'            => ['required', 'in:Educational,Business,Personal,Emergency'],
            'loan_type'                  => ['required', 'in:' . implode(',', array_keys(Config::get('loan_matrix.loan_types')))],
            'amount'                     => ['required', 'numeric', ...$amountMinRule, ...$maxAmountRule],
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
            'contact_number.regex' => 'Mobile number must be exactly 11 digits.',
        ];

        $data = $request->validate($rules, $messages);

        // Auto-fill removed fields from member application record
        $memberApp = Auth::user()->memberApplication;
        $data['contact_number'] = ($memberApp?->contact_number ?? Auth::user()->phone ?? 'N/A');
        $data['address'] = ($memberApp?->street ?? Auth::user()->address ?? 'N/A');
        $data['birth_date'] = ($memberApp?->birthdate ?? Auth::user()->birthdate ?? null);

        // Set employment_status from memberApplication's occupation field
        $data['employment_status'] = ($memberApp?->occupation ?? 'Employed');

        // Employer name is required for Employed and Business Owner
        if (\in_array($data['employment_status'], ['Employed', 'Business Owner'], true) && empty($data['employer_name'])) {
            return back()
                ->withInput()
                ->withErrors(['employer_name' => 'Employer or business name is required for this employment status.']);
        }

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

        // Store uploaded supporting documents
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

        // ─── AI DOCUMENT ANALYSIS ───
        // Run asynchronously so the member isn't kept waiting. The score
        // is written back to both tables when the job finishes.
        $declaredIncome = (float) $request->input('monthly_income');
        $requestedAmount = (float) $request->input('amount');

        Queue::push(new ProcessLoanDocuments(
            $application->id,
            $documentPaths,
            $declaredIncome,
            $requestedAmount
        ));

        // ─── MIRROR INTO THE ADMIN APPLICATIONS TABLE ───
        // Your admin dashboard reads from `applications`, not
        // `loan_applications`, so every member submission gets a matching
        // row there too. The link lets AdminController::approve()/reject()
        // update this same LoanApplication's status further down.
        // Include soft-deleted rows so a withdrawn mirror row's app_id can
        // never be reissued. (Application::max('id') alone adds WHERE deleted_at IS NULL.)
        $lastId = Application::withTrashed()->max('id') ?? 0;
        $appId  = 'APP-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

        Application::create([
            'loan_application_id' => $application->id,
            'app_id'    => $appId,
            'applicant' => $application->full_name,
            'loan_type' => $application->loan_type,
            'amount'    => $application->amount,
            'status'    => 'Pending',
            'ai_score'  => null, // Filled in by the document analysis job
        ]);

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
     * Mark a loan as fully paid by the member.
     *
     * Records a Payment, zeroes out remaining_balance, and clears
     * next_due_date. Returns JSON for AJAX callers.
     */
    public function markAsPaid(Request $request, LoanApplication $application)
    {
        abort_unless($application->user_id === Auth::id(), 403);

        if (!in_array($application->status, ['approved', 'disbursed'], true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only approved loans can be marked as paid.',
                ], 422);
            }
            return back()->with('error', 'Only approved loans can be marked as paid.');
        }

        $remainingBalance = (float) ($application->remaining_balance ?? 0);
        if ($remainingBalance <= 0) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This loan has no remaining balance to pay.',
                ], 422);
            }
            return back()->with('error', 'This loan has no remaining balance to pay.');
        }

        $amount = $remainingBalance;
        $application->update([
            'remaining_balance' => 0.0,
            'status'           => 'paid',
            'next_due_date'    => null,
        ]);

        Payment::create([
            'loan_application_id' => $application->id,
            'user_id'             => Auth::id(),
            'amount_paid'         => $amount,
            'remaining_balance_after' => 0.0,
            'payment_date'        => now(),
            'payment_method'      => 'member_portal',
            'notes'               => 'Marked as paid by member.',
        ]);

        // Sync the mirrored admin Application row
        Application::where('loan_application_id', $application->id)->update([
            'remaining_balance' => 0.0,
            'status'            => 'Paid',
        ]);

        $message = "Payment of ₱" . number_format($amount, 2) . " recorded. "
                 . "Loan {$application->reference} is now fully paid.";

        if ($request->expectsJson()) {
            return response()->json([
                'success'          => true,
                'message'          => $message,
                'remaining_balance' => 0.0,
                'reference'        => $application->reference,
            ]);
        }

        return back()->with('success', $message);
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
        $notifications = Auth::user()->notifications()
            ->latest()
            ->take(10)
            ->get();

        return response()->json($notifications);
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
        $rate = (float) config('loan_matrix.rate', 0.12);

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
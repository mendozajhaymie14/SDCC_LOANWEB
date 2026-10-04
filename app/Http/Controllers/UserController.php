<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

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
            'full_name'         => ['required', 'string', 'max:255'],
            'email'             => ['required', 'email', 'max:255'],
            'contact_number'    => ['required', 'string', 'size:11', 'regex:/^[0-9]{11}$/'],
            'address'           => ['required', 'string', 'max:500'],
            'birth_date'        => ['required', 'date'],
            'member_status'     => ['required', 'in:Employed,Self-employed,Business Owner,Student'],
            'source_of_income'  => ['required', 'in:' . implode(',', Config::get('loan_matrix.form_options.source_of_income'))],
            'employer_name'     => ['nullable', 'string', 'max:255'],
            'monthly_income'    => ['required', 'numeric', 'in:' . implode(',', array_column(Config::get('loan_matrix.form_options.monthly_income'), 0))],
            'loan_type'         => ['required', 'in:' . implode(',', array_keys(Config::get('loan_matrix.loan_types')))],
            'amount'            => ['required', 'numeric', ...$amountMinRule, ...$maxAmountRule],
            'term_months'       => $termRule,
            'required_documents' => ['nullable', 'array', 'max:10'],
            'required_documents.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'agree'             => ['accepted'],
        ];

        $messages = [
            'agree.accepted'      => 'Please confirm your details are true and correct.',
            'amount.min'          => 'The smallest loan we release is ₱1,000.',
            'amount.max'          => 'Maximum amount for this product is ₱' . number_format($maxAmount ?? 2000000, 2),
            'term_months.in'      => 'This product only supports ' . implode(', ', $matrix['terms']) . '-month terms.',
            'contact_number.regex' => 'Mobile number must be exactly 11 digits.',
        ];

        $data = $request->validate($rules, $messages);

        // Employer name is required for Employed and Business Owner
        if (\in_array($data['member_status'], ['Employed', 'Business Owner'], true) && empty($data['employer_name'])) {
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
        $data['employment_status'] = $data['member_status'];
        unset($data['member_status']);

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
            'ai_score'  => rand(70, 99),
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
}
<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /** Loan products offered to members. Shared by the form and the dashboard. */
    public const LOAN_TYPES = [
        'Salary Loan'      => 'Short-term loan against your regular income.',
        'Emergency Loan'   => 'Quick release for medical or urgent needs.',
        'Business Loan'    => 'Capital for a small business or expansion.',
        'Educational Loan' => 'Tuition and school expenses for your family.',
        'Appliance Loan'   => 'Purchase household appliances on instalment.',
    ];

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
        return view('user.loan', [
            'user'      => Auth::user(),
            'loanTypes' => self::LOAN_TYPES,
        ]);
    }

    /**
     * Validate and store a submitted application.
     */
    public function storeApplication(Request $request)
    {
        // First validate the basic fields
        $data = $request->validate([
            'full_name'         => ['required', 'string', 'max:255'],
            'email'             => ['required', 'email', 'max:255'],
            'contact_number'    => ['required', 'string', 'max:30'],
            'address'           => ['required', 'string', 'max:500'],
            'employment_status' => ['required', 'in:Employed,Self-employed,Business Owner,Retired,Unemployed'],
            'employer_name'     => ['nullable', 'string', 'max:255'],
            'monthly_income'    => ['required', 'numeric', 'min:1', 'max:99999999'],
            'loan_type'         => ['required', 'in:' . implode(',', array_keys(self::LOAN_TYPES))],
            'amount'            => ['required', 'numeric', 'min:1000', 'max:2000000'],
            'term_months'       => ['required', 'integer', 'min:6', 'max:60'],
            'agree'             => ['accepted'],
        ], [
            'agree.accepted'    => 'Please confirm your details are true and correct.',
            'amount.min'        => 'The smallest loan we release is ₱1,000.',
        ]);

        // Dynamic term validation based on loan type
        $shortTermLoans = ['Salary Loan', 'Emergency Loan', 'Business Loan'];
        $maxTerm = in_array($data['loan_type'], $shortTermLoans) ? 12 : 6;

        if ($data['term_months'] > $maxTerm) {
            return back()
                ->withInput()
                ->withErrors(['term_months' => "Maximum payment term for {$data['loan_type']} is {$maxTerm} months."]);
        }

        // Employer name is required for Employed and Business Owner
        if (in_array($data['employment_status'], ['Employed', 'Business Owner']) && empty($data['employer_name'])) {
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
        $data['user_id']   = Auth::id();
        $data['reference'] = LoanApplication::makeReference();
        $data['status']    = 'pending';

        $application = LoanApplication::create($data);

        // ─── MIRROR INTO THE ADMIN APPLICATIONS TABLE ───
        // Your admin dashboard reads from `applications`, not
        // `loan_applications`, so every member submission gets a matching
        // row there too. The link lets AdminController::approve()/reject()
        // update this same LoanApplication's status further down.
        $lastId = Application::max('id') ?? 0;
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
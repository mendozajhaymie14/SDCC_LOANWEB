<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /** Loan products offered to members, aligned to the SDCC Loan Matrix. */
    public const LOAN_TYPES = [
        'character'  => 'Productive/Provident - Character Loan (CL)',
        'healthcare' => 'Provident - Healthcare Loan',
        'micro'      => 'Productive - Micro Palangbayan Loan',
        'regular'    => 'Regular Productive and Provident Loan',
        'educational'=> 'Provident - Educational Loan',
        'emergency'  => 'Provident - Emergency Loan',
        'recovery'   => 'Provident - Recovery Loan (Calamity)',
        'secured'    => 'Secured Loan',
        'share_cap'  => 'Share Capital Loan',
        'special'    => 'Productive and Provident - Special Loan',
        'utility'    => 'Provident - Utility Bills Loan',
    ];

    /**
     * SDCC Loan Matrix rules used to drive the member form.
     *
     * - `max_amount`: numeric limit, or a string key the controller turns into
     *   a dynamic rule (`share_capital_x2` means up to 2× current share capital).
     * - `terms`: payment terms offered for that product.
     * - `collateral_options`: values the member is allowed to choose.
     * - `requirements`: checklist shown after a product is selected.
     * - `rate`: annual rate used for the estimate (null falls back to 12%).
     */
    public const MATRIX = [
        'character' => [
            'max_amount' => 'share_capital_x2',
            'terms' => [36],
            'collateral_options' => ['None'],
            'requirements' => [
                'Amount is 50%, 100%, or 100% × 2 of share capital.',
                'With approved credit limit and within the approved DTI ratio (if applicable).',
                'No approved credit limit / No DTI ratio for the 50% option.',
            ],
            'rate' => 0.12,
        ],
        'healthcare' => [
            'max_amount' => 2000000,
            'terms' => [12],
            'collateral_options' => ['None'],
            'requirements' => [
                'Based on the Coop Healthcare Plan.',
                'With approved credit limit and within the approved DTI ratio.',
            ],
            'rate' => 0.12,
        ],
        'micro' => [
            'max_amount' => 100000,
            'terms' => [12],
            'collateral_options' => ['None', 'Cart'],
            'requirements' => [
                'P100,000.00 max; P10,000.00 if inventory-only.',
                'No loan packaging / No approved credit limit / No DTI ratio (if None).',
                'With approved credit limit and within approved DTI ratio (if Cart).',
                'With Co-op HealthCard.',
                'With accident and inventory/cart insurance.',
                'Attend Basic Entrepreneur Training (BET).',
                'Open Palawan Account (optional, but encouraged for cart maintenance).',
            ],
            'rate' => 0.12,
        ],
        'regular' => [
            'max_amount' => 'share_capital_x2',
            'terms' => [36],
            'collateral_options' => ['None', 'Chattel', 'Real Estate Mortgage'],
            'requirements' => [
                'Approved credit limit + 100% of share capital.',
                'With approved credit limit and within the approved DTI ratio.',
                'Collateral: Chattel and/or Real Estate Mortgage (REM).',
            ],
            'rate' => 0.12,
        ],
        'educational' => [
            'max_amount' => 2000000,
            'terms' => [36],
            'collateral_options' => ['Chattel', 'Real Estate Mortgage'],
            'requirements' => [
                'Purpose: payment of tuition fees, purchase of uniforms, shoes, books, and other school materials.',
                'Proof of Purpose: school registration or card, statement of account/tuition fee, or list of books/school supplies.',
            ],
            'rate' => 0.12,
        ],
        'emergency' => [
            'max_amount' => 2000000,
            'terms' => [36],
            'collateral_options' => ['Chattel', 'Real Estate Mortgage'],
            'requirements' => [
                '20% of the approved credit limit.',
                'With approved credit limit and within the approved DTI ratio.',
                'Proof of emergency purpose (e.g., hospitalization, medical care, typhoon/flood/fire).',
            ],
            'rate' => 0.12,
        ],
        'recovery' => [
            'max_amount' => 30000,
            'terms' => [24],
            'collateral_options' => ['None'],
            'requirements' => [
                '50% of Share Capital, not exceeding P30,000.00 for MIGS.',
                '50% of Share Capital, not exceeding P15,000.00 for NON-MIGS.',
                'Purpose: repair of house/vehicle, household appliances/equipment, or small-business capital.',
                'With approved credit limit and within approved DTI ratio.',
            ],
            'rate' => 0.12,
        ],
        'secured' => [
            'max_amount' => 'share_capital_plus_savings',
            'terms' => [36],
            'collateral_options' => ['100% Share Capital and Savings/Time Deposit'],
            'requirements' => [
                'Collateral: 100% Share Capital and Savings/Time Deposit.',
                'No loan packaging / No approved credit limit / No DTI ratio.',
            ],
            'rate' => 0.12,
        ],
        'share_cap' => [
            'max_amount' => 15000,
            'terms' => [12],
            'collateral_options' => ['100% Share Capital'],
            'requirements' => [
                'Amount to complete the minimum share capital of P15,000.00.',
                'No loan packaging / No approved credit limit / No DTI ratio.',
            ],
            'rate' => 0.12,
        ],
        'special' => [
            'max_amount' => 2000000,
            'terms' => [120],
            'collateral_options' => ['TCT and Machinery & Equipment'],
            'requirements' => [
                'Over and above the approved credit limit on a regular loan, not to exceed the approved DTI ratio.',
                'Proof of collateral: ownership/registration of machinery & equipment, TCT, certified true copy, CTC, tax declaration, improvement, location/vicinity maps, and certificate of no improvement.',
                'Enrolled in Planong Damayan.',
                'Payment through post-dated checks (PDC).',
            ],
            'rate' => 0.12,
        ],
        'utility' => [
            'max_amount' => 10000,
            'terms' => [12],
            'collateral_options' => ['None'],
            'requirements' => [
                'Actual billing up to a maximum of P10,000.00.',
                'With approved credit limit and within the approved DTI ratio.',
                'Statement of accounting statement of Utility Bills (electricity, water, telephone/cellphone, cable, internet).',
            ],
            'rate' => 0.12,
        ],
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
     * Resolve the numeric maximum loan amount for a matrix product.
     * Returns null when the limit is dynamic (depends on share capital).
     */
    public static function maxAmountFor(string $loanType): ?float
    {
        $rule = self::MATRIX[$loanType]['max_amount'] ?? null;
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
        $loanType = $request->input('loan_type');
        $matrix   = self::MATRIX[$loanType] ?? null;

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
        $collateralRequired = !in_array('None', $matrix['collateral_options'], true);

        $rules = [
            'full_name'         => ['required', 'string', 'max:255'],
            'email'             => ['required', 'email', 'max:255'],
            'contact_number'    => ['required', 'string', 'size:11', 'regex:/^[0-9]{11}$/'],
            'address'           => ['required', 'string', 'max:500'],
            'birth_date'        => ['required', 'date'],
            'civil_status'      => ['required', 'in:Single,Married,Widowed,Divorced,Separated'],
            'member_status'     => ['required', 'in:Employed,Self-employed,Business Owner,Student'],
            'source_of_income'  => ['required', 'string', 'max:255', 'regex:/^[A-Za-z .\'-]+$/'],
            'employer_name'     => ['nullable', 'string', 'max:255'],
            'monthly_income'    => ['required', 'numeric', 'min:1', 'max:99999999'],
            'loan_type'         => ['required', 'in:' . implode(',', array_keys(self::LOAN_TYPES))],
            'amount'            => ['required', 'numeric'] + $amountMinRule + $maxAmountRule,
            'term_months'       => $termRule,
            'purpose'           => ['required', 'string', 'max:1000'],
            'collateral'        => $collateralRequired ? ['required', 'in:' . implode(',', $matrix['collateral_options'])] : ['nullable', 'in:' . implode(',', $matrix['collateral_options'])],
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
            'source_of_income.regex' => 'Source of income must contain letters only.',
        ];

        $data = $request->validate($rules, $messages);

        // Employer name is required for Employed and Business Owner
        if (in_array($data['member_status'], ['Employed', 'Business Owner']) && empty($data['employer_name'])) {
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
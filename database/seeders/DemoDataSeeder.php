<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\MemberApplication;
use App\Models\Payment;
use App\Models\User;
use App\Services\AmortizationService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Demo / defense data: a realistic cooperative with members at every stage of
 * the loan lifecycle (pending, approved, overdue, due soon, paid, rejected),
 * payment history, membership applications and an audit trail.
 *
 * Run (scratch DB only — see CLAUDE.md):
 *   php artisan db:seed --class=DemoDataSeeder --env=scratch
 *
 * Safe to run more than once: members are matched by member_id, loans by their
 * DEMO-Lxx reference, membership applications by e-mail. Nothing is deleted or
 * truncated. Dates are relative to "today", so the overdue / due-soon /
 * due-today examples stay accurate whenever you run it.
 *
 * Requires the same env vars as RoleDemoSeeder (staff + member demo passwords).
 */
class DemoDataSeeder extends Seeder
{
    /** @var array<string, User> */
    private array $staff = [];

    private int $receiptNo = 0;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) && config('sdcc.seed_demo_users') !== true) {
            $this->command?->info('Demo data seeding skipped (run in local/testing or set SEED_DEMO_USERS=true).');
            return;
        }

        $this->command?->info('Seeding demo data into database: ' . DB::connection()->getDatabaseName());

        // Base roster + the six role accounts (idempotent seeders).
        $this->call([CoopMemberSeeder::class]);
        if (! User::where('email', 'branchmgr@sdcc.coop')->exists()) {
            $this->call([RoleDemoSeeder::class]);
        }

        $this->loadStaff();

        DB::transaction(function () {
            $members = $this->seedMembers();
            $this->seedPortalAccounts($members);
            $this->seedLoans($members);
            $this->seedMemberApplications($members);
        });

        $this->command?->info('Demo data ready.');
    }

    // ───────────────────────────── staff ─────────────────────────────

    private function loadStaff(): void
    {
        $map = [
            'admin'        => 'admin@sdcc.coop',
            'manager'      => 'branchmgr@sdcc.coop',
            'officer'      => 'loanofficer@sdcc.coop',
            'investigator' => 'creditinvest@sdcc.coop',
        ];

        foreach ($map as $key => $email) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                throw new RuntimeException("Staff account {$email} is missing. Set the *_DEMO_PASSWORD env vars and run RoleDemoSeeder.");
            }
            $this->staff[$key] = $user;
        }
    }

    // ──────────────────────────── members ────────────────────────────

    /**
     * @return array<string, CoopMember> keyed by member_id (demo portal member keyed 'DEMO')
     */
    private function seedMembers(): array
    {
        // monthly_cap is derived below: max(0, income * 0.35 - obligations).
        $rows = [
            // Existing roster (CoopMemberSeeder) — enriched with financials.
            ['member_id' => 'SDCC-2023-0001', 'income' => 45000,  'obl' => 5000,  'limit' => 150000, 'savings' => 20000, 'job' => 'Public School Teacher',     'addr' => 'Blk 4 Lot 12, Pilar Village, San Dionisio, Parañaque City'],
            ['member_id' => 'SDCC-2023-0002', 'income' => 28000,  'obl' => 0,     'limit' => 100000, 'savings' => 12000, 'job' => 'Administrative Assistant', 'addr' => '27 Sampaguita St., San Dionisio, Parañaque City'],
            ['member_id' => 'SDCC-2023-0003', 'income' => 60000,  'obl' => 8000,  'limit' => 300000, 'savings' => 45000, 'job' => 'Warehouse Supervisor',     'addr' => '15 Dr. A. Santos Ave., San Dionisio, Parañaque City'],
            ['member_id' => 'SDCC-2023-0004', 'income' => 22000,  'obl' => 3000,  'limit' => 60000,  'savings' => 8000,  'job' => 'Sales Associate',          'addr' => '9 Mabini St., San Dionisio, Parañaque City'],
            ['member_id' => 'SDCC-2023-0005', 'income' => 120000, 'obl' => 20000, 'limit' => 500000, 'savings' => 90000, 'job' => 'Business Owner',           'addr' => '101 Quirino Ave., San Dionisio, Parañaque City'],

            // New members.
            ['member_id' => 'SDCC-2022-0101', 'name' => 'Liza Mae Villanueva',         'dob' => '1988-05-09', 'email' => 'liza.villanueva88@gmail.com',   'phone' => '09171230101', 'income' => 35000, 'obl' => 2000, 'limit' => 120000, 'savings' => 18000, 'share' => 40000, 'job' => 'Sari-sari Store Owner',  'addr' => '33 Rosal St., San Dionisio, Parañaque City',   'created' => '2022-03-14'],
            ['member_id' => 'SDCC-2022-0102', 'name' => 'Ramon Bautista Garcia',       'dob' => '1976-02-18', 'email' => 'ramon.garcia76@gmail.com',      'phone' => '09171230102', 'income' => 18000, 'obl' => 0,    'limit' => 40000,  'savings' => 3000,  'share' => 10000, 'job' => 'Tricycle Operator',      'addr' => '6 Ilang-Ilang St., San Dionisio, Parañaque City', 'created' => '2022-05-02'],
            ['member_id' => 'SDCC-2024-0201', 'name' => 'Jennifer Ocampo Mendoza',     'dob' => '1992-12-02', 'email' => 'jennifer.mendoza92@gmail.com',  'phone' => '09171230201', 'income' => 52000, 'obl' => 6000, 'limit' => 200000, 'savings' => 30000, 'share' => 35000, 'job' => 'Staff Nurse',            'addr' => '18 Narra St., San Dionisio, Parañaque City',      'created' => '2024-01-20'],
            ['member_id' => 'SDCC-2024-0202', 'name' => 'Mark Anthony Flores',         'dob' => '1999-08-30', 'email' => 'mark.flores99@gmail.com',       'phone' => '09171230202', 'income' => 30000, 'obl' => 1500, 'limit' => 80000,  'savings' => 6000,  'share' => 15000, 'job' => 'Customer Service Rep',  'addr' => '72 Rizal Ext., San Dionisio, Parañaque City',     'created' => '2024-02-11'],
            ['member_id' => 'SDCC-2021-0050', 'name' => 'Rosario Dimaculangan Pascual', 'dob' => '1969-06-11', 'email' => 'rosario.pascual69@gmail.com',  'phone' => '09171230050', 'income' => 15000, 'obl' => 0,    'limit' => 30000,  'savings' => 9000,  'share' => 20000, 'job' => 'Retired (Pensioner)',   'addr' => '5 Bonifacio St., San Dionisio, Parañaque City',   'created' => '2021-07-05', 'status' => CoopMember::STATUS_INACTIVE],
            ['member_id' => 'SDCC-2020-0099', 'name' => 'Eduardo Lacson Navarro',      'dob' => '1971-09-23', 'email' => 'eduardo.navarro71@gmail.com',   'phone' => '09171230099', 'income' => 26000, 'obl' => 9000, 'limit' => 50000,  'savings' => 2000,  'share' => 12000, 'job' => 'Driver',                'addr' => '41 Luna St., San Dionisio, Parañaque City',       'created' => '2020-10-19', 'status' => CoopMember::STATUS_SUSPENDED],
        ];

        $members = [];

        foreach ($rows as $r) {
            $member = CoopMember::withTrashed()->firstOrNew(['member_id' => $r['member_id']]);

            if (! $member->exists) {
                $member->forceFill([
                    'full_name'     => $r['name'],
                    'date_of_birth' => $r['dob'],
                    'email'         => $r['email'],
                    'is_registered' => false,
                    'created_at'    => $r['created'] ?? now(),
                    'updated_at'    => $r['created'] ?? now(),
                ]);
                $member->forceFill(['contact_number' => $r['phone'], 'share_capital' => $r['share']]);
            }

            $member->forceFill([
                'status'                        => $r['status'] ?? CoopMember::STATUS_ACTIVE,
                'address'                       => $r['addr'],
                'occupation'                    => $r['job'],
                'monthly_income'                => $r['income'],
                'existing_monthly_obligations'  => $r['obl'],
                'savings_balance'               => $r['savings'],
                'approved_credit_limit'         => $r['limit'],
                'credit_limit_source'           => 'manual',
                'approved_monthly_credit_limit' => max(0, $r['income'] * 0.35 - $r['obl']),
                'monthly_credit_limit_source'   => 'auto',
            ])->save();

            $members[$r['member_id']] = $member;
        }

        // The role-demo member (member@sdcc.coop) — the account used to demo the portal.
        $demoUser = User::where('email', 'member@sdcc.coop')->first();
        $demo = $demoUser?->coopMember ?? CoopMember::where('email', 'member@sdcc.coop')->first();

        if ($demo) {
            $demo->forceFill([
                'status'                        => CoopMember::STATUS_ACTIVE,
                'address'                       => '88 Sto. Niño St., San Dionisio, Parañaque City',
                'occupation'                    => 'Office Clerk',
                'monthly_income'                => 40000,
                'existing_monthly_obligations'  => 3000,
                'savings_balance'               => 15000,
                'approved_credit_limit'         => 150000,
                'credit_limit_source'           => 'manual',
                'approved_monthly_credit_limit' => 11000,
                'monthly_credit_limit_source'   => 'auto',
            ])->forceFill(['share_capital' => 30000])->save();
            $members['DEMO'] = $demo;
        } else {
            $this->command?->warn('member@sdcc.coop not found — portal demo member loans skipped.');
        }

        return $members;
    }

    /**
     * Two real portal logins (password from MEMBER_DEMO_PASSWORD) so the member
     * portal can be shown with different borrowers. Everyone else is a
     * staff-encoded, portal-less member (matched by e-mail), which the app supports.
     *
     * @param  array<string, CoopMember>  $members
     */
    private function seedPortalAccounts(array $members): void
    {
        $password = env('MEMBER_DEMO_PASSWORD');

        if ($password === null || $password === '') {
            $this->command?->warn('MEMBER_DEMO_PASSWORD not set — Juan and Maria will be portal-less.');
            return;
        }

        foreach (['SDCC-2023-0001', 'SDCC-2023-0002'] as $memberId) {
            $member = $members[$memberId];
            $user = User::withTrashed()->firstOrNew(['email' => $member->email]);

            if (! $user->exists) {
                $user->password = Hash::make($password);
            }

            $user->forceFill([
                'name'              => $member->full_name,
                'phone'             => $member->contact_number,
                'role'              => 'member',
                'usertype'          => 'member',
                'coop_member_id'    => $member->id,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            $member->forceFill(['user_id' => $user->id, 'is_registered' => true])->save();
        }
    }

    // ───────────────────────────── loans ─────────────────────────────

    /**
     * @param  array<string, CoopMember>  $members
     */
    private function seedLoans(array $members): void
    {
        $pass  = ['Debt-to-income ratio is within the limit.', 'Savings are healthy relative to the requested amount.', 'Document check: passed'];
        $med   = ['Requested amount is above the recommended amount.', 'Debt-to-income ratio is close to the limit.'];
        $high  = ['Debt-to-income ratio far exceeds the limit; risk forced to High.', 'Requested amount exceeds recommended by more than 2x.', 'Savings are low relative to the requested amount.'];

        // status: approved | paid | pending | rejected
        // approved: next_due_in (days from today) + paid (installments already paid)
        // paid:     months_ago (approved), fully repaid
        // pending / rejected: submitted_days_ago
        $scenarios = [
            // ── Active loans, each in a different repayment standing ──
            ['ref' => 'DEMO-L01', 'm' => 'SDCC-2023-0001', 'type' => 'regular',   'amount' => 60000,  'term' => 36, 'purpose' => 'Personal',  'status' => 'approved', 'next_due_in' => 12, 'paid' => 4, 'rec' => 70000,  'risk' => 'Low',    'score' => 82, 'doc' => 'passed',       'reasons' => $pass,  'portal' => true],
            ['ref' => 'DEMO-L02', 'm' => 'SDCC-2023-0003', 'type' => 'regular',   'amount' => 150000, 'term' => 36, 'purpose' => 'Business',  'status' => 'approved', 'next_due_in' => -9, 'paid' => 3, 'rec' => 140000, 'risk' => 'Medium', 'score' => 64, 'doc' => 'passed',       'reasons' => $med,   'approved_amount' => 140000, 'remarks' => 'Approved at ₱140,000, reduced to fit the member’s monthly limit.', 'encoded' => true],
            ['ref' => 'DEMO-L03', 'm' => 'SDCC-2023-0004', 'type' => 'utility',   'amount' => 6000,   'term' => 12, 'purpose' => 'Personal',  'status' => 'approved', 'next_due_in' => 0,  'paid' => 6, 'rec' => 6000,   'risk' => 'Low',    'score' => 76, 'doc' => 'passed',       'reasons' => $pass,  'encoded' => true],
            ['ref' => 'DEMO-L04', 'm' => 'SDCC-2023-0005', 'type' => 'regular',   'amount' => 400000, 'term' => 36, 'purpose' => 'Business',  'status' => 'approved', 'next_due_in' => 3,  'paid' => 8, 'rec' => 450000, 'risk' => 'Low',    'score' => 88, 'doc' => 'passed',       'reasons' => $pass,  'encoded' => true],
            ['ref' => 'DEMO-L05', 'm' => 'DEMO',           'type' => 'utility',   'amount' => 9000,   'term' => 12, 'purpose' => 'Personal',  'status' => 'approved', 'next_due_in' => 20, 'paid' => 3, 'rec' => 9000,   'risk' => 'Low',    'score' => 80, 'doc' => 'passed',       'reasons' => $pass,  'portal' => true],

            // ── Fully repaid loans (repayment history) ──
            ['ref' => 'DEMO-L06', 'm' => 'SDCC-2022-0101', 'type' => 'healthcare', 'amount' => 20000, 'term' => 12, 'purpose' => 'Emergency', 'status' => 'paid', 'months_ago' => 14, 'rec' => 20000, 'risk' => 'Low', 'score' => 79, 'doc' => 'passed', 'reasons' => $pass, 'encoded' => true],
            ['ref' => 'DEMO-L07', 'm' => 'DEMO',           'type' => 'healthcare', 'amount' => 12000, 'term' => 12, 'purpose' => 'Emergency', 'status' => 'paid', 'months_ago' => 15, 'rec' => 12000, 'risk' => 'Low', 'score' => 81, 'doc' => 'passed', 'reasons' => $pass, 'portal' => true],

            // ── Pending: the approval queue ──
            ['ref' => 'DEMO-L08', 'm' => 'SDCC-2023-0002', 'type' => 'emergency',   'amount' => 15000, 'term' => 36, 'purpose' => 'Emergency',   'status' => 'pending', 'submitted_days_ago' => 2, 'rec' => 15000, 'risk' => 'Medium', 'score' => 61, 'doc' => 'passed',       'reasons' => $med,  'portal' => true],
            ['ref' => 'DEMO-L09', 'm' => 'SDCC-2024-0201', 'type' => 'educational', 'amount' => 45000, 'term' => 36, 'purpose' => 'Educational', 'status' => 'pending', 'submitted_days_ago' => 1, 'rec' => 45000, 'risk' => 'Medium', 'score' => 58, 'doc' => 'needs_review', 'reasons' => array_merge($med, ['Document check: needs review']), 'flags' => true, 'encoded' => true],
            ['ref' => 'DEMO-L10', 'm' => 'SDCC-2024-0202', 'type' => 'utility',     'amount' => 8000,  'term' => 12, 'purpose' => 'Personal',    'status' => 'pending', 'submitted_days_ago' => 0, 'rec' => 6000,  'risk' => 'Medium', 'score' => 55, 'doc' => 'unavailable',  'reasons' => $med,  'encoded' => true],
            ['ref' => 'DEMO-L11', 'm' => 'SDCC-2022-0101', 'type' => 'micro',       'amount' => 50000, 'term' => 12, 'purpose' => 'Business',    'status' => 'pending', 'submitted_days_ago' => 3, 'rec' => 55000, 'risk' => 'Low',    'score' => 72, 'doc' => 'passed',       'reasons' => $pass, 'encoded' => true],
            ['ref' => 'DEMO-L12', 'm' => 'SDCC-2022-0102', 'type' => 'regular',     'amount' => 120000, 'term' => 36, 'purpose' => 'Personal',   'status' => 'pending', 'submitted_days_ago' => 1, 'rec' => 30000, 'risk' => 'High',   'score' => 38, 'doc' => 'needs_review', 'reasons' => $high, 'flags' => true, 'encoded' => true],

            // ── Rejected ──
            ['ref' => 'DEMO-L13', 'm' => 'SDCC-2024-0202', 'type' => 'micro', 'amount' => 90000, 'term' => 12, 'purpose' => 'Business', 'status' => 'rejected', 'submitted_days_ago' => 70, 'reviewed_days_ago' => 67, 'rec' => 25000, 'risk' => 'High', 'score' => 41, 'doc' => 'passed', 'reasons' => $high, 'flags' => true, 'remarks' => 'Requested amount exceeds the approved credit limit and monthly capacity.', 'encoded' => true],
        ];

        foreach ($scenarios as $s) {
            $member = $members[$s['m']] ?? null;
            if (! $member) {
                continue; // demo portal member not available
            }
            if (LoanApplication::withTrashed()->where('reference', $s['ref'])->exists()) {
                continue; // idempotent
            }
            $this->makeLoan($s, $member);
        }
    }

    private function makeLoan(array $s, CoopMember $member): void
    {
        $today  = Carbon::today();
        $status = $s['status'];
        $k      = (int) ($s['paid'] ?? 0);

        // Work out the timeline.
        if ($status === 'approved') {
            $nextDue    = $today->copy()->addDays($s['next_due_in']);
            $reviewedAt = $nextDue->copy()->subMonthsNoOverflow($k + 1)->setTime(10, 30);
            $createdAt  = $reviewedAt->copy()->subDays(3);
        } elseif ($status === 'paid') {
            $reviewedAt = $today->copy()->subMonthsNoOverflow($s['months_ago'])->setTime(10, 30);
            $createdAt  = $reviewedAt->copy()->subDays(3);
            $k          = (int) $s['term'];
        } elseif ($status === 'rejected') {
            $createdAt  = $today->copy()->subDays($s['submitted_days_ago'])->setTime(9, 0);
            $reviewedAt = $today->copy()->subDays($s['reviewed_days_ago'])->setTime(14, 0);
        } else { // pending
            $createdAt  = $today->copy()->subDays($s['submitted_days_ago'])->setTime(rand(8, 15), rand(0, 59));
            $reviewedAt = null;
        }

        $granted  = in_array($status, ['approved', 'paid'], true);
        $portalId = ! empty($s['portal']) ? $member->user_id : null;
        $encoder  = ! empty($s['encoded']) ? $this->staff['officer'] : null;
        $reasons  = json_encode($s['reasons']);

        $loan = new LoanApplication();
        $loan->forceFill([
            'user_id'                      => $portalId,
            'reference'                    => $s['ref'],
            'full_name'                    => $member->full_name,
            'email'                        => $member->email,
            'contact_number'               => $member->contact_number ?: '09171234567',
            'address'                      => $member->address,
            'birth_date'                   => $member->date_of_birth,
            'employment_status'            => $member->occupation ?? 'Employed',
            'source_of_income'             => str_contains((string) $member->occupation, 'Owner') || str_contains((string) $member->occupation, 'Operator')
                                                ? 'Business / Self-employed'
                                                : (str_contains((string) $member->occupation, 'Retired') ? 'Retirement / Pension' : 'Salary'),
            'monthly_income'               => $member->monthly_income,
            'existing_monthly_obligations' => $member->existing_monthly_obligations ?? 0,
            'loan_type'                    => $s['type'],
            'purpose_of_loan'              => $s['purpose'],
            'purpose'                      => $s['purpose'],
            'amount'                       => $s['amount'],
            'term_months'                  => $s['term'],
            'status'                       => $status === 'paid' ? 'approved' : $status, // recompute flips it to 'paid'
            'admin_remarks'                => $s['remarks'] ?? ($granted ? 'Approved. Proceed with release of funds.' : null),
            'reviewed_at'                  => $reviewedAt,
            'approved_by'                  => $granted ? $this->staff['manager']->id : null,
            'approved_amount'              => $granted ? ($s['approved_amount'] ?? $s['amount']) : null,
            'remaining_balance'            => 0,
            'recommended_amount'           => $s['rec'],
            'risk_level'                   => $s['risk'],
            'risk_score'                   => $s['score'],
            'risk_factors'                 => $reasons,
            'evaluation_reasons'           => $reasons,
            'document_check'               => $s['doc'],
            'ai_score'                     => $s['score'],
            'ai_analysis_notes'            => $this->aiNote($s['doc'], $member->full_name),
            'document_processing_status'   => match ($s['doc']) { 'passed' => 'completed', 'needs_review' => 'unverified', default => 'pending' },
            'encoded_by'                   => $encoder?->id,
            'created_at'                   => $createdAt,
            'updated_at'                   => $reviewedAt ?? $createdAt,
        ])->save();

        if ($granted) {
            $loan->forceFill(['remaining_balance' => $loan->totalPayable()])->saveQuietly();
        }

        // Mirror row in the admin `applications` table (same convention as LoanSubmissionService).
        $lastId = Application::withTrashed()->max('id') ?? 0;
        $app = new Application();
        $app->forceFill([
            'loan_application_id' => $loan->id,
            'app_id'              => 'APP-' . str_pad((string) ($lastId + 1), 4, '0', STR_PAD_LEFT),
            'applicant'           => $member->full_name,
            'loan_type'           => $s['type'],
            'amount'              => $s['amount'],
            'term_months'         => $s['term'],
            'status'              => match ($status) { 'approved' => 'Approved', 'paid' => 'Approved', 'rejected' => 'Rejected', default => 'Pending' },
            'ai_score'            => $s['score'],
            'ai_analysis_note'    => $this->aiNote($s['doc'], $member->full_name),
            'has_risk_flags'      => (bool) ($s['flags'] ?? false),
            'remaining_balance'   => $granted ? $loan->remaining_balance : 0,
            'disbursement_date'   => $granted ? $reviewedAt->toDateString() : null,
            'recommended_amount'  => $s['rec'],
            'risk_level'          => $s['risk'],
            'risk_score'          => $s['score'],
            'risk_factors'        => $reasons,
            'evaluation_reasons'  => $reasons,
            'document_check'      => $s['doc'],
            'approved_amount'     => $granted ? ($s['approved_amount'] ?? $s['amount']) : null,
            'admin_remarks'       => $loan->admin_remarks,
            'approved_by'         => $granted ? $this->staff['manager']->id : null,
            // Review chain (not mass-assignable on the model): investigator → manager.
            'investigator_note'   => 'Documents reviewed; member profile and credit history checked.',
            'validated_by'        => $this->staff['investigator']->id,
            'validated_at'        => $reviewedAt?->copy()->subDay() ?? $createdAt->copy()->addHours(5),
            'manager_remark'      => $granted ? 'Approved per credit investigator recommendation.' : ($status === 'rejected' ? $loan->admin_remarks : null),
            'created_at'          => $createdAt,
            'updated_at'          => $reviewedAt ?? $createdAt,
        ])->save();

        // Repayment history, then let the app's own service derive balance / due date / status.
        if ($granted && $k > 0) {
            $emi = $loan->monthly_payment;
            $methods = ['Cash', 'GCash', 'Bank Transfer'];

            for ($i = 1; $i <= $k; $i++) {
                $due  = $reviewedAt->copy()->addMonthsNoOverflow($i)->startOfDay();
                $date = $due->copy()->subDays($i % 3);
                $recorder = $i % 2 === 0 ? $this->staff['manager'] : $this->staff['officer'];

                $payment = new Payment();
                $payment->forceFill([
                    'loan_application_id'     => $loan->id,
                    'user_id'                 => $portalId,
                    'amount_paid'             => $emi,
                    'remaining_balance_after' => 0, // recomputed below
                    'reference_no'            => 'OR-' . str_pad((string) (++$this->receiptNo + 1000), 6, '0', STR_PAD_LEFT),
                    'payment_date'            => $date->toDateString(),
                    'encoded_by'              => $recorder->id,
                    'recorded_by'             => $recorder->id,
                    'payment_method'          => $methods[$i % 3],
                    'notes'                   => 'Monthly amortization #' . $i,
                    'created_at'              => $date->copy()->setTime(11, 0),
                    'updated_at'              => $date->copy()->setTime(11, 0),
                ])->save();
            }

            app(PaymentService::class)->recomputeLoanState($loan);
        }

        // Audit trail.
        if ($encoder) {
            $this->audit('Application Encoded', "Staff-encoded application {$loan->reference} for {$member->full_name}", $encoder, $createdAt, $loan->id);
        }
        if ($granted) {
            $this->audit('Application Approved', "Approved {$loan->reference} for {$member->full_name} (₱" . number_format((float) $loan->approved_amount, 2) . ')', $this->staff['manager'], $reviewedAt, $loan->id);
        } elseif ($status === 'rejected') {
            $this->audit('Application Rejected', "Rejected {$loan->reference} for {$member->full_name}", $this->staff['manager'], $reviewedAt, $loan->id);
        }
        if ($k > 0) {
            $this->audit('Payment Recorded', "Recorded {$k} payment(s) on {$loan->reference} for {$member->full_name}", $this->staff['officer'], $reviewedAt->copy()->addMonthsNoOverflow($k), $loan->id);
        }
    }

    private function aiNote(string $docCheck, string $name): string
    {
        return match ($docCheck) {
            'passed'       => "Document check: passed — supporting documents are consistent with the declared details for {$name}. Advisory only; recommended amount and risk level are rule-based.",
            'needs_review' => 'AI Scan Alert: document mismatch detected. Documents not verified automatically; manual review required.',
            default        => 'Document scan unavailable. Score computed from declared data only.',
        };
    }

    private function audit(string $action, string $details, User $actor, Carbon $when, int $loanId): void
    {
        $log = new AuditLog();
        $log->forceFill([
            'user_id'     => $actor->id,
            'actor_id'    => $actor->id,
            'actor_role'  => $actor->role,
            'admin_name'  => $actor->name,
            'action'      => $action,
            'details'     => $details,
            'target_type' => 'loan_application',
            'target_id'   => $loanId,
            'ip_address'  => '127.0.0.1',
            'created_at'  => $when,
            'updated_at'  => $when,
        ])->save();
    }

    // ───────────────────── membership applications ─────────────────────

    /**
     * @param  array<string, CoopMember>  $members
     */
    private function seedMemberApplications(array $members): void
    {
        $rows = [
            ['first' => 'Angelica',  'middle' => 'Reyes',    'last' => 'Dizon',     'email' => 'angelica.dizon94@gmail.com',  'phone' => '09181110001', 'dob' => '1994-04-16', 'job' => 'Graphic Designer',   'income' => 38000, 'obl' => 2500, 'savings' => 9000,  'days' => 1, 'status' => 'pending'],
            ['first' => 'Joselito',  'middle' => 'Mercado',  'last' => 'Ramirez',   'email' => 'joselito.ramirez87@gmail.com', 'phone' => '09181110002', 'dob' => '1987-10-05', 'job' => 'Electrician',        'income' => 27000, 'obl' => 0,    'savings' => 5000,  'days' => 2, 'status' => 'pending'],
            ['first' => 'Catherine', 'middle' => 'Uy',       'last' => 'Sy',        'email' => 'catherine.sy90@gmail.com',    'phone' => '09181110003', 'dob' => '1990-01-29', 'job' => 'Accountant',         'income' => 55000, 'obl' => 7000, 'savings' => 40000, 'days' => 4, 'status' => 'pending'],
            ['first' => 'Mark Anthony', 'middle' => 'Santos', 'last' => 'Flores',   'email' => 'mark.flores99@gmail.com',     'phone' => '09171230202', 'dob' => '1999-08-30', 'job' => 'Customer Service Rep', 'income' => 30000, 'obl' => 1500, 'savings' => 6000,  'days' => 300, 'status' => 'approved', 'member' => 'SDCC-2024-0202'],
            ['first' => 'Benjamin',  'middle' => 'Cruz',     'last' => 'Torres',    'email' => 'benjamin.torres85@gmail.com', 'phone' => '09181110005', 'dob' => '1985-07-12', 'job' => 'Freelance Driver',   'income' => 9000,  'obl' => 6000, 'savings' => 500,   'days' => 30, 'status' => 'rejected', 'reason' => 'Incomplete requirements: proof of billing was not legible. Please re-apply with a clear copy.'],
        ];

        foreach ($rows as $r) {
            $app = MemberApplication::withTrashed()->firstOrNew(['email' => $r['email']]);
            if ($app->exists) {
                continue;
            }

            $when     = Carbon::now()->subDays($r['days']);
            $reviewed = $r['status'] === 'pending' ? null : $when->copy()->addDays(2);

            $app->forceFill([
                'surname'                      => $r['last'],
                'last_name'                    => $r['last'],
                'first_name'                   => $r['first'],
                'middle_name'                  => $r['middle'],
                'contact_number'               => $r['phone'],
                'date_of_birth'                => $r['dob'],
                'place_of_birth'               => 'Parañaque City',
                'nationality'                  => 'Filipino',
                'street'                       => 'San Dionisio',
                'barangay'                     => 'San Dionisio',
                'municipality'                 => 'Parañaque City',
                'province'                     => 'Metro Manila',
                'zip_code'                     => '1700',
                'occupation'                   => $r['job'],
                'monthly_income'               => $r['income'],
                'existing_monthly_obligations' => $r['obl'],
                'savings_balance'              => $r['savings'],
                'stay_years'                   => 5,
                'stay_months'                  => 0,
                'status'                       => $r['status'],
                'reviewed_at'                  => $reviewed,
                'reviewed_by'                  => $reviewed ? $this->staff['manager']->id : null,
                'approved_member_id'           => isset($r['member']) ? ($members[$r['member']]->id ?? null) : null,
                'rejection_reason'             => $r['reason'] ?? null,
                'created_at'                   => $when,
                'updated_at'                   => $reviewed ?? $when,
            ])->save();
        }
    }
}

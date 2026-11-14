<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use App\Support\LoanProductHelper;
use App\Support\LoanStatus;
use App\Support\Permissions;
use App\Services\CreditLimitService;
use App\Services\LoanEvaluationService;
use App\Services\LoanSubmissionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S0 Preflight: fails hard unless the test suite runs against SQLite in-memory.
 * S1: Monthly allowance / used / available logic tests.
 *
 * RULE (peso principal allowance, not a payment cap):
 *   Monthly Used  = sum of AMOUNT of loans APPROVED in the current
 *                   calendar month (Asia/Manila), reset on the 1st.
 *   Monthly Avail = max(0, monthly limit - monthly used).
 *   Over limit    = requested/approved amount > monthly available.
 */
class MonthlyCreditLimitTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function tests_run_on_sqlite_memory()
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    private function makeMember(array $overrides = []): CoopMember
    {
        $user = User::factory()->create();
        $member = CoopMember::factory()->create(array_merge([
            'user_id'                  => $user->id,
            'status'                   => CoopMember::STATUS_ACTIVE,
            'monthly_income'           => 50000,
            'existing_monthly_obligations' => 5000,
            'savings_balance'          => 0,
            'approved_credit_limit'    => null,
            'approved_monthly_credit_limit' => null,
            'monthly_credit_limit_source' => null,
            'is_migs'                  => false,
        ], $overrides));
        $user->forceFill(['coop_member_id' => $member->id])->save();
        return $member->fresh();
    }

    private function makePortalLessMember(array $overrides = []): CoopMember
    {
        $member = CoopMember::factory()->create(array_merge([
            'user_id'                  => null,
            'status'                   => CoopMember::STATUS_ACTIVE,
            'monthly_income'           => 50000,
            'existing_monthly_obligations' => 5000,
            'savings_balance'          => 0,
            'approved_credit_limit'    => null,
            'approved_monthly_credit_limit' => null,
            'monthly_credit_limit_source' => null,
            'is_migs'                  => false,
        ], $overrides));
        return $member->fresh();
    }

    /** Approved loan that counts toward THIS month's used allowance. */
    private function approvedThisMonth(CoopMember $member, float $amount, array $overrides = []): LoanApplication
    {
        return LoanApplication::factory()->create(array_merge([
            'user_id'     => $member->user_id,
            'email'       => $member->email,
            'status'      => LoanStatus::APPROVED,
            'amount'      => $amount,
            'term_months' => 12,
            'reviewed_at' => now(),
        ], $overrides));
    }

    /** @test */
    public function monthly_capacity_50000_income_0_obligations_returns_17500()
    {
        $member = $this->makeMember(['monthly_income' => 50000, 'existing_monthly_obligations' => 0]);
        $this->assertEquals(17500.0, $member->suggestedMonthlyLimit());
    }

    /** @test */
    public function monthly_capacity_50000_income_5000_obligations_returns_12500()
    {
        $member = $this->makeMember(['monthly_income' => 50000, 'existing_monthly_obligations' => 5000]);
        $this->assertEquals(12500.0, $member->suggestedMonthlyLimit());
    }

    /** @test */
    public function monthly_capacity_85000_income_1000_obligations_returns_28750()
    {
        $member = $this->makeMember(['monthly_income' => 85000, 'existing_monthly_obligations' => 1000]);
        $this->assertEquals(28750.0, $member->suggestedMonthlyLimit());
    }

    /** @test */
    public function monthly_capacity_30000_income_10000_obligations_returns_500()
    {
        $member = $this->makeMember(['monthly_income' => 30000, 'existing_monthly_obligations' => 10000]);
        $this->assertEquals(500.0, $member->suggestedMonthlyLimit());
    }

    /** @test */
    public function monthly_capacity_15000_income_6000_obligations_returns_0()
    {
        $member = $this->makeMember(['monthly_income' => 15000, 'existing_monthly_obligations' => 6000]);
        $this->assertEquals(0.0, $member->suggestedMonthlyLimit());
    }

    /** @test */
    public function monthly_capacity_null_income_returns_null()
    {
        $member = $this->makeMember(['monthly_income' => null, 'existing_monthly_obligations' => 0]);
        $this->assertNull($member->suggestedMonthlyLimit());
    }

    /** @test */
    public function monthly_capacity_stored_manual_20000_beats_computed_12500()
    {
        $member = $this->makeMember([
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 5000,
            'approved_monthly_credit_limit' => 20000,
            'monthly_credit_limit_source' => 'manual',
        ]);
        $this->assertEquals(20000.0, $member->monthlyCapacity());
        $this->assertEquals(12500.0, $member->suggestedMonthlyLimit());
    }

    /** @test */
    public function monthly_capacity_null_stored_falls_back_to_computed()
    {
        $member = $this->makeMember([
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 5000,
            'approved_monthly_credit_limit' => null,
            'monthly_credit_limit_source' => null,
        ]);
        $this->assertEquals(12500.0, $member->monthlyCapacity());
    }

    // ── New rule: used = principal approved this calendar month ─────────

    /** @test */
    public function monthly_used_sums_amounts_approved_this_calendar_month_only()
    {
        $member = $this->makeMember(['monthly_income' => 50000, 'existing_monthly_obligations' => 0]);

        $this->approvedThisMonth($member, 10000);
        $this->approvedThisMonth($member, 5000);

        // Excluded: last month's approval, pending, rejected, paid.
        LoanApplication::factory()->create([
            'user_id' => $member->user_id, 'status' => 'approved', 'amount' => 99999,
            'term_months' => 12, 'reviewed_at' => now()->subMonth(),
        ]);
        LoanApplication::factory()->create([
            'user_id' => $member->user_id, 'status' => 'pending', 'amount' => 99999, 'term_months' => 12,
        ]);
        LoanApplication::factory()->create([
            'user_id' => $member->user_id, 'status' => 'rejected', 'amount' => 99999, 'term_months' => 12,
        ]);
        LoanApplication::factory()->create([
            'user_id' => $member->user_id, 'status' => 'paid', 'amount' => 99999, 'term_months' => 12,
            'reviewed_at' => now(),
        ]);

        $this->assertEquals(15000.0, $member->fresh()->monthlyUsed());
        // capacity 17,500 (50,000 income, 0 obligations) − 15,000 used
        $this->assertEquals(2500.0, $member->fresh()->monthlyAvailable());
    }

    /** @test */
    public function monthly_used_resets_on_the_first_of_next_month()
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 30, 23, 59, 59, 'Asia/Manila'));
        $member = $this->makeMember(['monthly_income' => 50000, 'existing_monthly_obligations' => 0]);
        $this->approvedThisMonth($member, 10000);
        $this->assertEquals(10000.0, $member->fresh()->monthlyUsed());

        Carbon::setTestNow(Carbon::create(2026, 12, 1, 0, 0, 0, 'Asia/Manila'));
        $member = $member->fresh();
        $this->assertEquals(0.0, $member->monthlyUsed());
        $this->assertEquals($member->monthlyCapacity(), $member->monthlyAvailable());

        // A loan approved today (in the new month) counts again.
        $this->approvedThisMonth($member, 2500);
        $this->assertEquals(2500.0, $member->fresh()->monthlyUsed());

        Carbon::setTestNow();
    }

    /** @test */
    public function monthly_used_approved_last_day_of_last_month_does_not_count()
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 31, 23, 59, 59, 'Asia/Manila'));
        $member = $this->makeMember(['monthly_income' => 50000, 'existing_monthly_obligations' => 0]);
        $this->approvedThisMonth($member, 40000);

        Carbon::setTestNow(Carbon::create(2026, 11, 1, 8, 30, 0, 'Asia/Manila'));
        $this->assertEquals(0.0, $member->fresh()->monthlyUsed());

        Carbon::setTestNow();
    }

    // ── Old EMI behaviour lives on under monthlyPaymentsActive() ────────

    /** @test */
    public function monthly_payments_active_keeps_summing_original_emis_of_approved_unpaid_loans()
    {
        $member = $this->makeMember(['monthly_income' => 100000, 'existing_monthly_obligations' => 0]);

        // Even when approved LAST month, the EMI still counts for DTI.
        LoanApplication::factory()->create([
            'user_id' => $member->user_id, 'status' => 'approved', 'amount' => 50000,
            'term_months' => 12, 'reviewed_at' => now()->subMonth(),
        ]);
        $loan36 = LoanApplication::factory()->create([
            'user_id' => $member->user_id, 'status' => 'approved', 'amount' => 50000,
            'term_months' => 36, 'reviewed_at' => now(),
        ]);

        $expected = (float) LoanApplication::where('user_id', $member->user_id)
            ->where('status', LoanStatus::APPROVED)
            ->get()
            ->sum(fn ($l) => (float) $l->monthly_payment);
        $this->assertEquals($expected, $member->fresh()->monthlyPaymentsActive());
        $this->assertGreaterThan(0, $member->fresh()->monthlyPaymentsActive());
        // The principal-allowance used must NOT equal the EMI sum.
        $this->assertNotEquals($expected, $member->fresh()->monthlyUsed());
    }

    /** @test */
    public function monthly_available_never_negative()
    {
        $member = $this->makeMember([
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 0,
            'approved_monthly_credit_limit' => 1000,
            'monthly_credit_limit_source' => 'manual',
        ]);

        $this->assertEquals(1000.0, $member->monthlyAvailable());

        $this->approvedThisMonth($member, 100000); // principal over the limit
        $member = $member->fresh();
        $this->assertEquals(100000.0, $member->monthlyUsed());
        $this->assertEquals(0.0, $member->monthlyAvailable());
        $this->assertGreaterThanOrEqual(0, $member->monthlyAvailable());
    }

    /** @test */
    public function monthly_used_isolated_between_two_portal_less_members()
    {
        $memberA = $this->makePortalLessMember([
            'email' => 'memberA@example.com',
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 0,
        ]);
        $memberB = $this->makePortalLessMember([
            'email' => 'memberB@example.com',
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 0,
        ]);

        LoanApplication::factory()->create([
            'user_id' => null,
            'email'   => 'memberA@example.com',
            'status'  => 'approved',
            'amount'  => 50000,
            'term_months' => 12,
            'reviewed_at' => now(),
        ]);

        $this->assertEquals(50000.0, $memberA->fresh()->monthlyUsed());
        $this->assertEquals(0.0, $memberB->fresh()->monthlyUsed());
    }

    /** @test */
    public function monthly_used_excludes_pending_and_rejected_staff_encoded_loans()
    {
        $member = $this->makePortalLessMember([
            'email' => 'member@example.com',
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 0,
        ]);

        LoanApplication::factory()->create([
            'user_id' => null, 'email' => 'member@example.com',
            'status' => 'approved', 'amount' => 50000, 'term_months' => 12,
            'reviewed_at' => now(),
        ]);
        LoanApplication::factory()->create([
            'user_id' => null, 'email' => 'member@example.com',
            'status' => 'pending', 'amount' => 30000, 'term_months' => 12,
        ]);
        LoanApplication::factory()->create([
            'user_id' => null, 'email' => 'member@example.com',
            'status' => 'rejected', 'amount' => 20000, 'term_months' => 12,
        ]);

        $this->assertEquals(50000.0, $member->fresh()->monthlyUsed());
    }

    /** @test */
    public function member_with_null_user_id_and_no_email_counts_zero_used()
    {
        $member = $this->makePortalLessMember([
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 0,
        ]);

        // A stray staff-encoded loan under ANOTHER email must not leak in
        // (a member with neither user_id nor email resolves to zero loans).
        LoanApplication::factory()->create([
            'user_id' => null, 'email' => 'someone-else@example.com',
            'status' => 'approved', 'amount' => 50000, 'term_months' => 12,
            'reviewed_at' => now(),
        ]);

        $this->assertEquals(0.0, $member->fresh()->monthlyUsed());
    }

    // ── Evaluation: exact cap, forced High at zero, unchanged DTI ──────

    private function memberWith14kAvailable(): CoopMember
    {
        // Stored monthly limit 14,279.37, no approvals this month →
        // monthlyAvailable is exactly 14,279.37.
        return $this->makeMember([
            'approved_credit_limit'         => 1000000,
            'approved_monthly_credit_limit' => 14279.37,
            'monthly_credit_limit_source'   => 'manual',
            'monthly_income'                => 50000,
            'existing_monthly_obligations'  => 0,
            'savings_balance'               => 10000,
        ]);
    }

    /** @test */
    public function evaluation_of_440k_with_14279_37_available_caps_at_exactly_14279_37_with_capacity_reason()
    {
        $member = $this->memberWith14kAvailable();
        $loanApp = LoanApplication::factory()->create([
            'user_id'                      => $member->user_id,
            'email'                        => $member->email,
            'loan_type'                    => 'regular',
            'amount'                       => 440000,
            'term_months'                  => 36,
            'monthly_income'               => 50000,
            'existing_monthly_obligations' => 0,
            'status'                       => 'pending',
            'documents'                    => ['id.pdf'],
        ]);

        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        // EXACT cap — no ₱1,000 round-down on the monthly allowance.
        $this->assertEquals(14279.37, $result['recommended_amount']);
        $this->assertTrue(collect($result['evaluation_reasons'])->contains(
            fn ($r) => str_contains($r, 'Requested amount exceeds this month\'s available credit of ₱14,279.37. Capped at ₱14,279.37.')
        ));
    }

    /** @test */
    public function evaluation_exact_cap_scenario_available_5500_request_10000()
    {
        // limit 15,500; 10,000 approved this month → available 5,500.
        $member = $this->makeMember([
            'monthly_income'                => 100000,
            'existing_monthly_obligations'  => 0,
            'savings_balance'               => 10000,
            'approved_credit_limit'         => 1000000,
            'approved_monthly_credit_limit' => 15500,
            'monthly_credit_limit_source'   => 'manual',
        ]);
        $this->approvedThisMonth($member, 10000);

        $this->assertEquals(10000.0, $member->fresh()->monthlyUsed());
        $this->assertEquals(5500.0, $member->fresh()->monthlyAvailable());

        $loanApp = LoanApplication::factory()->create([
            'user_id'                      => $member->user_id,
            'email'                        => $member->email,
            'loan_type'                    => 'regular',
            'amount'                       => 10000,
            'term_months'                  => 12,
            'monthly_income'               => 100000,
            'existing_monthly_obligations' => 0,
            'status'                       => 'pending',
        ]);

        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        $this->assertEquals(5500.0, $result['recommended_amount']);
        $this->assertTrue($result['eligible']);
        $this->assertSame('Low', $result['risk_level']);
        $this->assertSame(83, $result['risk_score']); // 45 DTI + 18 no-history + 0 docs + 20 savings
        $this->assertSame([
            'Requested amount exceeds this month\'s available credit of ₱5,500.00. Capped at ₱5,500.00.',
            'No supporting documents uploaded.',
        ], $result['evaluation_reasons']);
        $this->assertEquals(45, $result['details']['dti_score']);
    }

    /** @test */
    public function evaluation_forces_high_when_monthly_available_is_zero()
    {
        // limit 15,500; approvals this month 10,000 + 5,500 → available 0.
        $member = $this->makeMember([
            'monthly_income'                => 100000,
            'existing_monthly_obligations'  => 0,
            'savings_balance'               => 10000,
            'approved_credit_limit'         => 1000000,
            'approved_monthly_credit_limit' => 15500,
            'monthly_credit_limit_source'   => 'manual',
        ]);
        $this->approvedThisMonth($member, 10000);
        $this->approvedThisMonth($member, 5500);

        $this->assertEquals(15500.0, $member->fresh()->monthlyUsed());
        $this->assertEquals(0.0, $member->fresh()->monthlyAvailable());

        $loanApp = LoanApplication::factory()->create([
            'user_id'                      => $member->user_id,
            'email'                        => $member->email,
            'loan_type'                    => 'regular',
            'amount'                       => 5500,
            'term_months'                  => 12,
            'monthly_income'               => 100000,
            'existing_monthly_obligations' => 0,
            'status'                       => 'pending',
        ]);

        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        $this->assertEquals(0.0, $result['recommended_amount']);
        $this->assertFalse($result['eligible']);
        $this->assertSame('High', $result['risk_level']);
        $this->assertSame(83, $result['risk_score']); // score NOT changed, only the level
        $this->assertSame([
            'No monthly credit limit left this month: used ₱15,500.00 of ₱15,500.00.',
            'Risk forced to High: no monthly credit limit left this month.',
            'No supporting documents uploaded.',
        ], $result['evaluation_reasons']);
        $this->assertStringNotContainsString(
            'exceeds recommended by more than 2x',
            implode(' ', $result['evaluation_reasons'])
        );
    }

    /** @test */
    public function evaluation_null_monthly_limit_is_not_forced_high_and_not_capped()
    {
        $member = $this->makeMember([
            'monthly_income'                => null, // capacity never assessed
            'existing_monthly_obligations'  => 0,
            'savings_balance'               => 10000,
            'approved_credit_limit'         => 1000000,
            'approved_monthly_credit_limit' => null,
        ]);
        $this->assertNull($member->fresh()->monthlyAvailable());

        $loanApp = LoanApplication::factory()->create([
            'user_id'                      => $member->user_id,
            'email'                        => $member->email,
            'loan_type'                    => 'regular',
            'amount'                       => 10000,
            'term_months'                  => 12,
            'monthly_income'               => 100000,
            'existing_monthly_obligations' => 0,
            'status'                       => 'pending',
            'documents'                    => ['id.pdf'],
        ]);

        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        $this->assertTrue($result['eligible']);
        $this->assertEquals(10000.0, $result['recommended_amount']); // no cap
        $this->assertNotSame('High', $result['risk_level']);           // no forced High
    }

    /** @test */
    public function evaluation_capped_above_zero_available_is_not_forced_high()
    {
        $member = $this->memberWith14kAvailable();
        $loanApp = LoanApplication::factory()->create([
            'user_id'                      => $member->user_id,
            'email'                        => $member->email,
            'loan_type'                    => 'regular',
            'amount'                       => 440000,
            'term_months'                  => 36,
            'monthly_income'               => 50000,
            'existing_monthly_obligations' => 0,
            'status'                       => 'pending',
            'documents'                    => ['id.pdf'],
        ]);

        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        $this->assertTrue($result['eligible']);
        $this->assertNotSame('High', $result['risk_level']);
        $this->assertStringNotContainsString(
            'Risk forced to High',
            implode(' ', $result['evaluation_reasons'])
        );
    }

    /** @test */
    public function dti_score_unchanged_by_the_monthly_rule_change()
    {
        // DTI still uses monthlyPaymentsActive() (original EMIs), not the
        // new principal-allowance used. Same fixture, same score.
        $member = $this->makeMember([
            'monthly_income'               => 100000,
            'existing_monthly_obligations' => 0,
            'savings_balance'              => 0,
            'approved_credit_limit'        => 1000000,
        ]);
        $this->approvedThisMonth($member, 50000); // 12-mo EMI ≈ 4,442.44

        $loanApp = LoanApplication::factory()->create([
            'user_id'                      => $member->user_id,
            'email'                        => $member->email,
            'loan_type'                    => 'regular',
            'amount'                       => 50000,
            'term_months'                  => 12,
            'monthly_income'               => 100000,
            'existing_monthly_obligations' => 0,
            'status'                       => 'pending',
        ]);

        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        // DTI must still be the EMI-based ratio (monthlyPaymentsActive),
        // not the new principal-allowance figure.
        $activeEmi = (float) LoanApplication::where('user_id', $member->user_id)
            ->where('status', LoanStatus::APPROVED)->first()->monthly_payment;
        $expectedRatio = round((0 + $activeEmi + (float) $loanApp->monthly_payment) / 100000, 4);

        $this->assertEquals($expectedRatio, $result['details']['dti_ratio']);
        $this->assertEquals(45, $result['details']['dti_score']);
    }

    // ── Request-time gates (member form + staff encode) ─────────────────

    /** @test */
    public function member_form_rejection_messages()
    {
        $member = $this->makeMember([
            'monthly_income'                => 50000,
            'existing_monthly_obligations'  => 0,
            'approved_credit_limit'         => 1000000,
            'approved_monthly_credit_limit' => 15500,
            'monthly_credit_limit_source'   => 'manual',
        ]);
        $this->approvedThisMonth($member, 10000); // available 5,500

        $base = [
            'full_name'                    => $member->full_name,
            'email'                        => $member->email,
            'contact_number'               => '09123456789',
            'source_of_income'             => 'Salary',
            'monthly_income'               => 50000,
            'existing_monthly_obligations' => 0,
            'purpose_of_loan'              => 'Personal',
            'loan_type'                    => 'regular',
            'term_months'                  => 36,
            'agree'                        => 'yes',
        ];

        // Over the limit — now accepted as PENDING (request-time gate removed);
        // the evaluation service caps the recommendation instead.
        $this->actingAs($member->user)
            ->post(route('user.loans.store'), array_merge($base, ['amount' => 6000]))
            ->assertRedirect();
        $this->assertEquals(1, LoanApplication::where('user_id', $member->user_id)->where('status', 'pending')->count());

        // Zero available — also accepted as PENDING. (The one-pending-at-a-time
        // rule now blocks the second submission before the monthly gate, so
        // assert that rule's message instead of a second row.)
        $this->approvedThisMonth($member, 5500);
        $this->actingAs($member->user)
            ->post(route('user.loans.store'), array_merge($base, ['amount' => 1000]))
            ->assertRedirect();
        $this->assertStringContainsString(
            'You already have an application under review.',
            session('error') ?? ''
        );
        $this->assertEquals(1, LoanApplication::where('user_id', $member->user_id)->where('status', 'pending')->count());

        // Not assessed — still blocked.
        $unassessed = $this->makeMember([
            'monthly_income' => null,
            'existing_monthly_obligations' => 0,
            'approved_credit_limit' => 1000000,
        ]);
        $this->actingAs($unassessed->user)
            ->post(route('user.loans.store'), array_merge([
                'full_name' => $unassessed->full_name,
                'email'     => $unassessed->email,
            ], $base, ['amount' => 1000]))
            ->assertSessionHasErrors('amount');
        $this->assertStringContainsString(
            'Monthly credit limit has not been assessed. Contact the cooperative office.',
            session('errors')->first('amount')
        );
        $this->assertEquals(0, LoanApplication::where('user_id', $unassessed->user_id)->count());
        // One pending app + its admin mirror from the over-limit POST above.
        $this->assertEquals(1, Application::count());
    }

    /** @test */
    public function staff_encode_rejection_messages()
    {
        $member = $this->makeMember([
            'monthly_income'                => 50000,
            'existing_monthly_obligations'  => 0,
            'approved_credit_limit'         => 1000000,
            'approved_monthly_credit_limit' => 15500,
            'monthly_credit_limit_source'   => 'manual',
        ]);
        $this->approvedThisMonth($member, 10000); // available 5,500

        $base = [
            'member_id'                    => $member->id,
            'loan_type'                    => 'regular',
            'term_months'                  => 36,
            'purpose'                      => 'Personal',
            'monthly_income'               => 50000,
            'existing_monthly_obligations' => 0,
            'source_of_income'             => 'Salary',
        ];

        // Over the limit — now accepted as PENDING (request-time gate removed).
        $this->actingAs($this->adminUser())
            ->post(route('applications.encode.store'), array_merge($base, ['amount' => 6000]))
            ->assertSessionDoesntHaveErrors();
        $this->assertEquals(1, LoanApplication::where('status', 'pending')->count());
        $this->assertEquals(1, Application::count());

        // Zero available — also accepted as PENDING. (The one-pending-at-a-time
        // rule blocks the second submission before the monthly gate.)
        $this->approvedThisMonth($member, 5500);
        $this->actingAs($this->adminUser())
            ->post(route('applications.encode.store'), array_merge($base, ['amount' => 1000]))
            ->assertRedirect();
        $this->assertStringContainsString(
            'This member already has an application under review.',
            session('error') ?? ''
        );
        $this->assertEquals(1, LoanApplication::where('status', 'pending')->count());
        $this->assertEquals(1, Application::count());

        $unassessed = $this->makeMember([
            'monthly_income' => null,
            'existing_monthly_obligations' => 0,
            'approved_credit_limit' => 1000000,
        ]);
        $this->actingAs($this->adminUser())
            ->post(route('applications.encode.store'), array_merge($base, [
                'member_id' => $unassessed->id,
                'amount'    => 1000,
            ]))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Monthly credit limit has not been assessed. Contact the cooperative office.'));
        // Two approved fixture loans + the one pending app created above,
        // plus its admin mirror. The unassessed member created nothing.
        $this->assertEquals(3, LoanApplication::count());
        $this->assertEquals(1, Application::count());
    }

    /** @test */
    public function null_income_blocks_submission_with_contact_office_message()
    {
        $member = $this->makeMember([
            'monthly_income'                => null,
            'existing_monthly_obligations'  => 0,
            'approved_monthly_credit_limit' => null,
            'monthly_credit_limit_source'   => null,
        ]);
        $user = $member->user;

        $this->actingAs($user)
            ->post(route('user.loans.store'), [
                'full_name'                  => $member->full_name,
                'email'                      => $member->email,
                'contact_number'             => '09123456789',
                'source_of_income'           => 'Salary',
                'monthly_income'             => 20000,
                'existing_monthly_obligations' => 0,
                'purpose_of_loan'            => 'Personal',
                'loan_type'                  => 'regular',
                'amount'                     => 5000,
                'term_months'                => 36,
                'agree'                      => 'yes',
            ])
            ->assertSessionHasErrors(['amount' => 'Monthly credit limit has not been assessed. Contact the cooperative office.']);

        $this->assertEquals(0, LoanApplication::where('user_id', $user->id)->count());
    }

    // ── Approve-time re-check ───────────────────────────────────────────

    /** @test */
    public function two_pending_loans_second_blocked_after_first_approved()
    {
        $member = $this->makeMember([
            'monthly_income'                => 50000,
            'existing_monthly_obligations'  => 0,
            'approved_credit_limit'         => 1000000,
            'approved_monthly_credit_limit' => 15500,
            'monthly_credit_limit_source'   => 'manual',
        ]);

        // A and B each fit alone (10,000 ≤ 15,500) but not together.
        // Same full_name so the approve flow reuses one Borrowers row.
        $appA = LoanApplication::factory()->create([
            'user_id' => $member->user_id, 'email' => $member->email,
            'full_name' => $member->full_name,
            'loan_type' => 'regular', 'amount' => 10000, 'term_months' => 12,
            'monthly_income' => 50000, 'existing_monthly_obligations' => 0,
            'status' => 'pending',
        ]);
        $appB = LoanApplication::factory()->create([
            'user_id' => $member->user_id, 'email' => $member->email,
            'full_name' => $member->full_name,
            'loan_type' => 'regular', 'amount' => 10000, 'term_months' => 12,
            'monthly_income' => 50000, 'existing_monthly_obligations' => 0,
            'status' => 'pending',
        ]);
        $mirrorA = Application::create([
            'loan_application_id' => $appA->id, 'app_id' => 'APP-A1',
            'applicant' => $appA->full_name, 'loan_type' => $appA->loan_type,
            'amount' => $appA->amount, 'status' => 'Pending',
            'recommended_amount' => 10000, 'risk_level' => 'Low', 'risk_score' => 80,
        ]);
        $mirrorB = Application::create([
            'loan_application_id' => $appB->id, 'app_id' => 'APP-B1',
            'applicant' => $appB->full_name, 'loan_type' => $appB->loan_type,
            'amount' => $appB->amount, 'status' => 'Pending',
            'recommended_amount' => 10000, 'risk_level' => 'Low', 'risk_score' => 80,
        ]);

        $loanOfficer = User::factory()->create(['usertype' => 'loan_officer']);
        $this->assertTrue(Permissions::has($loanOfficer, Permissions::APPROVE_LOANS));
        $this->assertFalse(Permissions::has($loanOfficer, Permissions::OVERRIDE_CREDIT_LIMIT));

        // Approve A — succeeds on its own.
        $this->actingAs($loanOfficer)
            ->postJson(route('applications.approve', $mirrorA->id), ['approved_amount' => 10000])
            ->assertJson(['success' => true]);

        // A now counts in Monthly Used (10,000); B (10,000 > 5,500) blocked.
        $this->assertEquals(10000.0, $member->fresh()->monthlyUsed());

        $respB = $this->actingAs($loanOfficer)
            ->postJson(route('applications.approve', $mirrorB->id), ['approved_amount' => 10000]);
        $respB->assertStatus(400)->assertJson(['success' => false]);
        $this->assertStringContainsString(
            'exceeds the monthly credit limit',
            json_decode($respB->getContent(), true)['message']
        );

        // Override with permission + reason succeeds and is audited.
        $branchManager = User::factory()->asBranchManager()->create();
        $this->actingAs($branchManager)
            ->postJson(route('applications.approve', $mirrorB->id), [
                'approved_amount' => 10000,
                'reason' => 'Exceptional payment history, co-signed by branch manager',
            ])
            ->assertJson(['success' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Monthly Capacity Override']);
    }

    /** @test */
    public function approve_rechecks_current_used_and_allows_exact_available_amount()
    {
        $member = $this->makeMember([
            'monthly_income'                => 100000,
            'existing_monthly_obligations'  => 0,
            'approved_credit_limit'         => 1000000,
            'approved_monthly_credit_limit' => 15500,
            'monthly_credit_limit_source'   => 'manual',
        ]);
        $this->approvedThisMonth($member, 10000); // available 5,500

        $app = LoanApplication::factory()->create([
            'user_id' => $member->user_id, 'email' => $member->email,
            'loan_type' => 'regular', 'amount' => 5500, 'term_months' => 12,
            'monthly_income' => 100000, 'existing_monthly_obligations' => 0,
            'status' => 'pending',
        ]);
        $mirror = Application::create([
            'loan_application_id' => $app->id, 'app_id' => 'APP-EXACT',
            'applicant' => $app->full_name, 'loan_type' => $app->loan_type,
            'amount' => $app->amount, 'status' => 'Pending',
            'recommended_amount' => 5500, 'risk_level' => 'Low', 'risk_score' => 83,
        ]);

        $loanOfficer = User::factory()->create(['usertype' => 'loan_officer']);
        $this->actingAs($loanOfficer)
            ->postJson(route('applications.approve', $mirror->id), ['approved_amount' => 5500])
            ->assertJson(['success' => true]);

        $member = $member->fresh();
        $this->assertEquals(15500.0, $member->monthlyUsed());
        $this->assertEquals(0.0, $member->monthlyAvailable());

        // A further request that month is now accepted as PENDING (request-time
        // gate removed); approve() still hard-blocks it at decision time.
        $this->actingAs($member->user)
            ->post(route('user.loans.store'), [
                'full_name'                    => $member->full_name,
                'email'                        => $member->email,
                'contact_number'               => '09123456789',
                'source_of_income'             => 'Salary',
                'monthly_income'               => 100000,
                'existing_monthly_obligations' => 0,
                'purpose_of_loan'              => 'Personal',
                'loan_type'                    => 'regular',
                'amount'                       => 1000,
                'term_months'                  => 36,
                'agree'                        => 'yes',
            ])
            ->assertRedirect();
        $this->assertEquals(
            1,
            LoanApplication::where('user_id', $member->user_id)->where('status', 'pending')->count()
        );

        // The approve gate still blocks the over-limit decision.
        $pendingApp = LoanApplication::where('user_id', $member->user_id)->where('status', 'pending')->first();
        $this->assertNotNull($pendingApp);
        $mirror = Application::where('loan_application_id', $pendingApp->id)->first();
        $this->assertNotNull($mirror);
        $resp = $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']))
            ->postJson(route('applications.approve', $mirror->id), ['approved_amount' => 1000]);
        $resp->assertJson(['success' => false]);
        $this->assertStringContainsString(
            'No monthly credit limit left this month',
            json_decode((string) $resp->getContent(), true)['message'] ?? ''
        );
    }

    /** @test */
    public function approve_monthly_gate_works_for_portal_less_staff_encoded_loans()
    {
        $member = $this->makePortalLessMember([
            'email'                        => 'portal-less@example.com',
            'monthly_income'               => 100000,
            'existing_monthly_obligations' => 0,
            'approved_credit_limit'        => 1000000,
            'approved_monthly_credit_limit' => 15500,
            'monthly_credit_limit_source'  => 'manual',
        ]);
        $this->approvedThisMonth($member, 10000); // available 5,500

        $loanApp = LoanApplication::factory()->create([
            'user_id' => null, 'email' => 'portal-less@example.com',
            'loan_type' => 'regular', 'amount' => 6000, 'term_months' => 12,
            'monthly_income' => 100000, 'existing_monthly_obligations' => 0,
            'status' => 'pending',
        ]);
        $mirror = Application::create([
            'loan_application_id' => $loanApp->id, 'app_id' => 'APP-PL1',
            'applicant' => $loanApp->full_name, 'loan_type' => $loanApp->loan_type,
            'amount' => $loanApp->amount, 'status' => 'Pending',
            'recommended_amount' => 6000, 'risk_level' => 'Low', 'risk_score' => 80,
        ]);

        $loanOfficer = User::factory()->create(['usertype' => 'loan_officer']);
        $resp = $this->actingAs($loanOfficer)
            ->postJson(route('applications.approve', $mirror->id), ['approved_amount' => 6000]);

        $resp->assertStatus(400)->assertJson(['success' => false]);
        $this->assertStringContainsString(
            'exceeds the monthly credit limit',
            json_decode($resp->getContent(), true)['message']
        );
    }

    // ── applicationData() JSON ──────────────────────────────────────────

    /** @test */
    public function application_data_returns_monthly_max_12_and_new_meaning_values()
    {
        $member = $this->makeMember([
            'monthly_income'                => 50000,
            'existing_monthly_obligations'  => 0,
            'approved_credit_limit'         => 1000000,
            'approved_monthly_credit_limit' => 15500,
            'monthly_credit_limit_source'   => 'manual',
        ]);

        foreach ([12, 36] as $term) {
            $loanApp = LoanApplication::factory()->create([
                'user_id' => $member->user_id, 'email' => $member->email,
                'loan_type' => 'regular', 'amount' => 10000, 'term_months' => $term,
                'monthly_income' => 50000, 'existing_monthly_obligations' => 0,
                'status' => 'pending',
            ]);
            $mirror = Application::create([
                'loan_application_id' => $loanApp->id, 'app_id' => 'APP-M' . $term,
                'applicant' => $loanApp->full_name, 'loan_type' => $loanApp->loan_type,
                'amount' => $loanApp->amount, 'status' => 'Pending',
                'recommended_amount' => 10000, 'risk_level' => 'Low', 'risk_score' => 80,
            ]);

            $payload = $this->actingAs($this->adminUser())
                ->get(route('admin.applications.data', $mirror->id))
                ->assertOk()
                ->getData(true)['application'];

            $this->assertEquals(186000.0, $payload['monthly_max_12']); // 15,500 x 12
            $this->assertEquals(0.0, $payload['monthly_used']);
            $this->assertEquals(15500.0, $payload['monthly_available']);
            // Total credit limit line values still present.
            $this->assertEquals(1000000.0, $payload['total_credit_limit']);
            $this->assertNotNull($payload['available_credit_total']);
            // The old term-based figure must not leak into the JSON.
            $this->assertArrayNotHasKey('max_for_term', $payload);
        }
    }

    /** @test */
    public function application_data_shows_not_assessed_when_monthly_limit_is_null()
    {
        $member = $this->makeMember([
            'monthly_income'       => null,
            'existing_monthly_obligations' => 0,
            'approved_credit_limit' => 1000000,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $member->user_id, 'email' => $member->email,
            'loan_type' => 'regular', 'amount' => 10000, 'term_months' => 12,
            'monthly_income' => 50000, 'existing_monthly_obligations' => 0,
            'status' => 'pending',
        ]);
        $mirror = Application::create([
            'loan_application_id' => $loanApp->id, 'app_id' => 'APP-NULL',
            'applicant' => $loanApp->full_name, 'loan_type' => $loanApp->loan_type,
            'amount' => $loanApp->amount, 'status' => 'Pending',
            'recommended_amount' => 10000, 'risk_level' => 'Low', 'risk_score' => 80,
        ]);

        $payload = $this->actingAs($this->adminUser())
            ->get(route('admin.applications.data', $mirror->id))
            ->getData(true)['application'];

        $this->assertNull($payload['monthly_max_12']);
        $this->assertNull($payload['monthly_available']);
        $this->assertNull($payload['monthly_capacity']);
    }

    /** @test */
    public function application_data_pending_warning_totals_pending_amounts()
    {
        $member = $this->memberWith14kAvailable();
        $appA = app(LoanSubmissionService::class)->submit([
            'user_id' => $member->user_id, 'reference' => LoanApplication::makeReference(),
            'full_name' => $member->full_name, 'email' => $member->email,
            'contact_number' => '09123456789', 'address' => 'Test Address',
            'employment_status' => 'Employed', 'loan_type' => 'regular',
            'amount' => 100000, 'term_months' => 36,
            'monthly_income' => 50000, 'existing_monthly_obligations' => 0,
            'source_of_income' => 'Salary', 'purpose_of_loan' => 'Personal',
        ]);
        $appB = app(LoanSubmissionService::class)->submit([
            'user_id' => $member->user_id, 'reference' => LoanApplication::makeReference(),
            'full_name' => $member->full_name, 'email' => $member->email,
            'contact_number' => '09123456789', 'address' => 'Test Address',
            'employment_status' => 'Employed', 'loan_type' => 'regular',
            'amount' => 100000, 'term_months' => 36,
            'monthly_income' => 50000, 'existing_monthly_obligations' => 0,
            'source_of_income' => 'Salary', 'purpose_of_loan' => 'Personal',
        ]);

        $mirrorA = Application::where('loan_application_id', $appA->id)->first();
        $payload = $this->actingAs($this->adminUser())
            ->get(route('admin.applications.data', $mirrorA->id))
            ->getData(true)['application'];

        $this->assertEquals(1, $payload['pending_loans_count']);
        $this->assertEquals(100000.0, $payload['pending_loans_total']); // amount, not EMI

        // A's own loan must not count itself.
        $mirrorB = Application::where('loan_application_id', $appB->id)->first();
        $payloadB = $this->actingAs($this->adminUser())
            ->get(route('admin.applications.data', $mirrorB->id))
            ->getData(true)['application'];
        $this->assertEquals(1, $payloadB['pending_loans_count']);
    }

    /** @test */
    public function application_data_returns_the_capped_at_this_months_available_credit_reason()
    {
        $member = $this->memberWith14kAvailable();
        $loanApp = app(LoanSubmissionService::class)->submit([
            'user_id'                      => $member->user_id,
            'reference'                    => LoanApplication::makeReference(),
            'full_name'                    => $member->full_name,
            'email'                        => $member->email,
            'contact_number'               => '09123456789',
            'address'                      => 'Test Address',
            'employment_status'            => 'Employed',
            'loan_type'                    => 'regular',
            'amount'                       => 440000,
            'term_months'                  => 36,
            'monthly_income'               => 50000,
            'existing_monthly_obligations' => 0,
            'source_of_income'             => 'Salary',
            'purpose_of_loan'              => 'Personal',
        ]);

        $mirror = Application::where('loan_application_id', $loanApp->id)->first();
        $this->assertNotNull($mirror);

        $payload = $this->actingAs($this->adminUser())
            ->get(route('admin.applications.data', $mirror->id))
            ->assertOk()
            ->getData(true);

        $this->assertTrue(collect($payload['application']['reasons'])->contains(
            fn ($r) => str_contains($r, 'this month\'s available credit')
        ));
    }

    // ── Consistency across the four gated paths (new rule) ─────────────

    /** @test */
    public function all_four_paths_enforce_amount_over_monthly_available()
    {
        $member = $this->makeMember([
            'monthly_income'                => 50000,
            'existing_monthly_obligations'  => 0,
            'approved_credit_limit'         => 1000000,
            'approved_monthly_credit_limit' => 15500,
            'monthly_credit_limit_source'   => 'manual',
            'savings_balance'               => 0,
        ]);

        // (1) evaluate(): 16,000 capped at exactly 15,500
        $loanApp = LoanApplication::factory()->create([
            'user_id'                      => $member->user_id,
            'email'                        => $member->email,
            'loan_type'                    => 'regular',
            'amount'                       => 16000,
            'term_months'                  => 36,
            'monthly_income'               => 50000,
            'existing_monthly_obligations' => 0,
            'status'                       => 'rejected',
        ]);
        $result = app(LoanEvaluationService::class)->evaluate($loanApp);
        $this->assertEquals(15500.0, $result['recommended_amount']);

        // (2) member form: over-limit 16,000 accepted as PENDING (request-time
        // gate removed); the approve gate below stays authoritative.
        $formBase = [
            'full_name'                    => $member->full_name,
            'email'                        => $member->email,
            'contact_number'               => '09123456789',
            'source_of_income'             => 'Salary',
            'monthly_income'               => 50000,
            'existing_monthly_obligations' => 0,
            'purpose_of_loan'              => 'Personal',
            'loan_type'                    => 'regular',
            'term_months'                  => 36,
            'agree'                        => 'yes',
        ];
        $this->actingAs($member->user)
            ->post(route('user.loans.store'), array_merge($formBase, ['amount' => 16000]))
            ->assertRedirect();
        $this->assertNull(session('error'));

        // (3) staff encode: the monthly gate no longer blocks the request —
        // the one-pending-at-a-time rule (created by (2) above) stops it instead.
        $admin = $this->adminUser();
        $encodeBase = [
            'member_id'                    => $member->id,
            'loan_type'                    => 'regular',
            'term_months'                  => 36,
            'purpose'                      => 'Personal',
            'monthly_income'               => 50000,
            'existing_monthly_obligations' => 0,
            'source_of_income'             => 'Salary',
        ];
        $this->actingAs($admin)
            ->post(route('applications.encode.store'), array_merge($encodeBase, ['amount' => 16000]))
            ->assertRedirect();
        $this->assertStringContainsString(
            'This member already has an application under review.',
            session('error') ?? ''
        );

        // (4) approve: 16,000 blocked without override, 15,000 passes
        $loanOfficer = User::factory()->create(['usertype' => 'loan_officer']);
        foreach ([['amount' => 16000, 'blocked' => true], ['amount' => 15000, 'blocked' => false]] as $case) {
            $app = LoanApplication::factory()->create([
                'user_id'                      => $member->user_id,
                'email'                        => $member->email,
                'loan_type'                    => 'regular',
                'amount'                       => $case['amount'],
                'term_months'                  => 36,
                'monthly_income'               => 50000,
                'existing_monthly_obligations' => 0,
                'status'                       => 'pending',
                'recommended_amount'           => 15000,
                'risk_level'                   => 'Low',
                'risk_score'                   => 80,
            ]);
            $mirror = Application::create([
                'loan_application_id' => $app->id,
                'app_id'              => 'APP-P2' . $case['amount'],
                'applicant'           => $app->full_name,
                'loan_type'           => $app->loan_type,
                'amount'              => $app->amount,
                'status'              => 'Pending',
                'recommended_amount'  => 15000,
                'risk_level'          => 'Low',
                'risk_score'          => 80,
            ]);

            $response = $this->actingAs($loanOfficer)
                ->postJson(route('applications.approve', $mirror->id), [
                    'approved_amount' => $case['amount'],
                ]);

            if ($case['blocked']) {
                $response->assertStatus(400)->assertJson(['success' => false]);
                $this->assertStringContainsString(
                    'exceeds the monthly credit limit',
                    json_decode($response->getContent(), true)['message']
                );
            } else {
                $response->assertJson(['success' => true]);
            }
        }
    }

    /** @test */
    public function zero_monthly_available_blocks_every_path()
    {
        // income 30000, obligations 10500 → capacity exactly 0
        $member = $this->makeMember([
            'monthly_income'               => 30000,
            'existing_monthly_obligations' => 10500,
            'approved_credit_limit'        => 100000,
        ]);
        $this->assertEquals(0.0, $member->monthlyAvailable());

        // evaluate(): rejected with the zero reason
        $loanApp = LoanApplication::factory()->create([
            'user_id'                      => $member->user_id,
            'email'                        => $member->email,
            'loan_type'                    => 'regular',
            'amount'                       => 10000,
            'term_months'                  => 12,
            'monthly_income'               => 30000,
            'existing_monthly_obligations' => 10500,
            'status'                       => 'rejected',
        ]);
        $result = app(LoanEvaluationService::class)->evaluate($loanApp);
        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('No monthly credit limit left this month: used ₱0.00 of ₱0.00.', $result['rejection_reason']);
        $this->assertSame('High', $result['risk_level']);

        // member form: accepted as PENDING (request-time gate removed); the
        // evaluation caps the recommendation to 0 and approve() blocks below.
        $this->actingAs($member->user)
            ->post(route('user.loans.store'), [
                'full_name'                    => $member->full_name,
                'email'                        => $member->email,
                'contact_number'               => '09123456789',
                'source_of_income'             => 'Salary',
                'monthly_income'               => 30000,
                'existing_monthly_obligations' => 10500,
                'purpose_of_loan'              => 'Personal',
                'loan_type'                    => 'regular',
                'amount'                       => 5000,
                'term_months'                  => 36,
                'agree'                        => 'yes',
            ])
            ->assertRedirect();

        // staff encode: the monthly gate no longer blocks it — the
        // one-pending-at-a-time rule (from the member-form POST above) does.
        $this->actingAs($this->adminUser())
            ->post(route('applications.encode.store'), [
                'member_id'                    => $member->id,
                'loan_type'                    => 'regular',
                'amount'                       => 5000,
                'term_months'                  => 36,
                'purpose'                      => 'Personal',
                'monthly_income'               => 30000,
                'existing_monthly_obligations' => 10500,
                'source_of_income'             => 'Salary',
            ])
            ->assertRedirect();
        $this->assertStringContainsString(
            'This member already has an application under review.',
            session('error') ?? ''
        );

        // approve: blocked
        $app = LoanApplication::factory()->create([
            'user_id'                      => $member->user_id,
            'email'                        => $member->email,
            'loan_type'                    => 'regular',
            'amount'                       => 5000,
            'term_months'                  => 36,
            'monthly_income'               => 30000,
            'existing_monthly_obligations' => 10500,
            'status'                       => 'pending',
            'recommended_amount'           => 5000,
            'risk_level'                   => 'Low',
            'risk_score'                   => 80,
        ]);
        $mirror = Application::create([
            'loan_application_id' => $app->id,
            'app_id'              => 'APP-P2Z',
            'applicant'           => $app->full_name,
            'loan_type'           => $app->loan_type,
            'amount'              => $app->amount,
            'status'              => 'Pending',
            'recommended_amount'  => 5000,
            'risk_level'          => 'Low',
            'risk_score'          => 80,
        ]);
        $response = $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']))
            ->postJson(route('applications.approve', $mirror->id), ['approved_amount' => 5000]);

        $response->assertStatus(400)->assertJson(['success' => false]);
        $this->assertStringContainsString(
            'No monthly credit limit left this month',
            json_decode($response->getContent(), true)['message']
        );
    }

    /** @test */
    public function null_monthly_available_blocks_controllers_but_evaluate_skips_the_cap()
    {
        $member = $this->makeMember([
            'monthly_income'               => null, // capacity never assessed
            'existing_monthly_obligations' => 0,
            'approved_credit_limit'        => 1000000,
        ]);
        $this->assertNull($member->monthlyAvailable());

        // evaluate(): income declared ON THE APPLICATION, member capacity null
        // → the monthly cap is skipped (by design), application stays eligible
        $loanApp = LoanApplication::factory()->create([
            'user_id'                      => $member->user_id,
            'email'                        => $member->email,
            'loan_type'                    => 'regular',
            'amount'                       => 200000,
            'term_months'                  => 12,
            'monthly_income'               => 50000,
            'existing_monthly_obligations' => 0,
            'status'                       => 'pending',
        ]);
        $result = app(LoanEvaluationService::class)->evaluate($loanApp);
        $this->assertTrue($result['eligible']);
        // affordable = 17500 / 0.0888487 ≈ 196963.86 (exact, no round-down)
        $this->assertEquals(196963.86, $result['recommended_amount'], '', 0.01);
        $this->assertStringNotContainsString(
            'No monthly credit limit',
            implode(' ', $result['evaluation_reasons'])
        );

        // staff encode: blocked
        $this->actingAs($this->adminUser())
            ->post(route('applications.encode.store'), [
                'member_id'                    => $member->id,
                'loan_type'                    => 'regular',
                'amount'                       => 5000,
                'term_months'                  => 36,
                'purpose'                      => 'Personal',
                'monthly_income'               => 50000,
                'existing_monthly_obligations' => 0,
                'source_of_income'             => 'Salary',
            ])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Monthly credit limit has not been assessed'));

        // approve: blocked
        $app = LoanApplication::factory()->create([
            'user_id'                      => $member->user_id,
            'email'                        => $member->email,
            'loan_type'                    => 'regular',
            'amount'                       => 5000,
            'term_months'                  => 36,
            'monthly_income'               => 50000,
            'existing_monthly_obligations' => 0,
            'status'                       => 'pending',
            'recommended_amount'           => 5000,
            'risk_level'                   => 'Low',
            'risk_score'                   => 80,
        ]);
        $mirror = Application::create([
            'loan_application_id' => $app->id,
            'app_id'              => 'APP-P2N',
            'applicant'           => $app->full_name,
            'loan_type'           => $app->loan_type,
            'amount'              => $app->amount,
            'status'              => 'Pending',
            'recommended_amount'  => 5000,
            'risk_level'          => 'Low',
            'risk_score'          => 80,
        ]);
        $response = $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']))
            ->postJson(route('applications.approve', $mirror->id), ['approved_amount' => 5000]);

        $response->assertStatus(400)->assertJson(['success' => false]);
        $this->assertStringContainsString(
            'Monthly credit limit has not been assessed',
            json_decode($response->getContent(), true)['message']
        );
    }

    /** @test */
    public function part2_no_loan_product_is_exempt_while_evaluate_hardcodes_requires_limit()
    {
        $types = array_keys(config('loan_matrix.loan_types', []));
        $this->assertNotEmpty($types);
        foreach ($types as $type) {
            $this->assertTrue(
                LoanProductHelper::requiresCreditLimit($type),
                "Product {$type} is exempt from credit limits but LoanEvaluationService hardcodes \$requiresLimit = true"
            );
        }
    }

    // ── Member / limit management behaviour (unchanged by the rule swap) ─

    /** @test */
    public function update_borrower_auto_recomputes_credit_limits_when_income_changes()
    {
        $admin = $this->adminUser();
        $member = $this->makeMember([
            'monthly_income'                => 30000,
            'existing_monthly_obligations'  => 0,
            'savings_balance'               => 0,
            'approved_credit_limit'         => null,
            'credit_limit_source'           => 'auto',
            'approved_monthly_credit_limit' => null,
            'monthly_credit_limit_source'   => 'auto',
        ]);

        $this->actingAs($admin)
            ->put(route('active-members.update', $member->id), [
                'full_name'     => $member->full_name,
                'email'         => $member->email,
                'phone'         => $member->contact_number,
                'date_of_birth' => optional($member->date_of_birth)->format('Y-m-d'),
                'address'       => $member->address,
                'occupation'    => $member->occupation,
                'monthly_income' => 50000,
                'existing_monthly_obligations' => 0,
                'savings_balance' => 0,
                'status'        => CoopMember::STATUS_ACTIVE,
                // approved_credit_limit + monthly_credit_limit omitted
            ])
            ->assertRedirect();

        $member->refresh();
        $this->assertEquals(17500.0, (float) $member->approved_monthly_credit_limit,
            'Auto monthly limit must recompute when income changes');
        $this->assertGreaterThan(0.0, (float) $member->approved_credit_limit,
            'Auto credit limit must recompute when income changes');
        $this->assertEquals('auto', $member->credit_limit_source);
    }

    /** @test */
    public function member_update_auto_recomputes_credit_limits_when_income_changes()
    {
        $admin = $this->adminUser();
        $member = $this->makeMember([
            'monthly_income'                => 30000,
            'existing_monthly_obligations'  => 0,
            'savings_balance'               => 0,
            'approved_credit_limit'         => null,
            'credit_limit_source'           => 'auto',
            'approved_monthly_credit_limit' => null,
            'monthly_credit_limit_source'   => 'auto',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.members.update', $member->id), [
                'full_name'     => $member->full_name,
                'email'         => $member->email,
                'contact_number' => $member->contact_number,
                'monthly_income' => 50000,
                'existing_monthly_obligations' => 0,
                'savings_balance' => 0,
                // limits omitted
            ])
            ->assertRedirect();

        $member->refresh();
        $this->assertEquals(17500.0, (float) $member->approved_monthly_credit_limit,
            'Auto monthly limit must recompute when income changes (profile flow)');
        $this->assertGreaterThan(0.0, (float) $member->approved_credit_limit,
            'Auto credit limit must recompute when income changes (profile flow)');
    }

    /** @test */
    public function zero_recompute_characterization_stale_credit_limit_kept()
    {
        $admin = $this->adminUser();
        $member = $this->makeMember([
            'monthly_income'                => 50000,
            'existing_monthly_obligations'  => 0,
            'savings_balance'               => 0,
            'approved_credit_limit'         => 100000,
            'credit_limit_source'           => 'auto',
            'approved_monthly_credit_limit' => 17500,
            'monthly_credit_limit_source'   => 'auto',
        ]);

        $this->actingAs($admin)
            ->put(route('active-members.update', $member->id), [
                'full_name'     => $member->full_name,
                'email'         => $member->email,
                'phone'         => $member->contact_number,
                'date_of_birth' => optional($member->date_of_birth)->format('Y-m-d'),
                'address'       => $member->address,
                'occupation'    => $member->occupation,
                'monthly_income' => 50000,
                'existing_monthly_obligations' => 20000, // ≥ 35% of income → capacity 0
                'savings_balance' => 0,
                'status'        => CoopMember::STATUS_ACTIVE,
                // limits omitted → auto path
            ])
            ->assertRedirect();

        $member->refresh();
        $this->assertEquals(0.0, (float) $member->approved_monthly_credit_limit);
        $this->assertEquals(100000.0, (float) $member->approved_credit_limit);
        $this->assertEquals('auto', $member->credit_limit_source);
    }

    /** @test */
    public function update_borrower_manual_source_never_overwritten_on_income_change()
    {
        $admin = $this->adminUser();
        $member = $this->makeMember([
            'monthly_income'                => 30000,
            'existing_monthly_obligations'  => 0,
            'savings_balance'               => 0,
            'approved_credit_limit'         => 123000,
            'credit_limit_source'           => 'manual',
            'approved_monthly_credit_limit' => 9000,
            'monthly_credit_limit_source'   => 'manual',
        ]);

        $this->actingAs($admin)
            ->put(route('active-members.update', $member->id), [
                'full_name'     => $member->full_name,
                'email'         => $member->email,
                'phone'         => $member->contact_number,
                'date_of_birth' => optional($member->date_of_birth)->format('Y-m-d'),
                'address'       => $member->address,
                'occupation'    => $member->occupation,
                'monthly_income' => 50000,
                'existing_monthly_obligations' => 0,
                'savings_balance' => 0,
                'status'        => CoopMember::STATUS_ACTIVE,
                // limits omitted
            ])
            ->assertRedirect();

        $member->refresh();
        $this->assertEquals(123000.0, (float) $member->approved_credit_limit,
            'Manual credit limit must never be overwritten');
        $this->assertEquals(9000.0, (float) $member->approved_monthly_credit_limit,
            'Manual monthly limit must never be overwritten');
        $this->assertEquals('manual', $member->credit_limit_source);
        $this->assertEquals('manual', $member->monthly_credit_limit_source);
    }

    /** @test */
    public function update_borrower_unchanged_financials_leave_limits_alone()
    {
        $admin = $this->adminUser();
        $member = $this->makeMember([
            'monthly_income'                => 30000,
            'existing_monthly_obligations'  => 0,
            'savings_balance'               => 0,
            'approved_credit_limit'         => 100000,
            'credit_limit_source'           => 'auto',
            'approved_monthly_credit_limit' => 10500,
            'monthly_credit_limit_source'   => 'auto',
        ]);

        $this->actingAs($admin)
            ->put(route('active-members.update', $member->id), [
                'full_name'     => $member->full_name,
                'email'         => $member->email,
                'phone'         => $member->contact_number,
                'date_of_birth' => optional($member->date_of_birth)->format('Y-m-d'),
                'address'       => $member->address,
                'occupation'    => $member->occupation,
                'monthly_income' => 30000,
                'existing_monthly_obligations' => 0,
                'savings_balance' => 0,
                'status'        => CoopMember::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $member->refresh();
        $this->assertEquals(100000.0, (float) $member->approved_credit_limit,
            'Unchanged financials must not trigger a recompute');
        $this->assertEquals(10500.0, (float) $member->approved_monthly_credit_limit,
            'Unchanged financials must not trigger a recompute');
    }

    // ── APP-0022: member resolution parity ─────────────────────────────

    /** @test */
    public function app_0022_evaluation_uses_same_member_resolution_as_modal()
    {
        $user = User::factory()->create(['usertype' => 'member']);
        $member = CoopMember::factory()->create([
            'user_id'                  => $user->id,
            'status'                   => CoopMember::STATUS_ACTIVE,
            'monthly_income'           => 50000,
            'existing_monthly_obligations' => 0,
            'savings_balance'          => 0,
            'approved_credit_limit'    => 376000,
            'approved_monthly_credit_limit' => 50000, // manual: 20,000 approved this month fits
            'monthly_credit_limit_source'   => 'manual',
            'is_migs'                  => false,
        ]);
        $user->forceFill(['coop_member_id' => $member->id])->save();
        $member = $member->fresh();

        $approvedLoan = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'status'  => 'approved',
            'amount'  => 20000,
            'term_months' => 36,
            'reviewed_at' => now(),
        ]);
        $approvedLoan->update(['remaining_balance' => 11957.04]);

        $loanApp = LoanApplication::factory()->create([
            'user_id'                  => $user->id,
            'loan_type'                => 'micro',
            'amount'                   => 2000,
            'term_months'              => 12,
            'monthly_income'           => 50000,
            'existing_monthly_obligations' => 0,
        ]);

        $service = app(\App\Services\LoanEvaluationService::class);
        $result = $service->evaluate($loanApp);

        $this->assertNotEquals(0.0, $result['recommended_amount'], 'Evaluation should find member and not zero out recommended amount');
        $this->assertGreaterThan(0, $result['recommended_amount']);

        $coopMemberFromModal = $loanApp->user?->coopMember ?? CoopMember::where('email', $loanApp->email)->first();
        $this->assertNotNull($coopMemberFromModal);
        $this->assertEquals($member->id, $coopMemberFromModal->id);
    }

    private function adminUser(): User
    {
        return User::factory()->asBranchManager()->create();
    }
}

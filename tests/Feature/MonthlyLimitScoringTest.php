<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use App\Services\LoanEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Monthly credit limit scoring tests for the rule-based engine.
 *
 * Tests the monthly_limit_score component and its interaction with risk floors.
 */
class MonthlyLimitScoringTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function monthly_limit_score_full_when_request_fits_available()
    {
        // Member: income 50k, obligations 2k, savings 10k, manual monthly limit 15500.
        // No approvals this month → monthlyUsed = 0 → available = 15500.
        $member = $this->makeMemberWithMonthlyLimit(15500);
        $loanApp = $this->makeLoanApp($member, 5000); // request fits within available

        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        // monthly_limit_score = 15 (full, request <= available)
        $this->assertEquals(15, $result['details']['monthly_limit_score'],
            'monthly_limit_score should be 15 when requested amount fits within available credit');
        $this->assertEquals(83, $result['risk_score'],
            'risk_score should be 83 (30 DTI + 18 payment + 0 docs + 20 savings + 15 monthly limit)');
        $this->assertSame('Low', $result['risk_level'],
            'risk level should be Low (83 >= 70)');
        $this->assertEquals(5000, $result['recommended_amount'],
            'recommended amount should be 5000 (un-capped)');
        // No monthly-limit reason in evaluation_reasons
        $this->assertFalse(
            collect($result['evaluation_reasons'])->contains(
                fn ($r) => str_contains($r, 'above this month\'s available credit')
            ),
            'no monthly-limit risk floor reason when request fits'
        );
    }

    /** @test */
    public function monthly_limit_score_partial_when_request_exceeds_available_but_not_2x()
    {
        // Member: same profile; 10k approved this month → monthlyUsed = 10k → available = 5500.
        $member = $this->makeMemberWithMonthlyLimit(15500);
        $this->approvedThisMonth($member, 10000);
        $loanApp = $this->makeLoanApp($member, 10000); // exceeds 5500, not 2x

        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        // monthly_limit_score = round(15 * min(1, 5500/10000)) = round(8.25) = 8
        $this->assertEquals(8, $result['details']['monthly_limit_score'],
            'monthly_limit_score should be 8 (proportional to 5,500 / 10,000)');
        $this->assertEquals(76, $result['risk_score'],
            'risk_score should be 76 (30 DTI + 18 payment + 0 docs + 20 savings + 8 monthly limit)');
        $this->assertSame('Medium', $result['risk_level'],
            'risk level should be Medium (floor raised from Low to Medium)');
        $this->assertEquals(5500, $result['recommended_amount'],
            'recommended amount should be capped at 5,500 available');
        // Should include the "raised to Medium" reason
        $this->assertTrue(
            collect($result['evaluation_reasons'])->contains(
                fn ($r) => str_contains($r, 'above this month\'s available credit')
                    && str_contains($r, 'risk raised to Medium')
            ),
            'evaluation reasons should include the "raised to Medium" floor reason'
        );
    }

    /** @test */
    public function monthly_limit_score_proportional_when_request_significantly_exceeds_available()
    {
        // Member: 15k approved this month → monthlyUsed = 15k → available = 500.
        $member = $this->makeMemberWithMonthlyLimit(15500);
        $this->approvedThisMonth($member, 15000);
        $loanApp = $this->makeLoanApp($member, 5000); // exceeds 2x available (1000)

        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        // monthly_limit_score = round(15 * min(1, 500/5000)) = round(1.5) = 2
        $this->assertEquals(2, $result['details']['monthly_limit_score'],
            'monthly_limit_score should be 2 (PHP round(1.5)=2)');
        $this->assertEquals(70, $result['risk_score'],
            'risk_score should be 70 (30 DTI + 18 payment + 0 docs + 20 savings + 2 monthly limit)');
        $this->assertSame('High', $result['risk_level'],
            'risk level should be High (floor raised from Medium to High)');
        $this->assertEquals(500, $result['recommended_amount'],
            'recommended amount should be capped at 500');
        // Should include the "more than twice available" reason
        $this->assertTrue(
            collect($result['evaluation_reasons'])->contains(
                fn ($r) => str_contains($r, 'more than twice this month\'s available credit')
                    && str_contains($r, 'risk raised to High')
            ),
            'evaluation reasons should include the "more than twice available" floor reason'
        );
    }

    /** @test */
    public function monthly_limit_score_zero_when_available_is_zero()
    {
        // Member: two approvals this month (10k + 5.5k) → monthlyUsed = 15.5k → available = 0.
        $member = $this->makeMemberWithMonthlyLimit(15500);
        $this->approvedThisMonth($member, 10000);
        $this->approvedThisMonth($member, 5500);
        $loanApp = $this->makeLoanApp($member, 5500); // request within total limit but available = 0

        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        // monthly_limit_score = 0 (available == 0)
        $this->assertEquals(0, $result['details']['monthly_limit_score'],
            'monthly_limit_score should be 0 when available credit is 0');
        $this->assertEquals(68, $result['risk_score'],
            'risk_score should be 68 (30 DTI + 18 payment + 0 docs + 20 savings + 0 monthly limit)');
        $this->assertSame('High', $result['risk_level'],
            'risk level should be High (forced by existing rule at 0 available)');
        $this->assertEquals(0, $result['recommended_amount'],
            'recommended amount should be 0 when no available credit');
        // Reasons should include the no-limit-left and forced-High lines but NOT the old 2x-escalation line
        $joinedReasons = implode(' ', $result['evaluation_reasons']);
        $this->assertStringContainsString(
            'No monthly credit limit left this month',
            $joinedReasons,
            'reasons should include the no-limit-left line'
        );
        $this->assertStringContainsString(
            'Risk forced to High',
            $joinedReasons,
            'reasons should include the forced-to-High line'
        );
        $this->assertStringNotContainsString(
            'exceeds recommended by more than 2x',
            $joinedReasons,
            'reasons should NOT include the old 2x-escalation line when level already High'
        );
    }

    /** @test */
    public function monthly_limit_score_unassessed_no_penalty()
    {
        // Member: monthly income null → monthlyCapacity null → monthlyAvailable null.
        // Also set approved_monthly_credit_limit null so engine skips monthly cap.
        // Per task: unassessed gives full 15 with no floor.
        $member = CoopMember::factory()->create([
            'user_id' => null,
            'status' => CoopMember::STATUS_ACTIVE,
            'monthly_income' => null,
            'existing_monthly_obligations' => 0,
            'savings_balance' => 10000,
            'approved_credit_limit' => 1000000,
            'approved_monthly_credit_limit' => null,
            'monthly_credit_limit_source' => null,
            'is_migs' => false,
        ]);
        $user = User::factory()->create(['usertype' => 'member']);
        $user->forceFill(['coop_member_id' => $member->id])->save();

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'regular',
            'amount' => 10000,
            'term_months' => 12,
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 2000,
            'documents' => ['id.pdf'],
        ]);

        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        // monthly_limit_score = 15 (full, unassessed; no risk floor)
        $this->assertEquals(15, $result['details']['monthly_limit_score'],
            'unassessed monthly limit should give full 15 monthly_limit_score');
        $this->assertEquals(83, $result['risk_score'],
            'risk_score should be 83 even with unassessed monthly limit');
        $this->assertSame('Low', $result['risk_level'],
            'risk level should be Low (no floor applied when monthly limit unassessed)');
        $this->assertEquals(10000, $result['recommended_amount'],
            'recommended amount should NOT be capped when monthly limit unassessed');
        // No monthly-limit reason when unassessed
        $this->assertFalse(
            collect($result['evaluation_reasons'])->contains(
                fn ($r) => str_contains($r, 'above this month\'s available credit')
                    || str_contains($r, 'more than twice')
            ),
            'no monthly-limit risk floor reason when monthly limit unassessed'
        );
    }

    /** @test */
    public function the_weights_total_exactly_100()
    {
        $this->assertEquals(
            100,
            array_sum(config('loan_evaluation.weights')),
            'Scoring weights must always sum to 100 — the total is the risk score.'
        );
    }

    /** @test */
    public function boundary_request_exactly_2x_available_medium()
    {
        // available = 5500; request exactly 11,000 (= 2x available) → Medium.
        $member = $this->makeMemberWithMonthlyLimit(15500);
        $this->approvedThisMonth($member, 10000);
        $loanApp = $this->makeLoanApp($member, 11000); // exactly 2x 5500

        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        // monthly_limit_score = round(15 * min(1, 5500/11000)) = round(7.5) = 8
        $this->assertEquals(8, $result['details']['monthly_limit_score']);
        $this->assertSame('Medium', $result['risk_level'],
            'at exactly 2x available, risk should be Medium (not yet High)');
        // The floor rule: $amount > 2*available → High; not satisfied when equal
        $this->assertStringNotContainsString(
            'more than twice',
            implode(' ', $result['evaluation_reasons']),
            'at exactly 2x, should NOT have "more than twice" reason'
        );
    }

    /** @test */
    public function boundary_request_2x_available_plus_1_high()
    {
        // available = 5500; request 11,001 (= 2x+1) → High.
        $member = $this->makeMemberWithMonthlyLimit(15500);
        $this->approvedThisMonth($member, 10000);
        $loanApp = $this->makeLoanApp($member, 11001);

        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        $this->assertSame('High', $result['risk_level'],
            'at 2x+1 available, risk should be High');
        $this->assertStringContainsString(
            'more than twice',
            implode(' ', $result['evaluation_reasons']),
            'above 2x available should have "more than twice" reason'
        );
    }

    // ── Helpers ────────────────────────────────────────────────────────

    /** Approved loan that counts toward THIS month's used allowance. */
    private function approvedThisMonth(CoopMember $member, float $amount, array $overrides = []): LoanApplication
    {
        return LoanApplication::factory()->create(array_merge([
            'user_id'     => $member->user_id,
            'email'       => $member->email,
            'status'      => \App\Support\LoanStatus::APPROVED,
            'amount'      => $amount,
            'term_months' => 12,
            'reviewed_at' => now(),
        ], $overrides));
    }

    private function makeMemberWithMonthlyLimit(int $monthlyLimit): CoopMember
    {
        $user = User::factory()->create(['usertype' => 'member']);
        $member = CoopMember::factory()->create(array_merge([
            'user_id'                  => $user->id,
            'status'                   => CoopMember::STATUS_ACTIVE,
            'monthly_income'           => 50000,
            'existing_monthly_obligations' => 2000,
            'savings_balance'          => 10000,
            'approved_credit_limit'    => 1000000,
            'approved_monthly_credit_limit' => $monthlyLimit,
            'monthly_credit_limit_source'   => 'manual',
            'is_migs'                  => false,
        ], []));

        $user->forceFill(['coop_member_id' => $member->id])->save();
        return $member->fresh();
    }

    private function makeLoanApp(CoopMember $member, int $amount): LoanApplication
    {
        // No approvals this month → monthlyUsed = 0 → available = monthlyLimit.
        // Create approved loan that counts toward monthlyUsed only if needed.
        // For now, the member starts with 0 approvals this month.
        return LoanApplication::factory()->create(array_merge([
            'user_id'                        => $member->user_id,
            'loan_type'                      => 'regular',
            'amount'                         => $amount,
            'term_months'                    => 12,
            'monthly_income'                 => 50000,
            'existing_monthly_obligations'   => 2000,
        ], []));
    }
}
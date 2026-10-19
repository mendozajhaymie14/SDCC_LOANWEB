<?php

namespace Tests\Feature;

use App\Models\LoanApplication;
use App\Models\User;
use App\Models\CoopMember;
use App\Services\LoanEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanEvaluationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function low_risk_application_passes()
    {
        $user = User::factory()->create();
        CoopMember::factory()->create([
            'user_id' => $user->id,
            'share_capital' => 50000,
            'savings_balance' => 10000,
            'approved_credit_limit' => 100000,
            'is_migs' => false,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'regular',
            'amount' => 50000,
            'term_months' => 12,
            'monthly_income' => 30000,
            'existing_monthly_obligations' => 5000,
            'share_capital' => 50000,
            'documents' => json_encode(['id1.pdf', 'id2.pdf']),
        ]);

        $service = app(LoanEvaluationService::class);
        $result = $service->evaluate($loanApp);

        $this->assertTrue($result['eligible']);
        $this->assertEquals('Low', $result['risk_level']);
        $this->assertGreaterThanOrEqual(70, $result['risk_score']);
        $this->assertEquals(0, $result['rejection_reason']);
    }

    /** @test */
    public function high_risk_application_fails_dti()
    {
        $user = User::factory()->create();
        CoopMember::factory()->create([
            'user_id' => $user->id,
            'share_capital' => 10000,
        ]);

        // Budget = (15000 * 0.35) - 10000 = -4750 → affordable = 0 → ineligible
        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'regular',
            'amount' => 100000,
            'term_months' => 12,
            'monthly_income' => 15000,
            'existing_monthly_obligations' => 10000,
            'share_capital' => 10000,
        ]);

        $service = app(LoanEvaluationService::class);
        $result = $service->evaluate($loanApp);

        $this->assertFalse($result['eligible']);
        $this->assertEquals('High', $result['risk_level']);
        $this->assertLessThan(50, $result['risk_score']);
        $this->assertNotNull($result['rejection_reason']);
        $this->assertStringContainsString('Debt-to-income ratio', implode(' ', $result['reasons']));
        $this->assertEquals(0, $result['recommended_amount']);
    }

    /** @test */
    public function character_loan_tiers_respected()
    {
        $user = User::factory()->create();
        CoopMember::factory()->create([
            'user_id' => $user->id,
            'share_capital' => 20000,
        ]);

        // Case 1: 200% tier (DTI passes)
        // income=20000, oblig=5000, budget=(20000*0.35)-5000=2000
        // affordable = 2000 / 0.03508 ≈ 57000 > 40000 (200% tier) → passes
        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'character',
            'amount' => 40000,
            'term_months' => 36,
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 5000,
            'share_capital' => 20000,
        ]);

        $service = app(LoanEvaluationService::class);
        $result = $service->evaluate($loanApp);

        $this->assertTrue($result['eligible']);
        $this->assertEquals(40000, $result['recommended_amount']);

        // Case 2: 100% tier (200% fails DTI, 100% passes)
        // income=20000, oblig=6000, budget=7000-6000=1000
        // affordable = 1000 / 0.03508 ≈ 28508
        // 200% tier = min(40000, 40000) = 40000 > 28508 → fails DTI
        // 100% tier = min(40000, 20000) = 20000 ≤ 28508 → passes
        $loanApp2 = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'character',
            'amount' => 40000,
            'term_months' => 36,
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 6000,
            'share_capital' => 20000,
        ]);

        $result2 = $service->evaluate($loanApp2);
        $this->assertTrue($result2['eligible']);
        $this->assertEquals(20000, $result2['recommended_amount']);

        // Case 3: 50% tier (both 200% and 100% fail DTI)
        // income=20000, oblig=10000, budget=7000-10000=-3000 → affordable=0
        // 200% tier = 40000 > 0 → fails
        // 100% tier = 20000 > 0 → fails
        // 50% tier = min(40000, 10000) = 10000 (no DTI required)
        $loanApp3 = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'character',
            'amount' => 40000,
            'term_months' => 36,
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 10000,
            'share_capital' => 20000,
        ]);

        $result3 = $service->evaluate($loanApp3);
        $this->assertTrue($result3['eligible']);
        $this->assertEquals(10000, $result3['recommended_amount']);
    }

    /** @test */
    public function special_loan_requires_min_share_capital()
    {
        $user = User::factory()->create();
        CoopMember::factory()->create([
            'user_id' => $user->id,
            'share_capital' => 50000,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'special',
            'amount' => 50000,
            'term_months' => 120,
            'monthly_income' => 30000,
            'existing_monthly_obligations' => 5000,
            'share_capital' => 50000,
        ]);

        $service = app(LoanEvaluationService::class);
        $result = $service->evaluate($loanApp);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('share capital', implode(' ', $result['reasons']));
    }

    /** @test */
    public function recovery_loan_migs_vs_non_migs()
    {
        // MIGS member
        $userMigs = User::factory()->create();
        CoopMember::factory()->create([
            'user_id' => $userMigs->id,
            'share_capital' => 50000,
            'is_migs' => true,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $userMigs->id,
            'loan_type' => 'recovery',
            'amount' => 25000,
            'term_months' => 24,
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 2000,
            'share_capital' => 50000,
        ]);

        $service = app(LoanEvaluationService::class);
        $resultMigs = $service->evaluate($loanApp);

        // NON-MIGS member
        $userNonMigs = User::factory()->create();
        CoopMember::factory()->create([
            'user_id' => $userNonMigs->id,
            'share_capital' => 50000,
            'is_migs' => false,
        ]);

        $loanApp2 = LoanApplication::factory()->create([
            'user_id' => $userNonMigs->id,
            'loan_type' => 'recovery',
            'amount' => 25000,
            'term_months' => 24,
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 2000,
            'share_capital' => 50000,
        ]);

        $resultNonMigs = $service->evaluate($loanApp2);

        // MIGS: 50% of 50000 = 25000, capped at 30000 → 25000
        $this->assertEquals(25000, $resultMigs['recommended_amount']);

        // NON-MIGS: 35% of 50000 = 17500, capped at 15000 → 15000
        $this->assertEquals(15000, $resultNonMigs['recommended_amount']);
    }

    /** @test */
    public function share_capital_loan_no_dti_check()
    {
        $user = User::factory()->create();
        CoopMember::factory()->create([
            'user_id' => $user->id,
            'share_capital' => 10000,
        ]);

        // High DTI but should still qualify (no DTI check for Share Capital Loan)
        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'share_cap',
            'amount' => 5000,
            'term_months' => 12,
            'monthly_income' => 10000,
            'existing_monthly_obligations' => 15000,
            'share_capital' => 10000,
        ]);

        $service = app(LoanEvaluationService::class);
        $result = $service->evaluate($loanApp);

        $this->assertTrue($result['eligible']);
        $this->assertEquals(5000, $result['recommended_amount']);
    }

    /** @test */
    public function utility_loan_capped_at_10000()
    {
        $user = User::factory()->create();
        CoopMember::factory()->create([
            'user_id' => $user->id,
            'share_capital' => 50000,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'utility',
            'amount' => 20000,
            'term_months' => 12,
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 2000,
            'share_capital' => 50000,
        ]);

        $service = app(LoanEvaluationService::class);
        $result = $service->evaluate($loanApp);

        $this->assertTrue($result['eligible']);
        $this->assertEquals(10000, $result['recommended_amount']);
    }

    /** @test */
    public function emergency_loan_capped_at_requested_amount()
    {
        $user = User::factory()->create();
        CoopMember::factory()->create([
            'user_id' => $user->id,
            'share_capital' => 50000,
            'is_migs' => false,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'emergency',
            'amount' => 10000,
            'term_months' => 12,
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 0,
            'share_capital' => 50000,
        ]);

        $service = app(LoanEvaluationService::class);
        $result = $service->evaluate($loanApp);

        $this->assertTrue($result['eligible']);
        // Emergency loan should be capped at the requested amount: min(10000, affordable * 0.20)
        // With DTI 35% and income 50000: affordable principal ≈ 98000, so affordable * 0.20 = 19600
        // But capped at requested amount of 10000
        $this->assertEquals(10000, $result['recommended_amount']);
    }

    /** @test */
    public function risk_elevated_to_medium_when_requested_exceeds_double_recommended()
    {
        $user = User::factory()->create();
        CoopMember::factory()->create([
            'user_id' => $user->id,
            'share_capital' => 30000,
            'is_migs' => false,
        ]);

        // utility loan is capped at ₱10 000 regardless of requested amount.
        // With income ₱10 000, EMI on ₱30 000 is ~₱2 665 → DTI ~27 % (within 35 % limit).
        // Base score: 40 (DTI) + 20 (no loan history) + 15 (docs) + 15 (share-cap) = 90 → Low.
        // But requested 30000 > 2 × 10000 → bump to Medium.
        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'utility',
            'amount' => 30000,
            'term_months' => 12,
            'monthly_income' => 10000,
            'existing_monthly_obligations' => 0,
            'share_capital' => 30000,
            'documents' => ['doc1.pdf'],
        ]);

        $service = app(LoanEvaluationService::class);
        $result = $service->evaluate($loanApp);

        $this->assertEquals(10000, $result['recommended_amount']);
        $this->assertEquals('Medium', $result['risk_level']);
        $this->assertStringContainsString('exceeds recommended by more than 2x', implode(' ', $result['reasons']));
    }

    /** @test */
    public function payment_history_neutral_with_no_loan_history()
    {
        // Member with no prior loans: payment history gives 20 (not 30),
        // so a high request for low income is not scored as "Low".
        $user = User::factory()->create();
        CoopMember::factory()->create([
            'user_id' => $user->id,
            'share_capital' => 10000,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'educational',
            'amount' => 500000,
            'term_months' => 12,
            'monthly_income' => 15000,
            'existing_monthly_obligations' => 0,
            'share_capital' => 10000,
        ]);

        $service = app(LoanEvaluationService::class);
        $result = $service->evaluate($loanApp);

        // With no loan history, payment score = 20, DTI on requested amount will
        // be high, cap score low → risk must not be Low.
        $this->assertTrue($result['recommended_amount'] < 125000); // 25% of requested
        $this->assertNotEquals('Low', $result['risk_level']);
    }

    /** @test */
    public function educational_loan_falls_when_requested_exceeds_affordable()
    {
        // Large request for low income: DTI and coverage penalize the score
        // based on REQUESTED amount, so it must not be Low.
        $user = User::factory()->create();
        CoopMember::factory()->create([
            'user_id' => $user->id,
            'share_capital' => 30000,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'educational',
            'amount' => 500000,
            'term_months' => 12,
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital' => 30000,
        ]);

        $service = app(LoanEvaluationService::class);
        $result = $service->evaluate($loanApp);

        // Requested amount far above affordable → risk must not be Low.
        $this->assertLessThan(250000, $result['recommended_amount']);
        $this->assertEquals('High', $result['risk_level']);
    }

    /** @test */
    public function micro_loan_capped_at_100000()
    {
        $user = User::factory()->create();
        CoopMember::factory()->create([
            'user_id' => $user->id,
            'share_capital' => 50000,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'micro',
            'amount' => 150000,
            'term_months' => 12,
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 2000,
            'share_capital' => 50000,
        ]);

        $service = app(LoanEvaluationService::class);
        $result = $service->evaluate($loanApp);

        $this->assertTrue($result['eligible']);
        $this->assertEquals(100000, $result['recommended_amount']);
    }

    /** @test */
    public function regular_loan_affordable_principal_limited()
    {
        $user = User::factory()->create();
        CoopMember::factory()->create([
            'user_id' => $user->id,
            'share_capital' => 50000,
        ]);

        // DTI limits but doesn't zero out:
        // income=30000, oblig=8000, budget=(30000*0.35)-8000=2500
        // affordable = 2500 / 0.088858 ≈ 28136 → roundDown1000 = 28000
        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'regular',
            'amount' => 100000,
            'term_months' => 12,
            'monthly_income' => 30000,
            'existing_monthly_obligations' => 8000,
            'share_capital' => 50000,
        ]);

        $service = app(LoanEvaluationService::class);
        $result = $service->evaluate($loanApp);

        // Should be limited by DTI, not by requested amount or share capital
        $this->assertTrue($result['eligible']);
        $this->assertLessThan(100000, $result['recommended_amount']);
        $this->assertLessThan(50000, $result['recommended_amount']);
        $this->assertEquals(28000, $result['recommended_amount']);
    }
}
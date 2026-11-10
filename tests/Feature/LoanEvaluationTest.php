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
            'savings_balance' => 10000,
            'is_migs' => false,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'regular',
            'amount' => 50000,
            'term_months' => 12,
            'monthly_income' => 30000,
            'existing_monthly_obligations' => 5000,
            'documents' => json_encode(['id1.pdf', 'id2.pdf']),
        ]);

        $service = app(LoanEvaluationService::class);
        $result = $service->evaluate($loanApp);

        $this->assertTrue($result['eligible']);
        $this->assertEquals('Low', $result['risk_level']);
        $this->assertGreaterThanOrEqual(70, $result['risk_score']);
        $this->assertNull($result['rejection_reason']);
    }

    /** @test */
    public function high_risk_application_fails_dti()
    {
        $user = User::factory()->create();
        CoopMember::factory()->create([
            'user_id' => $user->id,
            'savings_balance' => 0,
        ]);

        // Budget = (15000 * 0.35) - 10000 = -4750 → affordable = 0 → ineligible
        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'regular',
            'amount' => 100000,
            'term_months' => 12,
            'monthly_income' => 15000,
            'existing_monthly_obligations' => 10000,
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
    public function special_loan_requires_min_savings_balance()
    {
        $user = User::factory()->create();
        CoopMember::factory()->create([
            'user_id' => $user->id,
            'savings_balance' => 50000,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'special',
            'amount' => 50000,
            'term_months' => 120,
            'monthly_income' => 30000,
            'existing_monthly_obligations' => 5000,
        ]);

        $service = app(LoanEvaluationService::class);
        $result = $service->evaluate($loanApp);

        // savings_balance is 50000 < 100000 required → ineligible
        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('savings balance', implode(' ', $result['reasons']));
    }

    /** @test */
    public function utility_loan_capped_at_10000()
    {
        $user = User::factory()->create();
        CoopMember::factory()->create([
            'user_id' => $user->id,
            'savings_balance' => 50000,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'utility',
            'amount' => 20000,
            'term_months' => 12,
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 2000,
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
            'savings_balance' => 50000,
            'is_migs' => false,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'emergency',
            'amount' => 10000,
            'term_months' => 12,
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 0,
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
            'savings_balance' => 30000,
            'is_migs' => false,
        ]);

        // utility loan is capped at ₱10 000 regardless of requested amount.
        // With income ₱10 000, EMI on ₱30 000 is ~₱2 665 → DTI ~27 % (within 35 % limit).
        // Base score: 40 (DTI) + 20 (no loan history) + 15 (docs) + savings score on 30000/30000 = 15 → 90 → Low.
        // But requested 30000 > 2 × 10000 → bump to Medium.
        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'utility',
            'amount' => 30000,
            'term_months' => 12,
            'monthly_income' => 10000,
            'existing_monthly_obligations' => 0,
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
            'savings_balance' => 10000,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'educational',
            'amount' => 500000,
            'term_months' => 12,
            'monthly_income' => 15000,
            'existing_monthly_obligations' => 0,
        ]);

        $service = app(LoanEvaluationService::class);
        $result = $service->evaluate($loanApp);

        // With no loan history, payment score = 20, DTI on requested amount will
        // be high, cap score low → risk must not be Low.
        $this->assertEquals(500000, $result['recommended_amount']); // capped by credit limit
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
            'savings_balance' => 30000,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'educational',
            'amount' => 500000,
            'term_months' => 12,
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 0,
        ]);

        $service = app(LoanEvaluationService::class);
        $result = $service->evaluate($loanApp);

        // Requested amount far above affordable → risk must not be Low.
        $this->assertEquals(500000, $result['recommended_amount']); // capped by credit limit
        $this->assertEquals('High', $result['risk_level']);
    }

    /** @test */
    public function micro_loan_capped_at_100000()
    {
        $user = User::factory()->create();
        CoopMember::factory()->create([
            'user_id' => $user->id,
            'savings_balance' => 50000,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'loan_type' => 'micro',
            'amount' => 150000,
            'term_months' => 12,
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 2000,
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
            'savings_balance' => 50000,
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
        ]);

        $service = app(LoanEvaluationService::class);
        $result = $service->evaluate($loanApp);

        // Should be limited by DTI, not by requested amount or savings
        $this->assertTrue($result['eligible']);
        $this->assertLessThan(100000, $result['recommended_amount']);
        $this->assertLessThan(50000, $result['recommended_amount']);
        $this->assertEquals(28000, $result['recommended_amount']);
    }
}
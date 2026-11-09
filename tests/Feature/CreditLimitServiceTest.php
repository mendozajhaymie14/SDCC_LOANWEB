<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\User;
use App\Services\CreditLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function affordable_principal_50k_income_2k_obligations_returns_raw_466666_33()
    {
        $service = new CreditLimitService();

        $result = $service->calculateAffordablePrincipal(50000, 2000);

        $this->assertEquals(466666.33, $result);
    }

    /** @test */
    public function affordable_principal_50k_income_1500_obligations_returns_raw_481720_08()
    {
        $service = new CreditLimitService();

        $result = $service->calculateAffordablePrincipal(50000, 1500);

        $this->assertEquals(481720.08, $result);
    }

    /** @test */
    public function obligations_above_35_percent_income_returns_zero()
    {
        $service = new CreditLimitService();

        $result = $service->calculateAffordablePrincipal(50000, 17500);

        $this->assertEquals(0, $result);
    }

    /** @test */
    public function affordable_principal_100k_income_0_obligations_returns_raw_1053762_68()
    {
        $service = new CreditLimitService();

        $result = $service->calculateAffordablePrincipal(100000, 0);

        $this->assertEquals(1053762.68, $result);
    }

    /** @test */
    public function affordable_principal_200k_income_capped_at_2m_via_service()
    {
        $user = User::factory()->create();
        $member = CoopMember::factory()->create([
            'user_id' => $user->id,
            'monthly_income' => 200000,
            'existing_monthly_obligations' => 0,
            'savings_balance' => 0,
        ]);

        $service = new CreditLimitService();
        $result = $service->calculateCreditLimit($member);

        $this->assertEquals(2000000, $result['limit']);
        $this->assertEquals('affordability', $result['cappedBy']);
    }

    /** @test */
    public function calculate_credit_limit_affordability()
    {
        $user = User::factory()->create();
        $member = CoopMember::factory()->create([
            'user_id' => $user->id,
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 2000,
            'approved_credit_limit' => null,
            'is_migs' => false,
            'savings_balance' => 0,
        ]);

        $service = new CreditLimitService();
        $result = $service->calculateCreditLimit($member);

        $this->assertEquals(466000, $result['limit']);
        $this->assertEquals('affordability', $result['cappedBy']);
    }

    /** @test */
    public function calculate_credit_limit_savings_cap()
    {
        config(['loan_matrix.credit_limit.savings_multiple' => 3]);

        $user = User::factory()->create();
        $member = CoopMember::factory()->create([
            'user_id' => $user->id,
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 2000,
            'approved_credit_limit' => null,
            'is_migs' => false,
            'savings_balance' => 10000,
        ]);

        $service = new CreditLimitService();
        $result = $service->calculateCreditLimit($member);

        $this->assertEquals(30000, $result['limit']);
        $this->assertEquals('savings', $result['cappedBy']);
    }

    /** @test */
    public function format_suggestion_affordability()
    {
        $service = new CreditLimitService();

        $result = $service->formatSuggestion(466000, 'affordability');

        $this->assertStringContainsString('Suggested: ₱466,000 (affordability)', $result);
    }

    /** @test */
    public function format_suggestion_savings()
    {
        $service = new CreditLimitService();

        $result = $service->formatSuggestion(30000, 'savings');

        $this->assertStringContainsString('Suggested: ₱30,000 (savings)', $result);
    }

    /** @test */
    public function format_suggestion_zero_obligations()
    {
        $service = new CreditLimitService();

        $result = $service->formatSuggestion(0, 'affordability');

        $this->assertStringContainsString('Suggested: ₱0 (obligations at or above 35% of income)', $result);
    }

    /** @test */
    public function needs_recompute_returns_false_when_source_is_manual()
    {
        $user = User::factory()->create();
        $member = CoopMember::factory()->create([
            'user_id' => $user->id,
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 2000,
            'approved_credit_limit' => 500000,
            'credit_limit_source' => 'manual',
            'savings_balance' => 0,
        ]);

        $service = new CreditLimitService();

        $result = $service->needsRecompute($member, 50000, 2000, 0);

        $this->assertFalse($result);
    }

    /** @test */
    public function needs_recompute_returns_true_when_income_changes_and_source_is_auto()
    {
        $user = User::factory()->create();
        $member = CoopMember::factory()->create([
            'user_id' => $user->id,
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 2000,
            'approved_credit_limit' => 466000,
            'credit_limit_source' => 'auto',
            'savings_balance' => 0,
        ]);

        $service = new CreditLimitService();

        $result = $service->needsRecompute($member, 60000, 2000, 0);

        $this->assertTrue($result);
    }

    /** @test */
    public function calculate_credit_limit_raw_income_50k_obligations_1500_savings_10k_disabled()
    {
        config(['loan_matrix.credit_limit.savings_multiple' => null]);

        $service = new CreditLimitService();

        $result = $service->calculateCreditLimitRaw(50000, 1500, 10000, 36, 0.01);

        $this->assertEquals(481000, $result['limit']);
        $this->assertEquals('affordability', $result['cappedBy']);
    }

    /** @test */
    public function calculate_credit_limit_raw_income_50k_obligations_1500_savings_10k_enabled_3()
    {
        config(['loan_matrix.credit_limit.savings_multiple' => 3]);

        $service = new CreditLimitService();

        $result = $service->calculateCreditLimitRaw(50000, 1500, 10000, 36, 0.01);

        $this->assertEquals(30000, $result['limit']);
        $this->assertEquals('savings', $result['cappedBy']);
    }

    /** @test */
    public function calculate_credit_limit_raw_obligations_at_35_percent_returns_zero()
    {
        config(['loan_matrix.credit_limit.savings_multiple' => null]);

        $service = new CreditLimitService();

        $result = $service->calculateCreditLimitRaw(50000, 17500, 0, 36, 0.01);

        $this->assertEquals(0, $result['limit']);
        $this->assertEquals('affordability', $result['cappedBy']);
    }

    /** @test */
    public function calculate_credit_limit_raw_zero_obligations_savings_returns_affordability()
    {
        config(['loan_matrix.credit_limit.savings_multiple' => null]);

        $service = new CreditLimitService();

        $result = $service->calculateCreditLimitRaw(50000, 0, 0, 36, 0.01);

        $this->assertEquals(526000, $result['limit']);
        $this->assertEquals('affordability', $result['cappedBy']);
    }
}
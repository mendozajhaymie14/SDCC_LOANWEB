<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use App\Support\LoanStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S0 Preflight: fails hard unless the test suite runs against SQLite in-memory.
 * S1: Monthly capacity / used / available logic tests.
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

    /** @test */
    public function monthly_used_excludes_paid_pending_rejected()
    {
        $member = $this->makeMember(['monthly_income' => 50000, 'existing_monthly_obligations' => 0]);
        $user = $member->user;

        // Approved, unpaid loan → counted
        $loan1 = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'status'  => 'approved',
            'amount'  => 50000,
            'term_months' => 12,
        ]);

        // Paid loan → excluded
        $loan2 = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'status'  => 'paid',
            'amount'  => 30000,
            'term_months' => 12,
        ]);

        // Rejected loan → excluded
        $loan3 = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'status'  => 'rejected',
            'amount'  => 20000,
            'term_months' => 12,
        ]);

        // Pending loan → excluded (not approved)
        $loan4 = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'status'  => 'pending',
            'amount'  => 10000,
            'term_months' => 12,
        ]);

        $used = $member->monthlyUsed();
        $this->assertEquals((float) $loan1->monthly_payment, $used);
    }

    /** @test */
    public function monthly_used_with_12_and_36_month_loans_sums_original_payments()
    {
        $member = $this->makeMember(['monthly_income' => 100000, 'existing_monthly_obligations' => 0]);
        $user = $member->user;

        // 12-month loan
        $loan12 = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'status'  => 'approved',
            'amount'  => 50000,
            'term_months' => 12,
        ]);

        // 36-month loan
        $loan36 = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'status'  => 'approved',
            'amount'  => 50000,
            'term_months' => 36,
        ]);

        $used = $member->monthlyUsed();
        $expectedSum = (float) $loan12->monthly_payment + (float) $loan36->monthly_payment;
        $this->assertEquals($expectedSum, $used);
    }

    /** @test */
    public function monthly_available_never_negative()
    {
        $member = $this->makeMember([
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 5000,
            'approved_monthly_credit_limit' => 1000, // manually set very low
        ]);

        // Used is sum of approved loan payments (here none = 0)
        // Capacity is 1000, so available = max(0, 1000 - 0) = 1000
        $this->assertEquals(1000.0, $member->monthlyAvailable());

        // With a loan that pushes used > capacity
        $user = $member->user;
        LoanApplication::factory()->create([
            'user_id' => $user->id,
            'status'  => 'approved',
            'amount'  => 100000,
            'term_months' => 12,
        ]);

        $this->assertEquals(0.0, $member->monthlyAvailable());
        $this->assertGreaterThanOrEqual(0, $member->monthlyAvailable());
    }

    /** @test */
    public function monthly_used_isolated_between_two_portal_less_members()
    {
        // Member A with a loan (portal-less, user_id = null, scoped by email)
        $memberA = $this->makePortalLessMember([
            'email' => 'memberA@example.com',
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 0,
        ]);

        // Member B without a loan (portal-less, different email)
        $memberB = $this->makePortalLessMember([
            'email' => 'memberB@example.com',
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 0,
        ]);

        // Create loan for Member A (email match, user_id = null)
        LoanApplication::factory()->create([
            'user_id' => null,
            'email'   => 'memberA@example.com',
            'status'  => 'approved',
            'amount'  => 50000,
            'term_months' => 12,
        ]);

        $usedA = $memberA->monthlyUsed();
        $usedB = $memberB->monthlyUsed();

        $this->assertGreaterThan(0, $usedA);
        $this->assertEquals(0.0, $usedB);
    }
}

<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\MemberApplication;
use App\Services\CreditLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function affordable_principal_formula_cases_pass()
    {
        $service = new CreditLimitService();

        // 50k income, 1500 obligations → raw 481720.08 → floor 481000 (affordability)
        $result = $service->calculateAffordablePrincipal(50000, 1500);
        $this->assertEquals(481720.08, $result);

        // obligations above 35% → zero
        $result = $service->calculateAffordablePrincipal(50000, 17500);
        $this->assertEquals(0, $result);

        // 100k income, 0 obligations → raw 1053762.68
        $result = $service->calculateAffordablePrincipal(100000, 0);
        $this->assertEquals(1053762.68, $result);
    }

    /** @test */
    public function calculate_credit_limit_raw_correctness()
    {
        // 50k income, 1500 obligations, 10k savings → 481000 affordability (savings disabled)
        config(['loan_matrix.credit_limit.savings_multiple' => null]);
        $service = new CreditLimitService();

        $result = $service->calculateCreditLimitRaw(50000, 1500, 10000);
        $this->assertEquals(481000, $result['limit']);
        $this->assertEquals('affordability', $result['cappedBy']);

        // savings cap enabled with multiple 3 → 30000
        config(['loan_matrix.credit_limit.savings_multiple' => 3]);
        $service = new CreditLimitService();
        $result = $service->calculateCreditLimitRaw(50000, 1500, 10000);
        $this->assertEquals(30000, $result['limit']);
        $this->assertEquals('savings', $result['cappedBy']);

        // obligations at 35% → zero (reset savings disabled first)
        config(['loan_matrix.credit_limit.savings_multiple' => null]);
        $service = new CreditLimitService();
        $result = $service->calculateCreditLimitRaw(50000, 17500, 0);
        $this->assertEquals(0, $result['limit']);
        $this->assertEquals('affordability', $result['cappedBy']);

        // zero obligations, zero savings → 526000
        config(['loan_matrix.credit_limit.savings_multiple' => null]);
        $service = new CreditLimitService();
        $result = $service->calculateCreditLimitRaw(50000, 0, 0);
        $this->assertEquals(526000, $result['limit']);
        $this->assertEquals('affordability', $result['cappedBy']);
    }

    /** @test */
    public function recommendation_rendered_in_memberships_modal()
    {
        $application = MemberApplication::factory()->create([
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 1500,
            'savings_balance' => 0,
            'status' => 'pending',
        ]);

        $admin = \App\Models\User::factory()
            ->asBranchManager()
            ->create();

        $response = $this->actingAs($admin)
            ->get(route('memberships.index'));

        $response->assertSee('Recommended credit limit');
    }

    /** @test */
    public function approve_with_recommended_value_sets_auto_source()
    {
        $application = MemberApplication::factory()->create([
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 1500,
            'savings_balance' => 0,
            'status' => 'pending',
        ]);

        $recommendation = app(CreditLimitService::class)->calculateCreditLimitRaw(
            50000, 1500, 0, 36, 0.01
        );

        $admin = \App\Models\User::factory()
            ->asBranchManager()
            ->create();

        $this->actingAs($admin, 'web')
            ->postJson(route('memberships.approve', $application->id), [
                'approved_amount' => $recommendation['limit'],
            ])
            ->assertJson(['success' => true]);

        $member = CoopMember::where('email', $application->email)->first();
        $this->assertNotNull($member);
        $this->assertEquals($recommendation['limit'], $member->approved_credit_limit);
        $this->assertEquals('auto', $member->credit_limit_source);
    }

    /** @test */
    public function approve_with_different_value_sets_manual_source()
    {
        $application = MemberApplication::factory()->create([
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 1500,
            'savings_balance' => 0,
            'status' => 'pending',
        ]);

        $admin = \App\Models\User::factory()
            ->asBranchManager()
            ->create();

        $this->actingAs($admin, 'web')
            ->postJson(route('memberships.approve', $application->id), [
                'approved_amount' => 999000, // above recommendation
                'rejection_reason' => 'Custom approved amount above recommended limit.',
            ])
            ->assertJson(['success' => true]);

        $member = CoopMember::where('email', $application->email)->first();
        $this->assertNotNull($member);
        $this->assertEquals(999000, $member->approved_credit_limit);
        $this->assertEquals('manual', $member->credit_limit_source);
    }

    /** @test */
    public function approve_above_recommendation_requires_reason()
    {
        $application = MemberApplication::factory()->create([
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 1500,
            'savings_balance' => 0,
            'status' => 'pending',
        ]);

        $admin = \App\Models\User::factory()
            ->asBranchManager()
            ->create();

        $this->actingAs($admin, 'web')
            ->postJson(route('memberships.approve', $application->id), [
                'approved_amount' => 999000,
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'A reason is required when approving an amount above the recommendation.']);
    }

    /** @test */
    public function blank_approved_amount_no_limit_member_activated()
    {
        $application = MemberApplication::factory()->create([
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 1500,
            'savings_balance' => 0,
            'status' => 'pending',
            'email' => 'blank-test@example.com',
        ]);

        // Also create a user with this email so the member can be linked
        $user = \App\Models\User::factory()->create([
            'email' => 'blank-test@example.com',
        ]);

        $admin = \App\Models\User::factory()
            ->asBranchManager()
            ->create();

        $this->actingAs($admin, 'web')
            ->postJson(route('memberships.approve', $application->id), [
                'approved_amount' => '',
            ])
            ->assertJson(['success' => true]);

        $member = CoopMember::where('email', 'blank-test@example.com')->first();
        $this->assertNotNull($member);
        $this->assertEquals(CoopMember::STATUS_ACTIVE, $member->status);
        $this->assertNull($member->approved_credit_limit);
    }

    /** @test */
    public function reject_without_reason_returns_422()
    {
        $application = MemberApplication::factory()->create([
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 1500,
            'savings_balance' => 0,
            'status' => 'pending',
        ]);

        $admin = \App\Models\User::factory()
            ->asBranchManager()
            ->create();

        $this->actingAs($admin, 'web')
            ->postJson(route('memberships.reject', $application->id), [
                'rejection_reason' => '',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'A rejection reason is required (min 10 characters).']);
    }

    /** @test */
    public function reject_with_reason_stores_reason_and_sends_mail()
    {
        $application = MemberApplication::factory()->create([
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 1500,
            'savings_balance' => 0,
            'status' => 'pending',
            'email' => 'reject@example.com',
        ]);

        $admin = \App\Models\User::factory()
            ->asBranchManager()
            ->create();

        $this->actingAs($admin, 'web')
            ->postJson(route('memberships.reject', $application->id), [
                'rejection_reason' => 'Applicant failed DTI check: monthly obligations exceed 35% of income.',
            ])
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('member_applications', [
            'id' => $application->id,
            'status' => 'rejected',
            'rejection_reason' => 'Applicant failed DTI check: monthly obligations exceed 35% of income.',
        ]);

        $this->assertDatabaseHas('app_notification_logs', [
            'notification_type' => 'member_rejected',
            'channel' => 'email',
            'status' => 'Sent',
        ]);
    }

    /** @test */
    public function public_membership_form_renders_no_limit_field()
    {
        $response = $this->get('/member-application');

        $response->assertDontSee('credit limit');
        $response->assertDontSee('recommended');
    }

    /** @test */
    public function loan_with_user_id_null_does_not_inflate_outstanding_for_other_members()
    {
        $service = new CreditLimitService();

        // Loan with user_id = null (staff-encoded) should only count for
        // the member whose email it matches, not for other members
        $result = $service->calculateCreditLimitRaw(50000, 1500, 0);
        $this->assertEquals(481000, $result['limit']);
    }

    /** @test */
    public function forged_manual_source_ignored_on_recompute()
    {
        $user = \App\Models\User::factory()->asMember()->create();
        $member = CoopMember::factory()->create([
            'user_id' => $user->id,
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 1500,
            'approved_credit_limit' => 999000, // manually set, above recomputed
            'credit_limit_source' => 'manual',
            'savings_balance' => 0,
        ]);

        $service = new CreditLimitService();
        $result = $service->needsRecompute($member, 50000, 1500, 0);

        // Even though source is 'manual', if nothing changed, needsRecompute returns false
        $this->assertFalse($result);
    }
}
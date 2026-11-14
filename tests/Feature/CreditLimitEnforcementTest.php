<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use App\Support\LoanStatus;
use App\Support\Permissions;
use App\Services\LoanEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5: Credit limit enforcement feature tests.
 *
 * Covers:
 * 1. Request above available credit is rejected/capped
 * 2. Missing limit (null) on a limit-required product is ineligible with reason shown
 * 3. Limit of 0 is treated as assessed, with no capacity
 * 4. Outstanding balance reduces available credit
 * 5. Products that don't need a limit are unaffected (secured, share_cap, special exempt)
 * 6. Admin approve() above available credit is blocked without override+reason and allowed with it, with audit log
 * 7. credit_limit_source is correct after memberUpdate
 */
class CreditLimitEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->asBranchManager()->create();
    }

    private function createMember(array $overrides = []): CoopMember
    {
        $user = User::factory()->create(['usertype' => 'member']);
        $member = CoopMember::factory()->create(array_merge([
            'user_id'                  => $user->id,
            'status'                   => CoopMember::STATUS_ACTIVE,
            'monthly_income'           => 50000,
            'existing_monthly_obligations' => 5000,
            'savings_balance'          => 10000,
            'approved_credit_limit'    => 100000,
            'is_migs'                  => false,
        ], $overrides));

        $user->forceFill(['coop_member_id' => $member->id])->save();
        return $member->fresh();
    }

    /** Portal-less member (no User account) — matched by email only. */
    private function createPortalLessMember(array $overrides = []): CoopMember
    {
        $member = CoopMember::factory()->create(array_merge([
            'user_id'                  => null,
            'email'                    => 'portal-less@example.com',
            'status'                   => CoopMember::STATUS_ACTIVE,
            'monthly_income'           => 30000,
            'existing_monthly_obligations' => 0,
            'savings_balance'          => 0,
            'approved_credit_limit'    => 5000,
            'is_migs'                  => false,
        ], $overrides));

        return $member->fresh();
    }

    /** Staff-encoded loan: user_id null, email matches the portal-less member. */
    private function createStaffEncodedLoanApp(CoopMember $member, array $overrides = []): LoanApplication
    {
        return LoanApplication::factory()->create(array_merge([
            'user_id'                        => null,
            'email'                          => $member->email,
            'loan_type'                      => 'regular',
            'amount'                         => 20000,
            'term_months'                    => 36,
            'monthly_income'                 => (float) $member->monthly_income,
            'existing_monthly_obligations'   => (float) $member->existing_monthly_obligations,
            'status'                         => 'pending',
        ], $overrides));
    }

    private function createLoanApp(CoopMember $member, array $overrides = []): LoanApplication
    {
        return LoanApplication::factory()->create(array_merge([
            'user_id'                        => $member->user_id,
            'loan_type'                      => 'regular',
            'amount'                         => 20000,
            'term_months'                    => 36,
            'monthly_income'                 => (float) $member->monthly_income,
            'existing_monthly_obligations'   => (float) $member->existing_monthly_obligations,
            'documents'                      => ['id.pdf', 'income.pdf'],
        ], $overrides));
    }

    /** @test */
    public function member_submission_above_available_credit_is_rejected()
    {
        // Manual monthly limit high enough that the MONTHLY gate passes and
        // the available-credit gate is what this test exercises.
        $member = $this->createMember([
            'approved_credit_limit'         => 10000,
            'approved_monthly_credit_limit' => 100000,
            'monthly_credit_limit_source'   => 'manual',
        ]);

        $user = $member->user;
        $this->actingAs($user)
            ->post(route('user.loans.store'), [
                'full_name'                  => $member->full_name,
                'email'                      => $member->email,
                'contact_number'             => '09123456789',
                'source_of_income'           => 'Salary',
                'monthly_income'             => 50000,
                'existing_monthly_obligations' => 5000,
                'purpose_of_loan'            => 'Personal',
                'loan_type'                  => 'regular',
                'amount'                     => 20000,
                'term_months'                => 36,
                'agree'                      => true,
            ])
            ->assertSessionHas('error', function ($msg) {
                return str_contains($msg, 'exceeds your available credit');
            });
    }

    /** @test */
    public function staff_encode_above_available_credit_is_rejected()
    {
        $member = $this->createMember(['approved_credit_limit' => 10000]);
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('applications.encode.store'), [
                'member_id'                    => $member->id,
                'loan_type'                    => 'regular',
                'amount'                       => 20000,
                'term_months'                  => 36,
                'purpose'                      => 'Personal',
                'monthly_income'               => 50000,
                'existing_monthly_obligations' => 5000,
                'source_of_income'             => 'Salary',
            ])
            ->assertSessionHas('error', function ($msg) {
                return str_contains($msg, 'exceeds available credit');
            });
    }

    /** @test */
    public function missing_credit_limit_marks_ineligible_with_reason()
    {
        $member = $this->createMember(['approved_credit_limit' => null]);

        $loanApp = $this->createLoanApp($member, ['loan_type' => 'regular', 'amount' => 20000]);
        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('Credit limit not assessed', implode(' ', $result['reasons']));
        $this->assertArrayHasKey('credit_limit', $result['details']);
        $this->assertArrayHasKey('outstanding', $result['details']);
        $this->assertArrayHasKey('available_credit', $result['details']);
    }

    /** @test */
    public function credit_limit_of_zero_is_assessed_with_no_capacity()
    {
        $member = $this->createMember(['approved_credit_limit' => 0]);

        $this->assertTrue($member->hasAssessedCreditLimit());
        $this->assertEquals(0.0, $member->creditLimit());
        $this->assertEquals(0.0, $member->availableCredit());

        $loanApp = $this->createLoanApp($member, ['loan_type' => 'regular', 'amount' => 20000]);
        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        $this->assertFalse($result['eligible']);
        // Now correctly caught by zero-limit early exit branch
        $this->assertStringContainsString('Credit limit assessed at zero', $result['rejection_reason']);
    }

    /** @test */
    public function outstanding_balance_reduces_available_credit()
    {
        // Manual monthly limit high enough that the MONTHLY gate passes and
        // the available-credit gate is what this test exercises.
        $member = $this->createMember([
            'approved_credit_limit'         => 100000,
            'approved_monthly_credit_limit' => 100000,
            'monthly_credit_limit_source'   => 'manual',
        ]);
        $loanApp = $this->createLoanApp($member, ['amount' => 80000, 'status' => 'approved']);
        $loanApp->status = LoanStatus::APPROVED;
        $loanApp->save();

        // outstandingPrincipal = 80000 * (remaining_balance / total_payable)
        // For a fresh loan with no payments, remaining_balance = total_payable
        // So outstandingPrincipal = amount = 80000
        $outstanding = CoopMember::outstandingPrincipal($member);
        $this->assertEquals(80000, $outstanding);

        $available = $member->availableCredit();
        $this->assertEquals(20000, $available); // 100000 - 80000

        // Submitting 30000 should be rejected (exceeds 20000 available)
        $user = $member->user;
        $this->actingAs($user)
            ->post(route('user.loans.store'), [
                'full_name'                  => $member->full_name,
                'email'                      => $member->email,
                'contact_number'             => '09123456789',
                'source_of_income'           => 'Salary',
                'monthly_income'             => 50000,
                'existing_monthly_obligations' => 5000,
                'purpose_of_loan'            => 'Personal',
                'loan_type'                  => 'regular',
                'amount'                     => 30000,
                'term_months'                => 36,
                'agree'                      => true,
            ])
            ->assertSessionHas('error');
    }

    /** @test */
    public function special_loan_type_exempt_from_available_credit_cap()
    {
        $member = $this->createMember(['approved_credit_limit' => 10000, 'savings_balance' => 100000]);

        // Special loan requires 100000 savings, which the member has
        // Special loan should NOT be capped by available credit
        $loanApp = $this->createLoanApp($member, ['loan_type' => 'special', 'amount' => 50000]);
        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        // Should not be ineligible due to credit limit
        $joined = implode(' ', $result['reasons']);
        $this->assertFalse(str_contains($joined, 'Credit limit not assessed'));
        $this->assertFalse(str_contains($joined, 'Credit limit assessed at zero'));
    }

    /** @test */
    public function admin_approve_above_available_credit_blocked_without_override_reason()
    {
        // Member with low available credit
        $member = $this->createMember(['approved_credit_limit' => 1000]);
        $loanApp = $this->createLoanApp($member, ['amount' => 5000, 'status' => 'pending']);

        // Create mirrored Application
        $application = Application::create([
            'loan_application_id' => $loanApp->id,
            'app_id'              => 'APP-0001',
            'applicant'           => $loanApp->full_name,
            'loan_type'           => $loanApp->loan_type,
            'amount'              => $loanApp->amount,
            'status'              => 'Pending',
            'recommended_amount'  => 5000,
            'risk_level'          => 'Low',
            'risk_score'          => 80,
        ]);

        // Loan officer has APPROVE_LOANS but NOT OVERRIDE_CREDIT_LIMIT
        $loanOfficer = User::factory()->create(['usertype' => 'loan_officer']);
        $this->assertTrue(Permissions::has($loanOfficer, Permissions::APPROVE_LOANS));
        $this->assertFalse(Permissions::has($loanOfficer, Permissions::OVERRIDE_CREDIT_LIMIT));

        // Loan officer tries to approve above available credit (5000 > 1000)
        // WITH a reason (to pass the "above recommended" reason check)
        $response = $this->actingAs($loanOfficer)
            ->postJson(route('applications.approve', $application->id), [
                'approved_amount' => 15000, // above available credit of 1000
                'reason'          => 'Trying to override without permission',
            ]);

        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
        $content = json_decode($response->getContent(), true);
        $this->assertStringContainsString('exceeds available credit', $content['message']);
    }

    /** @test */
    public function admin_approve_above_available_credit_allowed_with_override_and_audit_log()
    {
        $member = $this->createMember(['approved_credit_limit' => 10000]);
        $loanApp = $this->createLoanApp($member, ['amount' => 5000, 'status' => 'pending']);

        $application = Application::create([
            'loan_application_id' => $loanApp->id,
            'app_id'              => 'APP-0002',
            'applicant'           => $loanApp->full_name,
            'loan_type'           => $loanApp->loan_type,
            'amount'              => $loanApp->amount,
            'status'              => 'Pending',
            'recommended_amount'  => 5000,
            'risk_level'          => 'Low',
            'risk_score'          => 80,
        ]);

        // Branch Manager has OVERRIDE_CREDIT_LIMIT permission
        $branchManager = User::factory()->asBranchManager()->create();
        $this->assertTrue(Permissions::has($branchManager, Permissions::OVERRIDE_CREDIT_LIMIT));

        $response = $this->actingAs($branchManager)
            ->postJson(route('applications.approve', $application->id), [
                'approved_amount' => 15000,
                'reason'          => 'Exceptional payment history, co-signed by branch manager',
            ]);

        $response->assertJson(['success' => true]);

        // Check AuditLog entry was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Credit Limit Override',
            'details' => 'Application APP-0002: approved 15000.00 above available credit 10000.00. Override permitted. Reason: Exceptional payment history, co-signed by branch manager',
        ]);
    }

    /** @test */
    public function credit_limit_source_correct_after_member_update()
    {
        $member = $this->createMember([
            'approved_credit_limit' => 50000,
            'credit_limit_source'   => 'auto',
        ]);

        $admin = $this->createAdmin();

        // PUT with a different limit -> source should be 'manual'
        $this->actingAs($admin)
            ->put(route('admin.members.update', $member->id), [
                'approved_credit_limit' => 60000,
                'full_name'     => $member->full_name,
                'email'         => $member->email,
                'phone'         => $member->contact_number,
                'date_of_birth' => $member->date_of_birth,
                'address'       => $member->address,
                'occupation'    => $member->occupation,
                'monthly_income' => $member->monthly_income,
                'existing_monthly_obligations' => $member->existing_monthly_obligations,
                'savings_balance' => $member->savings_balance,
                'status'        => CoopMember::STATUS_ACTIVE,
            ]);

        $member->refresh();
        $this->assertEquals('manual', $member->credit_limit_source);
        $this->assertEquals(60000, $member->approved_credit_limit);

        // PUT with the SAME limit (explicitly sent) -> source should stay 'manual' (staff explicitly set it)
        // The auto-recompute only triggers when the field is OMITTED entirely.
        // To test auto path: omit approved_credit_limit, change savings, source auto recomputes.
        $member2 = $this->createMember([
            'approved_credit_limit' => 50000,
            'credit_limit_source'   => 'auto',
            'savings_balance'       => 10000,
        ]);

        // PUT WITHOUT approved_credit_limit field, but with changed savings_balance => 20000
        $this->actingAs($admin)
            ->put(route('admin.members.update', $member2->id), [
                'savings_balance' => 20000,
                'full_name'     => $member2->full_name,
                'email'         => $member2->email,
                'phone'         => $member2->contact_number,
                'date_of_birth' => $member2->date_of_birth,
                'address'       => $member2->address,
                'occupation'    => $member2->occupation,
                'monthly_income' => $member2->monthly_income,
                'existing_monthly_obligations' => $member2->existing_monthly_obligations,
                'status'        => CoopMember::STATUS_ACTIVE,
                // intentionally omit approved_credit_limit to test auto recompute
            ]);

        $member2->refresh();
        // Source should stay 'auto' and limit should be recomputed
        $this->assertEquals('auto', $member2->credit_limit_source);
        $this->assertGreaterThan(0, $member2->approved_credit_limit);
    }

    /** @test */
    public function missing_credit_limit_forces_high_risk()
    {
        $member = $this->createMember(['approved_credit_limit' => null]);
        $loanApp = $this->createLoanApp($member, ['loan_type' => 'regular', 'amount' => 20000]);
        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        $this->assertFalse($result['eligible']);
        $this->assertEquals(0, $result['recommended_amount']);
        $this->assertEquals('High', $result['risk_level']);
        $this->assertStringContainsString('Credit limit not assessed', implode(' ', $result['reasons']));
    }

    /** @test */
    public function zero_credit_limit_forces_high_risk()
    {
        $member = $this->createMember(['approved_credit_limit' => 0]);
        $loanApp = $this->createLoanApp($member, ['loan_type' => 'regular', 'amount' => 20000]);
        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        $this->assertEquals('High', $result['risk_level']);
        $this->assertStringContainsString('Credit limit assessed at zero', implode(' ', $result['reasons']));
    }

    /** @test */
    public function dti_breach_with_no_history_forces_high_risk()
    {
        // Member with income but high obligations → DTI breach on requested amount
        $member = $this->createMember([
            'monthly_income'      => 30000,
            'existing_monthly_obligations' => 20000, // leaves only 500 budget for DTI
            'approved_credit_limit' => 500000,
            'savings_balance'    => 0,
        ]);
        // Request large amount to force DTI breach (EMI will exceed remaining budget)
        $loanApp = $this->createLoanApp($member, [
            'loan_type' => 'regular',
            'amount'    => 50000,
            'term_months' => 12,
        ]);
        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        // No history + DTI breach should give High risk
        $this->assertEquals('High', $result['risk_level']);
    }

    /** @test */
    public function three_late_payments_gives_low_payment_score()
    {
        // Create member with existing loan and 3 late payments
        $user = User::factory()->create(['usertype' => 'member']);
        $member = CoopMember::factory()->create([
            'user_id'                  => $user->id,
            'status'                   => CoopMember::STATUS_ACTIVE,
            'monthly_income'           => 50000,
            'existing_monthly_obligations' => 5000,
            'savings_balance'          => 10000,
            'approved_credit_limit'    => 100000,
            'is_migs'                  => false,
        ]);
        $user->forceFill(['coop_member_id' => $member->id])->save();

        // Create an earlier approved loan with payments
        $earlierApp = LoanApplication::factory()->create([
            'user_id'        => $user->id,
            'loan_type'      => 'regular',
            'amount'         => 20000,
            'term_months'    => 12,
            'status'         => 'approved',
        ]);
        // Use created_at as disbursement date fallback
        $disbursed = $earlierApp->created_at;

        // Add 3 late payments (after due dates)
        for ($i = 0; $i < 3; $i++) {
            $dueDate = Carbon::parse($disbursed)->addMonths($i + 1);
            Payment::create([
                'loan_application_id' => $earlierApp->id,
                'amount'              => 2000,
                'payment_date'        => $dueDate->copy()->addDays(5), // 5 days late
                'payment_type'        => 'regular',
            ]);
        }

        // Current application
        $loanApp = $this->createLoanApp($member, ['loan_type' => 'regular', 'amount' => 20000]);
        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        // 3 late payments → payment score = max(0, 35 - 3*10) = 5
        // With no docs, no savings, and good DTI: dtiScore=45, payment=5, doc=0, savings=0 → total=50 → Medium
        // We just verify the payment score is computed correctly
        $this->assertEquals(5, $result['details']['payment_score']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Bug 2: Approve gate skips staff-encoded loans for portal-less members.
    // $loanApp->user?->coopMember fails when user_id is null, so both credit
    // gates are silently skipped. Fix: use CoopMember::forLoanApplication().
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function approve_staff_encoded_loan_for_portal_less_member_blocked_above_available_credit()
    {
        // Portal-less member with low credit limit
        $member = $this->createPortalLessMember([
            'approved_credit_limit' => 5000, // available = 5000
        ]);

        // Staff-encoded loan: user_id null, email matches member
        $loanApp = $this->createStaffEncodedLoanApp($member, ['amount' => 20000]);

        $application = Application::create([
            'loan_application_id' => $loanApp->id,
            'app_id'              => 'APP-0099',
            'applicant'           => $loanApp->full_name,
            'loan_type'           => $loanApp->loan_type,
            'amount'              => $loanApp->amount,
            'status'              => 'Pending',
            'recommended_amount'  => 20000,
            'risk_level'          => 'Low',
            'risk_score'          => 80,
        ]);

        // Loan officer has APPROVE_LOANS but NOT OVERRIDE_CREDIT_LIMIT
        $loanOfficer = User::factory()->create(['usertype' => 'loan_officer']);
        $this->assertTrue(Permissions::has($loanOfficer, Permissions::APPROVE_LOANS));
        $this->assertFalse(Permissions::has($loanOfficer, Permissions::OVERRIDE_CREDIT_LIMIT));

        // Approve 20000 > available 5000 → blocked
        $response = $this->actingAs($loanOfficer)
            ->postJson(route('applications.approve', $application->id), [
                'approved_amount' => 20000,
                'reason'          => 'No override permission',
            ]);

        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
        $content = json_decode($response->getContent(), true);
        $this->assertStringContainsString('exceeds available credit', $content['message']);
    }

    /** @test */
    public function approve_staff_encoded_loan_for_portal_less_member_allowed_with_override_and_audit()
    {
        $member = $this->createPortalLessMember([
            'approved_credit_limit' => 5000,
        ]);

        $loanApp = $this->createStaffEncodedLoanApp($member, ['amount' => 20000]);

        $application = Application::create([
            'loan_application_id' => $loanApp->id,
            'app_id'              => 'APP-0100',
            'applicant'           => $loanApp->full_name,
            'loan_type'           => $loanApp->loan_type,
            'amount'              => $loanApp->amount,
            'status'              => 'Pending',
            'recommended_amount'  => 20000,
            'risk_level'          => 'Low',
            'risk_score'          => 80,
        ]);

        // Branch Manager has OVERRIDE_CREDIT_LIMIT
        $branchManager = User::factory()->asBranchManager()->create();
        $this->assertTrue(Permissions::has($branchManager, Permissions::OVERRIDE_CREDIT_LIMIT));

        $response = $this->actingAs($branchManager)
            ->postJson(route('applications.approve', $application->id), [
                'approved_amount' => 20000,
                'reason'          => 'Exceptional payment history',
            ]);

        $response->assertJson(['success' => true]);

        // AuditLog 'Credit Limit Override' entry written
        $this->assertDatabaseHas('audit_logs', [
            'action'   => 'Credit Limit Override',
            'details'  => 'Application APP-0100: approved 20000.00 above available credit 5000.00. Override permitted. Reason: Exceptional payment history',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Bug 3: LoanEvaluationService reads the wrong member's outstanding
    // credit. Line ~151 did CoopMember::where('user_id', $loanApp->user_id)
    // ->first(); with user_id null that's user_id IS NULL → first portal-less
    // member, not the member evaluate() already resolved.
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function evaluation_uses_correct_portal_less_members_outstanding_credit()
    {
        $memberA = $this->createPortalLessMember([
            'email'                 => 'portal-a@example.com',
            'approved_credit_limit' => 100000,
        ]);
        $memberB = $this->createPortalLessMember([
            'email'                 => 'portal-b@example.com',
            'approved_credit_limit' => 100000,
        ]);

        // Different outstanding loans: A owes 80000, B owes 10000.
        $this->createStaffEncodedLoanApp($memberA, ['amount' => 80000, 'status' => 'approved']);
        $this->createStaffEncodedLoanApp($memberB, ['amount' => 10000, 'status' => 'approved']);

        // New pending loan for member B (user_id null, email matches B).
        $loanApp = $this->createStaffEncodedLoanApp($memberB, [
            'amount'      => 100000,
            'term_months' => 12,
            'status'      => 'pending',
        ]);

        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        // Must use B's outstanding principal (10000), not A's (80000).
        $this->assertEquals(10000, $result['details']['outstanding']);
        // Consequence: available credit 90000 — NOT 10000 (which A's balance
        // would cause). The monthly allowance (10500) now caps below that,
        // so the recommendation lands exactly on Monthly Available.
        $this->assertEquals(10500, $result['recommended_amount']);
        $this->assertTrue(collect($result['evaluation_reasons'])->contains(
            fn ($r) => str_contains($r, 'this month\'s available credit')
        ));
    }

    /** @test */
    public function approve_staff_encoded_loan_for_portal_less_member_blocked_above_monthly_capacity()
    {
        // Portal-less member with income 30000, obligations 0 → monthly capacity 10500
        // Request 400000 → exceeds the monthly allowance gate
        $member = $this->createPortalLessMember([
            'monthly_income'           => 30000,
            'existing_monthly_obligations' => 0,
            'approved_credit_limit'    => 1000000, // large credit limit
        ]);

        $loanApp = $this->createStaffEncodedLoanApp($member, ['amount' => 400000]);

        $application = Application::create([
            'loan_application_id' => $loanApp->id,
            'app_id'              => 'APP-0101',
            'applicant'           => $loanApp->full_name,
            'loan_type'           => $loanApp->loan_type,
            'amount'              => $loanApp->amount,
            'status'              => 'Pending',
            'recommended_amount'  => 400000,
            'risk_level'          => 'Low',
            'risk_score'          => 80,
        ]);

        $loanOfficer = User::factory()->create(['usertype' => 'loan_officer']);
        $this->assertFalse(Permissions::has($loanOfficer, Permissions::OVERRIDE_CREDIT_LIMIT));

        // 400000 > monthly available 10500 → blocked by monthly gate
        $response = $this->actingAs($loanOfficer)
            ->postJson(route('applications.approve', $application->id), [
                'approved_amount' => 400000,
                'reason'          => 'No override permission',
            ]);

        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
        $content = json_decode($response->getContent(), true);
        $this->assertStringContainsString('exceeds the monthly credit limit', $content['message']);
    }
}
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
        $member = $this->createMember(['approved_credit_limit' => 10000]);

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
        $member = $this->createMember(['approved_credit_limit' => 100000]);
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
}
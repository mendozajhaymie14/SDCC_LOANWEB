<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use App\Services\AmortizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 0A — money must be computed on the APPROVED amount, not the
 * requested amount, once a loan is granted.
 */
class ApprovedPrincipalTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['usertype' => 'branch_manager', 'email' => 'admin@example.com']);
    }

    /**
     * Request ₱5,000, approve ₱4,500 through the real route.
     *
     * @return array{0: Application, 1: LoanApplication}
     */
    private function approveForLess(): array
    {
        $admin  = $this->makeAdmin();
        $member = User::factory()->create(['usertype' => 'member', 'email' => 'mem@example.com']);
        CoopMember::factory()->create([
            'user_id'       => $member->id,
            'share_capital' => 30000,
            'monthly_income' => 20000,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id'     => $member->id,
            'full_name'   => 'John Smith',
            'email'       => $member->email,
            'loan_type'   => 'regular',
            'amount'      => 5000,
            'status'      => 'pending',
            'term_months' => 12,
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApp->id,
            'app_id'              => 'APP-' . str_pad((string) $loanApp->id, 4, '0', STR_PAD_LEFT),
            'applicant'           => 'John Smith',
            'status'              => 'pending',
            'recommended_amount'  => 2500.00,
            'risk_level'          => 'Medium',
            'risk_score'          => 60,
        ]);

        $response = $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('applications.approve', $application->id), [
                'approved_amount' => 4500,
            ]);

        $response->assertOk();

        return [$application, $loanApp];
    }

    /** @test */
    public function task_0a_approve_stores_approved_amount_on_the_loan_row()
    {
        [$application, $loanApp] = $this->approveForLess();

        $this->assertDatabaseHas('loan_applications', [
            'id'              => $loanApp->id,
            'approved_amount' => 4500,
        ]);
        $this->assertDatabaseHas('applications', [
            'id'              => $application->id,
            'approved_amount' => 4500,
        ]);
    }

    /** @test */
    public function task_0a_granted_loan_money_uses_the_approved_principal()
    {
        [$application, $loanApp] = $this->approveForLess();

        $emi     = app(AmortizationService::class)->emi(4500, 12);
        $payable = app(AmortizationService::class)->summary(4500, 12)['total_paid'];

        $loanApp->refresh();
        $application->refresh();

        $this->assertEqualsWithDelta($emi, $loanApp->monthly_payment, 0.01, 'EMI must use the approved principal');
        $this->assertEqualsWithDelta($payable, $loanApp->totalPayable(), 0.01, 'totalPayable must use the approved principal');
        $this->assertEqualsWithDelta($payable, $loanApp->remaining_balance, 0.01, 'loan row remaining_balance must be the approved total payable');
        $this->assertEqualsWithDelta($payable, $application->remaining_balance, 0.01, 'applications mirror must agree');
        $this->assertEqualsWithDelta($emi, $application->loanApplication->monthly_payment, 0.01);

        // Schedule is built from the approved principal too.
        $schedule = $loanApp->amortizationSchedule();
        $this->assertCount(12, $schedule);
        $this->assertEqualsWithDelta($emi, $schedule[0]['payment'], 0.01);
    }

    /** @test */
    public function task_0a_outstanding_principal_uses_approved_principal()
    {
        [$application, $loanApp] = $this->approveForLess();

        $loanApp->refresh();
        $this->assertEqualsWithDelta(4500, $loanApp->outstandingPrincipal(), 0.01);
    }

    /** @test */
    public function task_0a_member_monthly_used_counts_approved_principal()
    {
        [$application, $loanApp] = $this->approveForLess();

        $member = CoopMember::where('user_id', $loanApp->user_id)->first();
        $this->assertEqualsWithDelta(4500, $member->monthlyUsed(), 0.01, 'monthlyUsed must sum the approved principal, not the requested amount');
    }

    /** @test */
    public function task_0a_member_portal_borrowed_uses_approved_principal()
    {
        [$application, $loanApp] = $this->approveForLess();

        $member = User::where('usertype', 'member')->first();

        $response = $this->actingAs($member)->get(route('user.dashboard'));
        $response->assertOk();

        $summary = $response->original->getData()['summary'] ?? null;
        $this->assertNotNull($summary, 'dashboard must pass $summary to the view');
        $this->assertEqualsWithDelta(4500, (float) $summary['borrowed'], 0.01, 'portal "borrowed" must use the approved principal');
    }

    /** @test */
    public function task_0a_pending_loan_keeps_requested_amount()
    {
        $member = User::factory()->create(['usertype' => 'member', 'email' => 'pend@example.com']);
        $loanApp = LoanApplication::factory()->create([
            'user_id'   => $member->id,
            'amount'    => 5000,
            'status'    => 'pending',
            'term_months' => 12,
        ]);

        $this->assertEqualsWithDelta(5000, $loanApp->principal, 0.001);
        // EMI of the requested amount while pending.
        $emi = app(AmortizationService::class)->emi(5000, 12);
        $this->assertEqualsWithDelta($emi, $loanApp->monthly_payment, 0.01);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Task 0B — next_due_date must be anchored on the loan's stable first due
 * date (disbursement/reviewed_at + 1 month), not on the CURRENT next_due_date,
 * which made every payment shift the anchor forward and compound the drift.
 */
class DueDateAnchorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * Approve a ₱10,000 / 12-month loan through the real route.
     *
     * @return array{0: Application, 1: LoanApplication, 2: float} [application, loan, emi]
     */
    private function approveLoan(): array
    {
        $admin  = User::factory()->create(['usertype' => 'branch_manager', 'email' => 'admin@example.com']);
        $member = User::factory()->create(['usertype' => 'member', 'email' => 'mem@example.com']);
        CoopMember::factory()->create([
            'user_id'                      => $member->id,
            'share_capital'                => 30000,
            'monthly_income'               => 20000,
            'approved_monthly_credit_limit' => 20000,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id'     => $member->id,
            'amount'      => 10000,
            'term_months' => 12,
            'status'      => 'pending',
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApp->id,
            'app_id'              => 'APP-' . str_pad((string) $loanApp->id, 4, '0', STR_PAD_LEFT),
            'applicant'           => 'John Smith',
            'status'              => 'pending',
            'recommended_amount'  => 10000.00,
            'risk_level'          => 'Medium',
            'risk_score'          => 60,
        ]);

        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('applications.approve', $application->id))
            ->assertOk();

        $emi = $loanApp->fresh()->monthly_payment;

        return [$application, $loanApp, $emi];
    }

    private function recordPayment(Application $application, float $amount, string $ref, Carbon $date): void
    {
        $officer = User::factory()->create(['usertype' => 'loan_officer']);

$response = $this->actingAs($officer)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/admin/repayments', [
                'application_id' => $application->id,
                'amount'         => $amount,
                'reference_no'   => $ref,
                'payment_date'   => $date->toDateString(),
                'payment_method' => 'bank_transfer',
                'notes'          => 'Test payment',
                'proof'          => UploadedFile::fake()->image('proof.jpg'),
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    /** @test */
    public function task_0b_next_due_advances_one_month_per_installment()
    {
        [$application, $loanApp, $emi] = $this->approveLoan();

        $firstDue = Carbon::parse($loanApp->fresh()->next_due_date);

        foreach ([1, 2, 3] as $i) {
            $this->recordPayment($application, $emi, "REP-00$i", Carbon::today()->subDays($i));

            $loanApp->refresh();
            $application->refresh();

            $expected = $firstDue->copy()->addMonths($i)->format('Y-m-d');
            $this->assertEquals(
                $expected,
                $loanApp->fresh()->next_due_date?->format('Y-m-d'),
                "After {$i} installment(s) next_due_date must be first due + {$i} month(s), not drift forward"
            );
            $this->assertEquals(
                $expected,
                $application->fresh()->next_due_date?->format('Y-m-d'),
                "applications mirror must equal loan_applications after payment {$i}"
            );
        }
    }

    /** @test */
    public function task_0b_partial_payment_keeps_first_due_date()
    {
        [$application, $loanApp, $emi] = $this->approveLoan();

        $firstDue = Carbon::parse($loanApp->fresh()->next_due_date);

        // A payment smaller than one installment covers nothing.
        $this->recordPayment($application, $emi / 2, 'REP-PART', Carbon::today()->subDays(1));

        $this->assertEquals(
            $firstDue->format('Y-m-d'),
            $loanApp->fresh()->next_due_date?->format('Y-m-d'),
            'A partial installment must not move the due date'
        );
    }

    /** @test */
    public function task_0b_fully_paid_loan_has_no_due_date_and_is_paid()
    {
        [$application, $loanApp, $emi] = $this->approveLoan();

        for ($i = 1; $i <= 12; $i++) {
            $this->recordPayment($application, $emi, 'REP-FUL-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), Carbon::today()->subDays($i));
        }

        $loanApp->refresh();
        $application->refresh();

        $this->assertEquals('paid', $loanApp->status);
        $this->assertEquals('Paid', $application->status);
        $this->assertNull($loanApp->next_due_date);
        $this->assertNull($application->next_due_date);
    }

    /** @test */
    public function task_0b_reversing_a_payment_moves_the_due_date_back()
    {
        [$application, $loanApp, $emi] = $this->approveLoan();

        $firstDue = Carbon::parse($loanApp->fresh()->next_due_date);

        $this->recordPayment($application, $emi, 'REV-A', Carbon::today()->subDays(2));
        $this->recordPayment($application, $emi, 'REV-B', Carbon::today()->subDays(1));

        $this->assertEquals(
            $firstDue->copy()->addMonths(2)->format('Y-m-d'),
            $loanApp->fresh()->next_due_date?->format('Y-m-d'),
            'sanity: two installments covered → first due + 2 months'
        );

        $paymentB = Payment::where('reference_no', 'REV-B')->first();
        $manager  = User::factory()->asBranchManager()->create();

        $this->actingAs($manager)
            ->postJson("/repayments/{$paymentB->id}/reverse", [
                'reason' => 'Payment was encoded twice by mistake',
            ])
            ->assertOk();

        $loanApp->refresh();
        $application->refresh();

        $this->assertEquals(
            $firstDue->copy()->addMonths(1)->format('Y-m-d'),
            $loanApp->next_due_date?->format('Y-m-d'),
            'After reversing the second installment the due date must go back to first due + 1 month'
        );
        $this->assertEquals(
            $loanApp->next_due_date?->format('Y-m-d'),
            $application->next_due_date?->format('Y-m-d'),
            'mirrors must stay equal after reversal'
        );
    }

    /** @test */
    public function task_0b_manager_amount_edit_recomputes_the_due_date()
    {
        [$application, $loanApp, $emi] = $this->approveLoan();

        $firstDue = Carbon::parse($loanApp->fresh()->next_due_date);

        $this->recordPayment($application, $emi, 'EDI-A', Carbon::today()->subDays(2));
        $this->recordPayment($application, $emi, 'EDI-B', Carbon::today()->subDays(1));

        $paymentA = Payment::where('reference_no', 'EDI-A')->first();
        $manager  = User::factory()->asBranchManager()->create();

        // Double the first installment: three installments are now covered.
        $this->actingAs($manager)
            ->withHeaders(['Accept' => 'application/json'])
            ->put("/repayments/{$paymentA->id}", [
                'amount'         => $emi * 2,
                'payment_method' => 'bank_transfer',
                'reference_no'   => 'EDI-A',
                'payment_date'   => Carbon::today()->subDays(2)->toDateString(),
                'notes'          => 'Corrected amount',
            ])
            ->assertOk();

        // Before the edit two single installments were covered; after doubling
        // the first payment the loan has paid 3 × emi → 3 installments.
        $this->assertEquals(
            $firstDue->copy()->addMonths(3)->format('Y-m-d'),
            $loanApp->fresh()->next_due_date?->format('Y-m-d'),
            'Editing a payment amount must recompute next_due_date from the stable first due date'
        );
        $this->assertEquals(
            $loanApp->fresh()->next_due_date?->format('Y-m-d'),
            $application->fresh()->next_due_date?->format('Y-m-d'),
            'mirrors must stay equal after an amount edit'
        );
    }
}

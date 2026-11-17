<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationLoanRefTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
    }

    private function manager(): User
    {
        return User::factory()->create([
            'usertype' => 'branch_manager',
            'email'    => 'manager' . uniqid() . '@example.com',
        ]);
    }

    private function member(?string $email = null): User
    {
        return User::factory()->create([
            'usertype' => 'member',
            'email'    => $email ?? 'borrower' . uniqid() . '@example.com',
        ]);
    }

    private function coopMember(User $member): CoopMember
    {
        return CoopMember::factory()->create([
            'user_id'       => $member->id,
            'email'         => $member->email,
            'share_capital' => 30000,
            'is_migs'       => false,
            'monthly_income' => 20000,
        ]);
    }

    /**
     * Test loan ref resolution when loan_applications.id, applications.id,
     * and app_id are deliberately different numbers.
     *
     * Scenario: loan id 10, mirror id 8, app_id APP-0009.
     * Approve + reject through real routes.
     * Assert outbox shows same app_id as Applications page / application modal.
     */
    public function test_outbox_loan_ref_matches_applications_page_when_ids_differ(): void
    {
        $manager = $this->manager();
        $member  = $this->member();
        $coop    = $this->coopMember($member);

        // Loan id 10, mirror id 8, app_id APP-0009
        $loan = LoanApplication::factory()->create([
            'id'                 => 10,
            'user_id'            => $member->id,
            'email'              => $member->email,
            'full_name'          => 'Test Borrower',
            'loan_type'          => 'regular',
            'amount'             => 5000,
            'status'             => 'pending',
            'term_months'        => 12,
            'monthly_income'     => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'      => 30000,
            'remaining_balance'  => 0,
        ]);

        $app = Application::factory()->create([
            'id'                 => 8,
            'loan_application_id'=> $loan->id,
            'app_id'             => 'APP-0009',
            'applicant'          => $loan->full_name,
            'status'             => 'Pending',
            'recommended_amount' => 2500.00,
            'risk_level'         => 'Medium',
            'risk_score'         => 60,
            'loan_type'          => 'regular',
            'amount'             => 5000,
            'term_months'        => 12,
        ]);

        // Approve through real route
        $this->actingAs($manager)
            ->post(route('applications.approve', $app->id), [
                'approved_amount' => 10000,
                'reason'          => 'Good history',
            ]);

        // Applications page shows APP-0009 for this loan
        $appsPage = $this->actingAs($manager)->get(route('applications.index'))->getContent();
        $this->assertStringContainsString('APP-0009', $appsPage);

        // Application modal data shows APP-0009 (route takes numeric id)
        $modalData = $this->actingAs($manager)->get(route('admin.applications.data', $app->id))->getContent();
        $this->assertStringContainsString('APP-0009', $modalData);

        // Outbox approval row shows the same APP-0009
        $outbox = $this->actingAs($manager)->get(route('admin.notifications-log'))->getContent();
        $this->assertStringContainsString('APP-0009', $outbox);

        // Outbox link target (numericId) points to the mirror's id (8) so the data route
        // resolves the same mirror; verify the onclick carries numericId=8.
        $this->assertStringContainsString('openNotifLoan(\'APP-0009\', 8)', $outbox);

        // Repayments page shows the same APP-0009 in the Loan column
        $repay = $this->actingAs($manager)->get('/repayments')->getContent();
        $this->assertStringContainsString('APP-0009', $repay);

        // Assert the log row stores loan_application_id = 10 (the loan's id, not the mirror's id 8)
        $this->assertDatabaseHas('app_notification_logs', [
            'notification_type'   => 'approved',
            'loan_application_id' => 10,
        ]);
    }

    /**
     * Also test rejection flow with different ids.
     */
    public function test_outbox_rejected_loan_ref_matches_applications_page_when_ids_differ(): void
    {
        $manager = $this->manager();
        $member  = $this->member();
        $coop    = $this->coopMember($member);

        // Loan id 20, mirror id 18, app_id APP-0019
        $loan = LoanApplication::factory()->create([
            'id'                 => 20,
            'user_id'            => $member->id,
            'email'              => $member->email,
            'full_name'          => 'Test Borrower Two',
            'loan_type'          => 'regular',
            'amount'             => 8000,
            'status'             => 'pending',
            'term_months'        => 12,
            'monthly_income'     => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'      => 30000,
            'remaining_balance'  => 0,
        ]);

        $app = Application::factory()->create([
            'id'                 => 18,
            'loan_application_id'=> $loan->id,
            'app_id'             => 'APP-0019',
            'applicant'          => $loan->full_name,
            'status'             => 'Pending',
            'recommended_amount' => 4000.00,
            'risk_level'         => 'Medium',
            'risk_score'         => 60,
            'loan_type'          => 'regular',
            'amount'             => 8000,
            'term_months'        => 12,
        ]);

        // Reject through real route
        $this->actingAs($manager)
            ->post(route('applications.reject', $app->id), [
                'reason' => 'Insufficient income',
            ]);

        // Applications page shows APP-0019 (as rejected)
        $appsPage = $this->actingAs($manager)->get(route('applications.index'))->getContent();
        $this->assertStringContainsString('APP-0019', $appsPage);

        // Application modal data shows APP-0019
        $modalData = $this->actingAs($manager)->get(route('admin.applications.data', $app->id))->getContent();
        $this->assertStringContainsString('APP-0019', $modalData);

        // Outbox rejection row shows the same APP-0019
        $outbox = $this->actingAs($manager)->get(route('admin.notifications-log'))->getContent();
        $this->assertStringContainsString('APP-0019', $outbox);

        // Assert the log row stores loan_application_id = 20
        $this->assertDatabaseHas('app_notification_logs', [
            'notification_type'   => 'rejected',
            'loan_application_id' => 20,
        ]);
    }
}
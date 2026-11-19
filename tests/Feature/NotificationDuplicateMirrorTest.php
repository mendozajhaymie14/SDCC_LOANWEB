<?php

namespace Tests\Feature;

use App\Models\AppNotificationLog;
use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationDuplicateMirrorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');

        // These tests simulate a legacy database that predates the UNIQUE
        // index on applications.loan_application_id: they deliberately create
        // two mirror rows for one loan to prove ref resolution picks the
        // newest. Drop the index so the fixtures can be inserted.
        \Illuminate\Support\Facades\Schema::table('applications', function ($t) {
            $t->dropUnique(['loan_application_id']);
        });
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
     * Test duplicate mirror scenario:
     * Two Application rows with same loan_application_id — the HasOne
     * relation picks the first (lowest id) but the Applications page
     * shows the latest. The outbox should show the same app_id as the
     * Applications page.
     */
    public function test_outbox_loan_ref_with_duplicate_mirrors(): void
    {
        $manager = $this->manager();
        $member  = $this->member();
        $coop    = $this->coopMember($member);

        // Loan id 10
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

        // Mirror A (id 8, older): loan_application_id = 10, app_id = APP-0009
        $mirrorA = Application::factory()->create([
            'id'                 => 8,
            'loan_application_id'=> 10,
            'app_id'             => 'APP-0009',
            'applicant'          => 'Test Borrower',
            'status'             => 'Pending',
            'recommended_amount' => 2500.00,
        ]);

        // Mirror B (id 9, newer, same loan_application_id): app_id = APP-0010
        $mirrorB = Application::factory()->create([
            'id'                 => 9,
            'loan_application_id'=> 10,
            'app_id'             => 'APP-0010',
            'applicant'          => 'Test Borrower',
            'status'             => 'Pending',
            'recommended_amount' => 2500.00,
        ]);

        // Approve through mirror B's route (app_id = 9)
        $this->actingAs($manager)
            ->post(route('applications.approve', $mirrorB->id), [
                'approved_amount' => 10000,
                'reason'          => 'Good history',
            ]);

        // Applications page should show the latest mirror
        // (The admin application index shows all mirrors; we'll check the modal)
        $appsPage = $this->actingAs($manager)->get(route('applications.index'))->getContent();
        $this->assertStringContainsString('APP-0010', $appsPage);

        // Application modal for mirror B
        $modalData = $this->actingAs($manager)->get(route('admin.applications.data', $mirrorB->id))->getContent();
        $this->assertStringContainsString('APP-0010', $modalData);

        // Outbox approval row — THIS IS THE CRITICAL ASSERTION
        // Check the exact loan link HTML to see which app_id the outbox renders.
        // The loan link format: openNotifLoan('APP-XXXX', N, event); return false;"
        $outbox = $this->actingAs($manager)->get(route('admin.notifications-log'))->getContent();

        // The outbox loan link should show APP-0010 (mirror B), not APP-0009 (mirror A).
        // HasOne without orderBy returns the first mirror by id (APP-0009), which is wrong.
        $this->assertStringContainsString(
            "openNotifLoan('APP-0010', 9, event)",
            $outbox,
            'Outbox loan link should point to APP-0010 (mirror B), not APP-0009 (mirror A — wrong!)'
        );

        // Sanity: APP-0009 should NOT appear as the loan link in the outbox
        $this->assertStringNotContainsString(
            "openNotifLoan('APP-0009', 8, event)",
            $outbox,
            'Outbox should NOT link to APP-0009 (mirror A — that is the wrong mirror)'
        );
    }

    /**
     * Payment::appId() must resolve to the NEWEST mirror's app_id, matching the
     * outbox/Applications page/modal — not the oldest mirror (belongsTo returns
     * first match by id, which is the wrong one with duplicate mirrors).
     */
    public function test_payment_app_id_uses_newest_mirror(): void
    {
        $manager = $this->manager();
        $member  = $this->member();
        $coop    = $this->coopMember($member);

        $loan = LoanApplication::factory()->create([
            'id'                 => 10,
            'user_id'            => $member->id,
            'email'              => $member->email,
            'full_name'          => 'Test Borrower',
            'loan_type'          => 'regular',
            'amount'             => 5000,
            'status'             => 'approved',
            'term_months'        => 12,
            'monthly_income'     => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'      => 30000,
            'remaining_balance'  => 4000,
            'next_due_date'      => now()->addDays(2),
        ]);

        // Mirror A (id 8, older): APP-0009
        Application::factory()->create([
            'id'                 => 8,
            'loan_application_id'=> 10,
            'app_id'             => 'APP-0009',
            'applicant'          => 'Test Borrower',
            'status'             => 'Pending',
            'recommended_amount' => 2500.00,
        ]);

        // Mirror B (id 9, newer, same loan): APP-0010
        Application::factory()->create([
            'id'                 => 9,
            'loan_application_id'=> 10,
            'app_id'             => 'APP-0010',
            'applicant'          => 'Test Borrower',
            'status'             => 'Pending',
            'recommended_amount' => 2500.00,
        ]);

        $payment = Payment::factory()->create([
            'loan_application_id' => $loan->id,
            'amount_paid'         => 1000,
            'remaining_balance_after' => 3000,
            'reference_no'        => 'REP-DUPLICATE-1',
            'payment_date'        => now(),
            'payment_method'      => 'Cash',
        ]);

        $this->assertSame('APP-0010', $payment->appId(),
            'Payment::appId() must return the NEWEST mirror (APP-0010), not the oldest (APP-0009)');

        $repay = $this->actingAs($manager)->get('/repayments')->getContent();
        $this->assertStringContainsString('APP-0010', $repay,
            'Repayments page Loan column must show the newest mirror APP-0010');
        $this->assertStringNotContainsString('APP-0009', $repay,
            'Repayments page must NOT show the stale oldest mirror APP-0009');
    }

    /**
     * Test that soft-deleted mirrors also cause incorrect resolution.
     */
    public function test_outbox_loan_ref_with_softdeleted_mirror(): void
    {
        $manager = $this->manager();
        $member  = $this->member();
        $coop    = $this->coopMember($member);

        // Loan id 20
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

        // Mirror A (id 18, soft-deleted): loan_application_id = 20, app_id = APP-0019
        $mirrorA = Application::factory()->create([
            'id'                 => 18,
            'loan_application_id'=> 20,
            'app_id'             => 'APP-0019',
            'applicant'          => 'Test Borrower Two',
            'status'             => 'Pending',
            'recommended_amount' => 4000.00,
        ]);

        // Soft-delete mirror A
        $mirrorA->delete(); // or use forceDelete if you want

        // Mirror B (id 19, active): loan_application_id = 20, app_id = APP-0020
        $mirrorB = Application::factory()->create([
            'id'                 => 19,
            'loan_application_id'=> 20,
            'app_id'             => 'APP-0020',
            'applicant'          => 'Test Borrower Two',
            'status'             => 'Pending',
            'recommended_amount' => 4000.00,
        ]);

        // Approve through mirror B
        $this->actingAs($manager)
            ->post(route('applications.approve', $mirrorB->id), [
                'approved_amount' => 16000,
                'reason'          => 'Good history',
            ]);

        // Applications page modal shows mirror B
        $modalData = $this->actingAs($manager)->get(route('admin.applications.data', $mirrorB->id))->getContent();
        $this->assertStringContainsString('APP-0020', $modalData);

        // Outbox approval row
        $outbox = $this->actingAs($manager)->get(route('admin.notifications-log'))->getContent();

        // With default HasOne, since mirror A is soft-deleted, HasOne should return mirror B
        // Let's see what actually happens
        $this->assertStringContainsString('APP-0020', $outbox,
            'Outbox should show APP-0020 even with soft-deleted mirror A');
    }
}
<?php

namespace Tests\Feature;

use App\Models\AppNotificationLog;
use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
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
        // The loan link format: openNotifLoan('APP-XXXX', N); return false;"
        $outbox = $this->actingAs($manager)->get(route('admin.notifications-log'))->getContent();

        // The outbox loan link should show APP-0010 (mirror B), not APP-0009 (mirror A).
        // HasOne without orderBy returns the first mirror by id (APP-0009), which is wrong.
        $this->assertStringContainsString(
            "openNotifLoan('APP-0010', 9)",
            $outbox,
            'Outbox loan link should point to APP-0010 (mirror B), not APP-0009 (mirror A — wrong!)'
        );

        // Sanity: APP-0009 should NOT appear as the loan link in the outbox
        $this->assertStringNotContainsString(
            "openNotifLoan('APP-0009', 8)",
            $outbox,
            'Outbox should NOT link to APP-0009 (mirror A — that is the wrong mirror)'
        );
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
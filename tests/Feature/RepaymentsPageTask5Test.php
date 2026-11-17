<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Task 5 — repayments page bugs (5.1 tabs, 5.2 filter row, 5.3 member ID).
 */
class RepaymentsPageTask5Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function officer(): User
    {
        return User::factory()->create(['usertype' => 'loan_officer']);
    }

    /** Portal member: user linked to a coop member row. */
    private function portalMemberLoan(string $memberId, string $email): array
    {
        $user = User::factory()->create(['email' => $email]);
        $member = CoopMember::factory()->create([
            'member_id' => $memberId,
            'email'     => $email,
            'user_id'   => $user->id,
        ]);

        $loan = LoanApplication::factory()->create([
            'user_id'          => $user->id,
            'email'            => $email,
            'amount'           => 10000,
            'term_months'      => 12,
            'status'           => 'approved',
            'remaining_balance' => 8000,
            'next_due_date'    => now()->addDays(2),
        ]);
        Application::factory()->create([
            'loan_application_id' => $loan->id,
            'app_id'              => 'APP-0001',
            'status'              => 'Approved',
            'remaining_balance'   => 8000,
        ]);

        return [$loan, $member];
    }

    /** Portal-less loan: no linked user, member matched by email only. */
    private function portalLessLoan(string $memberId, string $email): LoanApplication
    {
        $member = CoopMember::factory()->create([
            'member_id' => $memberId,
            'email'     => $email,
            'user_id'   => null,
        ]);

        return LoanApplication::factory()->create([
            'user_id'           => null,
            'email'             => $email,
            'amount'            => 10000,
            'term_months'       => 12,
            'status'            => 'approved',
            'remaining_balance' => 8000,
            'next_due_date'     => now()->addDays(2),
        ]);
    }

    private function paymentFor(LoanApplication $loan, string $ref = 'REP-TEST-1'): Payment
    {
        return Payment::factory()->create([
            'loan_application_id' => $loan->id,
            'amount_paid'         => 1000,
            'remaining_balance_after' => 7000,
            'reference_no'        => $ref,
            'payment_date'        => now(),
            'payment_method'      => 'Cash',
        ]);
    }

    /** @test */
    public function task_5_1_tabs_use_shared_classes_and_only_one_panel_shows()
    {
        $response = $this->actingAs($this->officer())->get('/repayments');

        $response->assertStatus(200);
        $html = $response->getContent();

        // Shared admin tab classes are present
        $this->assertStringContainsString('class="rep-tab-bar"', $html);
        // Payments tab is active by default
        $this->assertStringContainsString('class="rep-tab active" data-tab="payments"', $html);
        $this->assertStringContainsString('class="rep-tab" data-tab="due"', $html);
        // Only the Payments panel is visible; the Due panel is hidden
        $this->assertStringContainsString('id="tab-payments" class="rep-tab-pane"', $html);
        $this->assertStringContainsString('id="tab-due" class="rep-tab-pane rep-hidden"', $html);
        // Count badge rendered as a pill
        $this->assertStringContainsString('rep-tab-count', $html);
    }

    /** @test */
    public function task_5_4_member_dropdown_lists_portal_and_portal_less_members()
    {
        $portalLoan = $this->portalMemberLoan('MEM-DROP-1', 'drop1@example.com')[0];
        $this->paymentFor($portalLoan, 'REP-DROP-1');
        $portalLess = $this->portalLessLoan('MEM-DROP-2', 'drop2@example.com');
        $this->paymentFor($portalLess, 'REP-DROP-2');

        $response = $this->actingAs($this->officer())->get('/repayments');

        $response->assertStatus(200);
        $html = $response->getContent();
        // The member search dropdown lists both the portal member (user link)
        // and the portal-less member (email fallback).
        $this->assertStringContainsString('value="MEM-DROP-1"', $html);
        $this->assertStringContainsString('value="MEM-DROP-2"', $html);
    }

    /** @test */
    public function task_5_1_tab_due_url_param_opens_the_due_tab()
    {
        $response = $this->actingAs($this->officer())->get('/repayments?tab=due');

        $response->assertStatus(200);
        $html = $response->getContent();

        $this->assertStringContainsString('class="rep-tab active" data-tab="due"', $html);
        $this->assertStringContainsString('class="rep-tab" data-tab="payments"', $html);
        $this->assertStringContainsString('id="tab-due" class="rep-tab-pane"', $html);
        $this->assertStringContainsString('id="tab-payments" class="rep-tab-pane rep-hidden"', $html);
    }

    /** @test */
    public function task_5_2_show_reversed_toggle_moved_into_the_filter_row()
    {
        $response = $this->actingAs($this->officer())->get('/repayments');

        $response->assertStatus(200);
        $html = $response->getContent();

        // The checkbox lives inside the single filter form and auto-submits
        $this->assertMatchesRegularExpression(
            '/<form[^>]*class="rep-filter-row"[\s\S]*?name="show_reversed"[\s\S]*?onchange="this\.form\.submit\(\)"[\s\S]*?<\/form>/',
            $html
        );
        $this->assertSame(1, substr_count($html, 'name="show_reversed"'));
        // The old standalone "Apply" form under the record count is gone
        $this->assertStringNotContainsString('>Apply</button>', $html);
        // The record count stays above the table
        $this->assertStringContainsString('record(s)', $html);
    }

    /** @test */
    public function task_5_3_payments_table_shows_portal_member_id()
    {
        [$loan] = $this->portalMemberLoan('MEM-PORTAL-1', 'portal@example.com');
        $this->paymentFor($loan, 'REP-PORTAL');

        $response = $this->actingAs($this->officer())->get('/repayments');

        $response->assertStatus(200);
        $response->assertSee('MEM-PORTAL-1');
        $response->assertSee('REP-PORTAL');
    }

    /** @test */
    public function task_5_3_payments_table_shows_portal_less_member_id_matched_by_email()
    {
        $loan = $this->portalLessLoan('MEM-EMAIL-2', 'portal-less@example.com');
        $this->paymentFor($loan, 'REP-EMAIL');

        $response = $this->actingAs($this->officer())->get('/repayments');

        $response->assertStatus(200);
        $response->assertSee('MEM-EMAIL-2');
        $response->assertSee('REP-EMAIL');
    }

    /** @test */
    public function task_5_3_payments_table_shows_dash_when_no_member_matches()
    {
        $loan = LoanApplication::factory()->create([
            'user_id'           => null,
            'email'             => 'nobody@example.com',
            'amount'            => 10000,
            'term_months'       => 12,
            'status'            => 'approved',
            'remaining_balance' => 8000,
        ]);
        $this->paymentFor($loan, 'REP-NOMATCH');

        $response = $this->actingAs($this->officer())->get('/repayments');

        $response->assertStatus(200);
        $response->assertSee('REP-NOMATCH');
        // MEMBER column: borrower name renders, and no member-ID sub-line is
        // shown when no member matches the loan.
        $html = $response->getContent();
        $this->assertStringContainsString('<div style="font-weight: 600;">', $html);
        $this->assertStringNotContainsString('<div style="font-size: 0.72rem; color: #6b7280;">', $html);
    }

    /** @test */
    public function task_5_3_due_tab_shows_portal_less_member_id()
    {
        $loan = $this->portalLessLoan('MEM-DUE-3', 'due-tab@example.com');
        $loan->update(['next_due_date' => now()]);

        $response = $this->actingAs($this->officer())->get('/repayments?tab=due');

        $response->assertStatus(200);
        $response->assertSee('MEM-DUE-3');
    }

    /** @test */
    public function task_5_3_record_modal_loan_options_carry_member_id()
    {
        [$loan] = $this->portalMemberLoan('MEM-RECORD-4', 'record@example.com');

        $response = $this->actingAs($this->officer())->get('/repayments');

        $response->assertStatus(200);
        $response->assertSee('data-member-id="MEM-RECORD-4"', false);
    }

    /** @test */
    public function task_5_3_search_finds_payment_by_portal_less_member_id()
    {
        $loan = $this->portalLessLoan('MEM-SEARCH-5', 'search@example.com');
        $this->paymentFor($loan, 'REP-SEARCH');

        $response = $this->actingAs($this->officer())->get('/repayments', ['search' => 'MEM-SEARCH-5']);

        $response->assertStatus(200);
        $response->assertSee('REP-SEARCH');
        $response->assertSee('MEM-SEARCH-5');
    }

    /** @test */
    public function task_5_3_member_id_accessor_resolves_by_user_link_then_email()
    {
        [$loan, $member] = $this->portalMemberLoan('MEM-ACCESSOR-6', 'accessor@example.com');

        $this->assertSame('MEM-ACCESSOR-6', $loan->fresh()->memberId());

        // Portal-less loan resolves through the email fallback
        $portalLess = $this->portalLessLoan('MEM-ACCESSOR-7', 'accessor2@example.com');
        $this->assertSame('MEM-ACCESSOR-7', $portalLess->fresh()->memberId());

        // No match anywhere → null (blade renders "—")
        $orphan = LoanApplication::factory()->create([
            'user_id' => null,
            'email'   => 'orphan@example.com',
        ]);
        $this->assertNull($orphan->fresh()->memberId());
    }
}

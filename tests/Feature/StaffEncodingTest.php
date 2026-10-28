<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\User;
use App\Services\LoanEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task D (FR-05 - FR-09): staff encoding of applications and member profiles.
 *
 * A staff-encoded application must go through the same evaluation engine and
 * land in the same mirrored tables as a member's own submission; only
 * encoded_by differs. Staff must also be able to create and edit a member's
 * profile and financial profile, and read one member's whole picture from a
 * single page.
 */
class StaffEncodingTest extends TestCase
{
    use RefreshDatabase;

    private function loanOfficer(): User
    {
        return User::factory()->asLoanOfficer()->create();
    }

    /**
     * An active member with a linked account and a known financial profile.
     */
    private function member(array $overrides = []): CoopMember
    {
        $user = User::factory()->create(['usertype' => 'member']);

        $member = CoopMember::factory()->create(array_merge([
            'user_id'      => $user->id,
            'status'       => CoopMember::STATUS_ACTIVE,
            'full_name'    => 'Juan Santos',
            'email'        => $user->email,
            'monthly_income' => 30000,
            'existing_monthly_obligations' => 0,
            'share_capital' => 30000,
            'savings_balance' => 20000,
            'approved_credit_limit' => 50000,
            'address'      => '12 Rizal St, Brgy.centro',
            'occupation'   => 'Farmer',
        ], $overrides));

        $user->forceFill(['coop_member_id' => $member->id])->save();

        return $member->fresh();
    }

    private function encodePayload(CoopMember $member, array $overrides = []): array
    {
        return array_merge([
            'member_id'   => $member->id,
            'loan_type'   => 'regular',
            'amount'      => 20000,
            'term_months' => 36,
            'purpose'     => 'Capital for farm inputs',
        ], $overrides);
    }

    /** @test */
    public function a_loan_officer_can_encode_an_application_for_a_member()
    {
        $officer = $this->loanOfficer();
        $member  = $this->member();

        $response = $this->actingAs($officer)
            ->post(route('applications.encode.store'), $this->encodePayload($member));

        $response->assertRedirect(route('admin.members.show', $member->id));
        $response->assertSessionHas('success');

        $loanApp = LoanApplication::where('encoded_by', $officer->id)->first();
        $this->assertNotNull($loanApp, 'The staff-encoded application was not stored.');
        $this->assertSame($member->user_id, $loanApp->user_id);
        $this->assertSame('Juan Santos', $loanApp->full_name);
        $this->assertSame('regular', $loanApp->loan_type);
        $this->assertEquals(20000, $loanApp->amount);
        $this->assertEquals(36, $loanApp->term_months);
        $this->assertSame('pending', $loanApp->status);
        $this->assertStringStartsWith('SDCC-', $loanApp->reference);
    }

    /** @test */
    public function a_staff_encoded_application_is_evaluated_by_the_same_engine_as_a_member_submission()
    {
        $officer = $this->loanOfficer();
        $member  = $this->member();

        $this->actingAs($officer)
            ->post(route('applications.encode.store'), $this->encodePayload($member));

        $loanApp = LoanApplication::where('encoded_by', $officer->id)->first();

        // The engine's own verdict must be what got stored.
        $expected = app(LoanEvaluationService::class)->evaluate($loanApp->fresh());
        $this->assertEquals($expected['recommended_amount'], $loanApp->recommended_amount);
        $this->assertSame($expected['risk_level'], $loanApp->risk_level);
        $this->assertEquals($expected['risk_score'], $loanApp->risk_score);

        // And the recommended amount never exceeds what was requested.
        $this->assertLessThanOrEqual((float) $loanApp->amount, (float) $loanApp->recommended_amount);
    }

    /** @test */
    public function a_staff_encoded_application_is_mirrored_into_the_admin_applications_table()
    {
        $officer = $this->loanOfficer();
        $member  = $this->member();

        $this->actingAs($officer)
            ->post(route('applications.encode.store'), $this->encodePayload($member));

        $loanApp = LoanApplication::where('encoded_by', $officer->id)->first();

        $mirror = Application::where('loan_application_id', $loanApp->id)->first();
        $this->assertNotNull($mirror, 'No mirrored admin application row was written.');
        $this->assertStringStartsWith('APP-', $mirror->app_id);
        $this->assertSame('Juan Santos', $mirror->applicant);
        $this->assertSame('regular', $mirror->loan_type);
        $this->assertEquals($loanApp->recommended_amount, $mirror->recommended_amount);
        $this->assertSame($loanApp->risk_level, $mirror->risk_level);
        $this->assertSame($loanApp->risk_score, $mirror->risk_score);
        $this->assertSame('Pending', $mirror->status);
    }

    /** @test */
    public function a_member_submission_and_a_staff_encoding_write_the_same_tables()
    {
        // --- member self-submission ---
        $memberUser = User::factory()->asMember()->create()->fresh();
        $this->actingAs($memberUser)->post(route('user.loans.store'), [
            'loan_type'       => 'regular',
            'full_name'       => $memberUser->name,
            'email'           => $memberUser->email,
            'source_of_income'=> 'Salary',
            'employer_name'   => 'Widget Co.',
            'monthly_income'  => '20000',
            'existing_monthly_obligations' => '0',
            'purpose_of_loan' => 'Personal',
            'term_months'     => '36',
            'amount'          => '5000',
            'agree'           => 'yes',
        ]);

        $memberLoan = LoanApplication::where('user_id', $memberUser->id)->first();
        $this->assertNotNull($memberLoan);
        $this->assertNull($memberLoan->encoded_by, 'A member submission must not be marked as staff-encoded.');
        $this->assertNotNull(
            Application::where('loan_application_id', $memberLoan->id)->first(),
            'A member submission is not mirrored into the admin applications table.'
        );

        // --- staff encoding ---
        $officer = $this->loanOfficer();
        $staffMember = $this->member();
        $this->actingAs($officer)
            ->post(route('applications.encode.store'), $this->encodePayload($staffMember));

        $staffLoan = LoanApplication::where('encoded_by', $officer->id)->first();

        $this->assertSame(
            array_keys(Application::where('loan_application_id', $staffLoan->id)->first()->getAttributes()),
            array_keys(Application::where('loan_application_id', $memberLoan->id)->first()->getAttributes()),
            'Both paths must populate the same mirrored columns.'
        );
        $this->assertEquals($officer->id, $staffLoan->encoded_by);
    }

    /** @test */
    public function staff_cannot_encode_an_application_for_an_inactive_member()
    {
        $officer = $this->loanOfficer();
        $member  = $this->member(['status' => CoopMember::STATUS_INACTIVE]);

        $response = $this->actingAs($officer)
            ->post(route('applications.encode.store'), $this->encodePayload($member));

        $response->assertSessionHas('error');
        $this->assertSame(0, LoanApplication::where('encoded_by', $officer->id)->count());
    }

    /** @test */
    public function a_term_that_the_product_does_not_offer_is_rejected()
    {
        $officer = $this->loanOfficer();
        $member  = $this->member();

        $response = $this->actingAs($officer)
            ->post(route('applications.encode.store'), $this->encodePayload($member, ['term_months' => 120]));

        $response->assertSessionHas('error');
        $this->assertSame(0, LoanApplication::where('encoded_by', $officer->id)->count());
    }

    /** @test */
    public function a_member_cannot_encode_applications()
    {
        $member = $this->member();

        $response = $this->actingAs($member->user)
            ->get(route('applications.encode'));

        $response->assertForbidden();
    }

    /** @test */
    public function the_encode_form_lists_active_members()
    {
        $officer = $this->loanOfficer();
        $member  = $this->member();

        // The Encode Application button is on the /applications page (modal-based).
        $response = $this->actingAs($officer)->get(route('applications.index'));
        $response->assertOk();
        $response->assertSee('Encode Application');

        // The member financial JSON endpoint (used by the modal) returns the
        // member's financial profile.
        $json = $this->actingAs($officer)
            ->get(route('admin.members.financial', $member->id))
            ->assertOk()
            ->json();

        $this->assertTrue($json['success']);
        $this->assertSame('Juan Santos', $json['member_name']);
        $this->assertEquals(30000, $json['financial']['monthly_income']);
        $this->assertEquals(30000, $json['financial']['share_capital']);
    }

    /** @test */
    public function staff_can_create_a_member_profile_and_a_financial_profile()
    {
        $officer = $this->loanOfficer();

        $response = $this->actingAs($officer)->post(route('active-members.store'), [
            'member_id'     => 'SDCC-2026-0001',
            'full_name'     => 'Maria Reyes',
            'email'         => 'maria@example.com',
            'phone'         => '09171234567',
            'date_of_birth' => '1990-04-12',
            'address'       => '7 Mabini St, Brgy. West',
            'occupation'    => 'Vendor',
            'monthly_income' => 18000,
            'existing_monthly_obligations' => 2000,
            'savings_balance' => 5000,
            'share_capital' => 15000,
            'approved_credit_limit' => 25000,
            'is_registered' => '1',
            'status'        => CoopMember::STATUS_ACTIVE,
        ]);

        $response->assertRedirect(route('active-members.index'));

        $member = CoopMember::where('member_id', 'SDCC-2026-0001')->first();
        $this->assertNotNull($member);
        $this->assertSame('7 Mabini St, Brgy. West', $member->address);
        $this->assertSame('Vendor', $member->occupation);
        $this->assertEquals(18000, $member->monthly_income);
        $this->assertEquals(2000, $member->existing_monthly_obligations);
        $this->assertEquals(5000, $member->savings_balance);
        $this->assertEquals(15000, $member->share_capital);
        $this->assertEquals(25000, $member->approved_credit_limit);
    }

    /** @test */
    public function staff_can_update_a_member_profile_and_financial_profile()
    {
        $officer = $this->loanOfficer();
        $member  = $this->member();

        $response = $this->actingAs($officer)->put(route('admin.members.update', $member->id), [
            'occupation'   => 'Carpenter',
            'address'      => '9 Salud St',
            'monthly_income' => 41000,
            'existing_monthly_obligations' => 1500,
            'savings_balance' => 22000,
            'share_capital' => 33000,
            'approved_credit_limit' => 60000,
        ]);

        $response->assertRedirect(route('admin.members.show', $member->id));

        $member->refresh();
        $this->assertSame('Carpenter', $member->occupation);
        $this->assertSame('9 Salud St', $member->address);
        $this->assertEquals(41000, $member->monthly_income);
        $this->assertEquals(1500, $member->existing_monthly_obligations);
        $this->assertEquals(22000, $member->savings_balance);
        $this->assertEquals(33000, $member->share_capital);
        $this->assertEquals(60000, $member->approved_credit_limit);
    }

    /** @test */
    public function the_member_centered_page_shows_profile_financials_loans_payments_and_balance()
    {
        $officer = $this->loanOfficer();
        $member  = $this->member();

        $loanApp = LoanApplication::factory()->create([
            'user_id'   => $member->user_id,
            'full_name' => $member->full_name,
            'amount'    => 20000,
            'status'    => 'approved',
            'remaining_balance' => 12000,
            'next_due_date' => now()->subDays(3),
            'term_months' => 36,
        ]);

        Application::factory()->create([
            'loan_application_id' => $loanApp->id,
            'app_id'              => 'APP-0001',
            'applicant'           => $member->full_name,
            'status'              => 'approved',
        ]);

        Payment::create([
            'loan_application_id' => $loanApp->id,
            'user_id'             => $member->user_id,
            'amount_paid'         => 8000,
            'remaining_balance_after' => 12000,
            'payment_date'        => now()->subMonth(),
            'payment_method'      => 'Cash',
        ]);

        $response = $this->actingAs($officer)->get(route('admin.members.show', $member->id));

        $response->assertOk();
        $response->assertSee('Juan Santos');
        $response->assertSee('Rizal St');                       // address
        $response->assertSee('Farmer');                         // occupation
        $response->assertSee('30,000.00');                      // monthly income
        $response->assertSee('20,000.00');                      // savings
        $response->assertSee('APP-0001');                       // the loan
        $response->assertSee('8,000.00');                       // the payment
        $response->assertSee('12,000.00');                      // outstanding balance
        $response->assertSee('Overdue Loans (1)');              // overdue state
    }

    /** @test */
    public function a_credit_investigator_can_read_a_member_profile_but_not_edit_it()
    {
        $investigator = User::factory()->asCreditInvestigator()->create();
        $member = $this->member();

        $this->actingAs($investigator)
            ->get(route('admin.members.show', $member->id))
            ->assertOk();

        $this->actingAs($investigator)
            ->put(route('admin.members.update', $member->id), ['occupation' => 'Nope'])
            ->assertForbidden();

        $this->assertNotSame('Nope', $member->fresh()->occupation);
    }

    /** @test */
    public function a_member_cannot_read_another_members_profile()
    {
        $member = $this->member();
        $other  = $this->member();

        $this->actingAs($member->user)
            ->get(route('admin.members.show', $other->id))
            ->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The member loan form must carry the financial profile declared in the
 * membership application, and PURPOSE must be a dropdown on both the member
 * form and the two staff entry points (encode page + shared modal).
 */
class LoanFormPrefillTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function loan_form_prefills_financials_from_the_membership_record()
    {
        $user = User::factory()->asMember()->create();
        $member = CoopMember::where('user_id', $user->id)->firstOrFail();
        $member->forceFill([
            'monthly_income'               => 34567,
            'existing_monthly_obligations' => 1234,
            'source_of_income'             => 'Salary',
            'approved_monthly_credit_limit' => 34567,
            'monthly_credit_limit_source'  => 'manual',
        ])->save();
        $member = $member->fresh();

        $response = $this->actingAs($user)->get(route('user.loans.create'));

        $response->assertOk();
        // Value attributes come from the stored membership record, not a blank default.
        $response->assertSee('value="' . $member->monthly_income . '"', false);
        $response->assertSee('value="' . $member->existing_monthly_obligations . '"', false);
        $response->assertSee('<option value="Salary" selected>', false);
        // Bottom credit card uses the monthly limit semantics.
        $response->assertSee('Monthly Credit Limit');
        $response->assertSee('Monthly Available');
    }

    /** @test */
    public function purpose_is_a_dropdown_on_the_member_loan_form()
    {
        $user = User::factory()->asMember()->create();

        $response = $this->actingAs($user)->get(route('user.loans.create'));

        $response->assertOk();
        $response->assertSee('name="purpose_of_loan"', false);
        $response->assertSee('value="Educational"', false);
        $response->assertSee('value="Business"', false);
        $response->assertSee('value="Personal"', false);
        $response->assertSee('value="Emergency"', false);
    }

    /** @test */
    public function purpose_is_a_dropdown_on_the_admin_encode_page()
    {
        $officer = User::factory()->asLoanOfficer()->create();

        $response = $this->actingAs($officer)->get(route('applications.encode'));

        $response->assertOk();
        $response->assertSee('name="purpose"', false);
        $response->assertSee('>Select purpose<', false);
        $response->assertSee('value="Educational"', false);
        $response->assertSee('value="Emergency"', false);
    }
}

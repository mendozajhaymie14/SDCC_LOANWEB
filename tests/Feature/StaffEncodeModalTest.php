<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffEncodeModalTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_loan_officer_can_submit_a_valid_encode_payload_via_the_modal_and_see_it_on_applications_index()
    {
        $officer = User::factory()->asLoanOfficer()->create();
        $member = CoopMember::factory()->create([
            'status' => CoopMember::STATUS_ACTIVE,
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 5000,
            'share_capital' => 30000,
            'savings_balance' => 20000,
            'approved_credit_limit' => 80000,
            // Monthly allowance ≥ 25,000 request so monthly gate passes
            'approved_monthly_credit_limit' => 50000,
            'monthly_credit_limit_source' => 'manual',
            'occupation' => 'Vendor',
        ]);

        // Ensure the member has a linked user for evaluation
        $user = User::factory()->create([
            'email' => $member->email,
            'name' => $member->full_name,
        ]);
        $member->update(['user_id' => $user->id]);

        $response = $this->actingAs($officer)->post(route('applications.encode.store'), [
            'member_id'                    => $member->id,
            'loan_type'                    => 'regular',
            'amount'                       => 25000,
            'term_months'                  => 36,
            'purpose'                      => 'Business capital',
            'monthly_income'               => 50000,
            'existing_monthly_obligations' => 5000,
            'source_of_income'             => 'Salary',
            'document_consent'             => '1',
            'required_documents'           => [],
        ]);

        // Should redirect to applications.index with success flash
        $response->assertRedirect(route('applications.index'));
        $response->assertSessionHas('success', 'Application encoded successfully.');

        // GET applications.index should now contain the new application
        $indexResponse = $this->actingAs($officer)->get(route('applications.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee($member->full_name);
    }

    /** @test */
    public function validation_failure_on_missing_member_redirects_back_with_errors_and_reopens_modal()
    {
        $officer = User::factory()->asLoanOfficer()->create();

        // Submit without member_id (required field)
        $response = $this->actingAs($officer)->post(route('applications.encode.store'), [
            'loan_type'                    => 'regular',
            'amount'                       => 25000,
            'term_months'                  => 36,
            'purpose'                      => 'Business capital',
            'monthly_income'               => 50000,
            'existing_monthly_obligations' => 5000,
            'source_of_income'             => 'Salary',
            'document_consent'             => '1',
        ]);

        // Should redirect back (to the encode page)
        $response->assertRedirect();
        $response->assertSessionHasErrors('member_id');
    }

    /** @test */
    public function rejected_loan_type_staff_encode_redirects_back()
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $officer = User::factory()->asLoanOfficer()->create();
        $this->actingAs($officer);

        foreach (['character', 'recovery', 'secured', 'share_cap'] as $lt) {
            $payload = [
                'member_id'                    => '1', // exists:coop_members,id
                'loan_type'                    => $lt,
                'amount'                       => 5000,
                'term_months'                  => 36,
                'purpose'                      => 'Business capital',
                'monthly_income'               => 50000,
                'existing_monthly_obligations' => 5000,
                'source_of_income'             => 'Salary',
                'document_consent'             => '1',
            ];

            $response = $this->post(route('applications.encode.store'), $payload);
            $response->assertRedirect();
            $response->assertSessionHasErrors('loan_type');
            $response->assertStatus(302);
        }
    }
}
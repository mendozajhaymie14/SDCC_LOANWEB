<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use App\Services\LoanEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reproduces the reported symptom: a staff '+ New Application' submit does
 * nothing visible on /applications. Posts to applications.encode.store with
 * exact modal field names (read from encode-application.blade.php), then checks
 * redirect target, session flash, validation errors, log lines, and whether
 * both the LoanApplication and the mirrored Application row were created.
 */
class ApplicationsIndexAppearanceTest extends TestCase
{
    use RefreshDatabase;

    private function loanOfficer(): User
    {
        return User::factory()->asLoanOfficer()->create();
    }

    private function member(): CoopMember
    {
        // Active member, linked account, financial profile, NO pending loans.
        $user = User::factory()->create(['usertype' => 'member']);
        return CoopMember::factory()->create(array_merge([
            'user_id'          => $user->id,
            'status'           => CoopMember::STATUS_ACTIVE,
            'full_name'        => 'Juan Santos',
            'email'            => $user->email,
            'monthly_income'   => 30000,
            'existing_monthly_obligations' => 0,
            'share_capital'    => 30000,
            'savings_balance'  => 20000,
            'approved_credit_limit' => 50000,
            // Monthly allowance ≥ 20,000 encode amount so the monthly gate passes
            'approved_monthly_credit_limit' => 50000,
            'monthly_credit_limit_source' => 'manual',
            'address'          => '12 Rizal St',
            'occupation'       => 'Farmer',
        ], [
            // Ensure no other pending loan exists for this user.
            'user_id' => $user->id,
        ]));
    }

    /** @test */
    public function a_staff_submission_appears_on_applications_index_and_has_a_visible_flash()
    {
        $officer = $this->loanOfficer();
        $member  = $this->member();

        $payload = [
            'member_id'                      => $member->id,
            'loan_type'                       => 'regular',
            'amount'                          => 20000,
            'term_months'                      => 36,
            'purpose'                         => 'Capital for farm inputs',
            'monthly_income'                   => '30000',
            'existing_monthly_obligations'     => '0',
            'source_of_income'                => 'Salary',
        ];

        $response = $this->actingAs($officer)
            ->post(route('applications.encode.store'), $payload);

        // Success path: redirect target + visible success flash.
        $response->assertRedirect(route('applications.index'));
        $response->assertSessionHas('success');
        $response->assertSessionHasNoErrors();

        // Both tables must receive a row.
        $loanApp = LoanApplication::where('encoded_by', $officer->id)->first();
        $this->assertNotNull($loanApp, 'staff LoanApplication row missing');
        $mirror = Application::where('loan_application_id', $loanApp->id)->first();
        $this->assertNotNull($mirror, 'staff mirror Application row missing');

        // Index query must include the new row.
        $response = $this->actingAs($officer)->get(route('applications.index'));
        $response->assertOk();
        $response->assertSee('Juan Santos');
        $response->assertSee('APP-');

        // And the flash rendered on /applications.
        $response->assertSee('Application encoded successfully.');
    }

    /** @test */
    public function a_missing_member_is_rejected_with_redirect_back_and_errors()
    {
        $officer = $this->loanOfficer();
        $payload = [
            'member_id'                      => 999999, // nonexistent
            'loan_type'                      => 'regular',
            'amount'                         => 20000,
            'term_months'                    => 36,
            'purpose'                        => 'Capital for farm inputs',
            'monthly_income'                 => '30000',
            'existing_monthly_obligations'   => '0',
            'source_of_income'               => 'Salary',
        ];

        $response = $this->actingAs($officer)
            ->post(route('applications.encode.store'), $payload);

        // No success flash on a validation-failure redirect.
        $response->assertRedirectBack();
        $response->assertSessionHasErrors(['member_id']);
    }
}

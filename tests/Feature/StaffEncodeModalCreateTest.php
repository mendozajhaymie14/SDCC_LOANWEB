<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Support\Permissions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug regression test for staff encode modal:
 * source_of_income <option value> was using $key (numeric index) instead of $label,
 * so the form sent source_of_income=6 instead of "Allowance / Family support".
 * The server rejected it because the rule is `in:Salary,...,Allowance / Family support,Other`.
 */
class StaffEncodeModalCreateTest extends TestCase
{
    use RefreshDatabase;

    private const ROLE_BRANCH_MANAGER = Permissions::ROLE_BRANCH_MANAGER;
    private const ROLE_LOAN_OFFICER   = Permissions::ROLE_LOAN_OFFICER;
    private const ROLE_CREDIT_INVESTIGATOR = Permissions::ROLE_CREDIT_INVESTIGATOR;

    private function makeMember(): CoopMember
    {
        return CoopMember::factory()->create([
            'status'           => CoopMember::STATUS_ACTIVE,
            'full_name'        => 'Loterina Alyanna Peralta',
            'monthly_income'   => 20000,
            'existing_monthly_obligations' => 1000,
            'share_capital'    => 50000,
            'savings_balance'  => 25000,
            'approved_credit_limit' => 100000,
            'occupation'       => 'Vendor',
            'email'            => 'alyn.peralta@example.com',
            'contact_number'   => '09171234567',
        ]);
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'role'   => $role,
            'email'  => 'encode-' . str_replace('_', '-', $role) . '@test.test',
        ]);
    }

    private function loginUser(string $role): void
    {
        $this->actingAs($this->makeUser($role));
    }

    private function encodePayload(int $memberId): array
    {
        return [
            'member_id'                     => $memberId,
            'loan_type'                     => 'character',
            'amount'                        => 1000,
            'term_months'                   => 36,
            'purpose'                       => 'utang',
            'monthly_income'               => 20000,
            'existing_monthly_obligations' => 1000,
            'source_of_income'              => 'Allowance / Family support',
            'document_consent'              => '1',
        ];
    }

    /** @test */
    public function a_staff_encode_creates_an_application_and_displays_it_for_branch_manager()
    {
        $this->loginUser(self::ROLE_BRANCH_MANAGER);

        $member = $this->makeMember();

        $response = $this->post('/applications/encode', $this->encodePayload($member->id));

        $response->assertRedirect(route('applications.index'));
        $response->assertSessionHas('success', 'Application encoded successfully.');

        $list = $this->get(route('applications.index'));
        $list->assertOk();
        $list->assertSee('Application encoded successfully.');
        $list->assertSee('Loterina Alyanna Peralta');
    }

    /** @test */
    public function a_staff_encode_creates_an_application_and_displays_it_for_loan_officer()
    {
        $this->loginUser(self::ROLE_LOAN_OFFICER);

        $member = $this->makeMember();

        $response = $this->post('/applications/encode', $this->encodePayload($member->id));

        $response->assertRedirect(route('applications.index'));
        $response->assertSessionHas('success', 'Application encoded successfully.');

        $list = $this->get(route('applications.index'));
        $list->assertOk();
        $list->assertSee('Application encoded successfully.');
        $list->assertSee('Loterina Alyanna Peralta');
    }

    /** @test */
    public function a_staff_encode_creates_an_application_and_displays_it_for_credit_investigator()
    {
        $this->loginUser(self::ROLE_CREDIT_INVESTIGATOR);

        $member = $this->makeMember();

        $response = $this->post('/applications/encode', $this->encodePayload($member->id));

        $response->assertRedirect(route('applications.index'));
        $response->assertSessionHas('success', 'Application encoded successfully.');

        $list = $this->get(route('applications.index'));
        $list->assertOk();
        $list->assertSee('Application encoded successfully.');
        $list->assertSee('Loterina Alyanna Peralta');
    }

    /** @test */
    public function pre_fix_submission_with_numeric_source_fails_validation_and_redirects_back()
    {
        // Before the fix, the form sent source_of_income=6 (the array index)
        // instead of "Allowance / Family support" (the label). Validation
        // rule is `in:Salary,...,Allowance / Family support,Other`, so index 6 fails.
        $this->loginUser(self::ROLE_BRANCH_MANAGER);

        $member = $this->makeMember();

        $payload = $this->encodePayload($member->id);
        $payload['source_of_income'] = '6';

        $response = $this->post('/applications/encode', $payload);

        $response->assertRedirect();
        $response->assertSessionHasErrors('source_of_income');
    }

    /** @test */
    public function post_browser_payload_as_branch_manager_follows_redirect_and_banner_appears()
    {
        $this->loginUser(self::ROLE_BRANCH_MANAGER);

        $member = $this->makeMember();

        $response = $this->post('/applications/encode', $this->encodePayload($member->id));

        $response->assertRedirect(route('applications.index'));
        $response->assertSessionHas('success', 'Application encoded successfully.');

        // GET the redirected page and assert success banner is in the HTML
        $list = $this->get(route('applications.index'));
        $list->assertOk();
        $list->assertSee('Application encoded successfully.');
        $list->assertSee('Loterina Alyanna Peralta');
    }

    /** @test
     * Member with NO portal account (user_id null) but email on coop_members.
     * Staff encode must copy coop_members.email into loan_applications.email
     * and set user_id = null on the loan.
     */
    public function staff_encode_member_without_portal_account_copies_coop_member_email()
    {
        $this->loginUser(self::ROLE_BRANCH_MANAGER);

        // Create a coop_member with email but NO linked user (user_id = null)
        $member = CoopMember::factory()->create([
            'status'           => CoopMember::STATUS_ACTIVE,
            'full_name'        => 'No Portal Member',
            'monthly_income'   => 20000,
            'existing_monthly_obligations' => 1000,
            'share_capital'    => 50000,
            'savings_balance'  => 25000,
            'approved_credit_limit' => 100000,
            'occupation'       => 'Vendor',
            'email'            => 'noportal@example.com', // email only on coop_members
            'contact_number'   => '09171234567',
            'user_id'          => null, // NO portal account
        ]);

        $payload = $this->encodePayload($member->id);

        $response = $this->post('/applications/encode', $payload);

        $response->assertRedirect(route('applications.index'));
        $response->assertSessionHas('success', 'Application encoded successfully.');

        // Fetch the created loan application and verify email & user_id
        $loan = \App\Models\LoanApplication::latest()->first();
        $this->assertNotNull($loan, 'LoanApplication should be created');
        $this->assertSame('noportal@example.com', $loan->email, 'coop_members.email must be copied to loan_applications.email');
        $this->assertNull($loan->user_id, 'user_id must be null when member has no portal account');
    }
}
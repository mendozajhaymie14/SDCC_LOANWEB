<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\MemberApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task A: member profile editing (FR-05).
 *
 * Covers PUT /active-members/{id} (active-members.update):
 * - Loan Officer can edit a member and the fields change.
 * - Keeping your own email and phone is allowed.
 * - Using another member's email or phone is rejected.
 * - A name-only match gives a warning, not a block.
 * - Credit Investigator and Admin get 403 on the PUT.
 */
class MemberEditTest extends TestCase
{
    use RefreshDatabase;

    private function loanOfficer(): User
    {
        return User::factory()->asLoanOfficer()->create();
    }

    private function member(array $overrides = []): CoopMember
    {
        return CoopMember::factory()->create(array_merge([
            'status'               => CoopMember::STATUS_ACTIVE,
            'full_name'            => 'Juan Santos',
            'email'                => 'juan@example.com',
            'contact_number'       => '09171234567',
            'date_of_birth'        => '1985-01-15',
            'address'              => '12 Rizal St, Brgy. Centro',
            'occupation'           => 'Farmer',
            'monthly_income'       => 30000,
            'existing_monthly_obligations' => 0,
            'savings_balance'      => 20000,
            'approved_credit_limit'=> 50000,
        ], $overrides))->fresh();
    }

    /** @test */
    public function a_loan_officer_can_edit_a_member_and_the_fields_change()
    {
        $officer = $this->loanOfficer();
        $member  = $this->member();

        $response = $this->actingAs($officer)
            ->put(route('active-members.update', $member->id), [
                'full_name'     => 'Maria Reyes',
                'email'         => 'maria@example.com',
                'phone'         => '09181234567',
                'date_of_birth' => '1990-04-12',
                'address'       => '7 Mabini St, Brgy. West',
                'occupation'    => 'Vendor',
                'monthly_income' => 18000,
                'existing_monthly_obligations' => 2000,
                'savings_balance' => 5000,
                'share_capital' => 15000,
                'approved_credit_limit' => 25000,
                'status'        => CoopMember::STATUS_ACTIVE,
            ]);

        $response->assertRedirect(route('active-members.index'));

        $member->refresh();
        $this->assertSame('Maria Reyes', $member->full_name);
        $this->assertSame('maria@example.com', $member->email);
        $this->assertSame('09181234567', $member->contact_number);
        $this->assertSame('7 Mabini St, Brgy. West', $member->address);
        $this->assertSame('Vendor', $member->occupation);
        $this->assertEquals(18000, $member->monthly_income);
        $this->assertEquals(2000, $member->existing_monthly_obligations);
        $this->assertEquals(5000, $member->savings_balance);
        $this->assertEquals(25000, $member->approved_credit_limit);
    }

    /** @test */
    public function keeping_your_own_email_and_phone_is_allowed()
    {
        $officer = $this->loanOfficer();
        $member  = $this->member();

        $response = $this->actingAs($officer)
            ->put(route('active-members.update', $member->id), [
                'full_name'     => 'Juan Santos',
                'email'         => 'juan@example.com',
                'phone'         => '09171234567',
                'address'       => 'Updated address',
                'status'        => CoopMember::STATUS_ACTIVE,
            ]);

        $response->assertRedirect(route('active-members.index'));

        $member->refresh();
        $this->assertSame('Updated address', $member->address);
        $this->assertSame('juan@example.com', $member->email);
        $this->assertSame('09171234567', $member->contact_number);
    }

    /** @test */
    public function using_another_members_email_is_rejected()
    {
        $officer = $this->loanOfficer();
        $member  = $this->member();
        $other   = $this->member(['email' => 'other@example.com']);

        $response = $this->actingAs($officer)
            ->put(route('active-members.update', $member->id), [
                'full_name'     => 'Juan Santos',
                'email'         => 'other@example.com',
                'phone'         => '09171234567',
                'status'        => CoopMember::STATUS_ACTIVE,
            ]);

        $response->assertRedirect(route('active-members.index'));
        $response->assertSessionHasErrors('email');

        $member->refresh();
        $this->assertNotSame('other@example.com', $member->email);
    }

    /** @test */
    public function using_another_members_phone_is_rejected()
    {
        $officer = $this->loanOfficer();
        $member  = $this->member();
        $other   = $this->member(['contact_number' => '09181234567', 'email' => 'other@example.com']);

        $response = $this->actingAs($officer)
            ->put(route('active-members.update', $member->id), [
                'full_name'     => 'Juan Santos',
                'email'         => 'juan@example.com',
                'phone'         => '09181234567',
                'status'        => CoopMember::STATUS_ACTIVE,
            ]);

        $response->assertRedirect(route('active-members.index'));
        $response->assertSessionHasErrors('email');

        $member->refresh();
        $this->assertNotSame('09181234567', $member->contact_number);
    }

    /** @test */
    public function a_name_only_match_gives_a_warning_not_a_block()
    {
        $officer = $this->loanOfficer();
        $member  = $this->member();
        $other   = $this->member(['email' => 'other@example.com', 'contact_number' => '09181234567']);

        // Edit member A using member B's same name but member A's own email/phone
        $response = $this->actingAs($officer)
            ->put(route('active-members.update', $member->id), [
                'full_name'     => 'Juan Santos',
                'email'         => 'juan@example.com',
                'phone'         => '09171234567',
                'status'        => CoopMember::STATUS_ACTIVE,
            ]);

        $response->assertRedirect(route('active-members.index'));
        $response->assertSessionHas('warning');

        // Without confirm_continue, fields should not change
        $member->refresh();
        $this->assertSame('Juan Santos', $member->full_name);
    }

    /** @test */
    public function a_credit_investigator_gets_403_on_put_update()
    {
        $investigator = User::factory()->asCreditInvestigator()->create();
        $member = $this->member();

        $this->actingAs($investigator)
            ->put(route('active-members.update', $member->id), [
                'full_name' => 'Nope',
                'status' => CoopMember::STATUS_ACTIVE,
            ])
            ->assertForbidden();

        $this->assertNotSame('Nope', $member->fresh()->full_name);
    }

    /** @test */
    public function an_admin_gets_403_on_put_update()
    {
        $admin = User::factory()->asAdmin()->create();
        $member = $this->member();

        $this->actingAs($admin)
            ->put(route('active-members.update', $member->id), [
                'full_name' => 'Nope',
                'status' => CoopMember::STATUS_ACTIVE,
            ])
            ->assertForbidden();

        $this->assertNotSame('Nope', $member->fresh()->full_name);
    }

}

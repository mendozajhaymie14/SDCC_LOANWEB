<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The loan application gate: only an active cooperative member may POST to
 * /user/loans/apply. A 'user' (registered, no coop link) is bounced to the
 * membership page with a flash error; staff without apply_loan get plain 403;
 * members get through.
 */
class LoanApplyGateTest extends TestCase
{
    use RefreshDatabase;

    private function postValidLoan(string $userId): void
    {
        $user = User::find($userId);
        $this->actingAs($user);

        $this->post(route('user.loans.store'), [
            'loan_type'       => 'regular',
            'full_name'       => $user->name,
            'email'           => $user->email,
            'contact_number'  => null,
            'address'         => null,
            'birth_date'     => null,
            'source_of_income' => 'Salary',
            'employer_name'  => 'Widget Co.',
            'monthly_income'        => '20000',
            'existing_monthly_obligations' => '0',
            'purpose_of_loan'   => 'Personal',
            'term_months'       => '36',
            'amount'          => '5000',
            'agree'           => 'yes',
        ]);
    }

    public function test_non_member_is_redirected_to_membership_on_create(): void
    {
        $user = User::factory()->asUser()->create();
        $this->actingAs($user);

        $response = $this->get(route('user.loans.create'));

        $response->assertRedirect(route('user.membership'));
        $response->assertSessionHas('error');
    }

    public function test_non_member_cannot_submit_application(): void
    {
        $user = User::factory()->asUser()->create();
        $this->actingAs($user);

        $response = $this->post(route('user.loans.store'), [
            'loan_type'    => 'regular',
            'full_name'    => $user->name,
            'email'        => $user->email,
            'source_of_income' => 'Salary',
            'employer_name'   => 'Widget Co.',
            'monthly_income'  => '20000',
            'existing_monthly_obligations' => '0',
            'purpose_of_loan'   => 'Personal',
            'term_months'    => '36',
            'amount'         => '5000',
        ]);

        $response->assertRedirect(route('user.membership'));
        $response->assertSessionHas('error');
        $this->assertEquals(0, LoanApplication::where('user_id', $user->id)->count());
    }

    public function test_staff_without_apply_loan_get_403(): void
    {
        foreach ([
            Permissions::ROLE_ADMIN,
            Permissions::ROLE_BRANCH_MANAGER,
            Permissions::ROLE_LOAN_OFFICER,
            Permissions::ROLE_CREDIT_INVESTIGATOR,
        ] as $role) {
            $user = User::factory()->create(['usertype' => $role]);
            $this->actingAs($user);

            $this->post(route('user.loans.store'), [])->assertForbidden();
        }
    }

    /** A member whose cooperative membership is live can submit. */
    public function test_active_member_can_submit_application(): void
    {
        $member = User::factory()->asMember()->create()->fresh();

        // No pre-existing loan: the pending-recheck and evaluation path run.
        $this->postValidLoan($member->id);

        $this->assertTrue($member->isActiveCoopMember());
        $this->assertEquals(1, LoanApplication::where('user_id', $member->id)->count());
    }
}

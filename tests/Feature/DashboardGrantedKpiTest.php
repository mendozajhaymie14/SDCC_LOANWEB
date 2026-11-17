<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use App\Support\LoanStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardGrantedKpiTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'usertype' => 'branch_manager',
            'email'    => 'kpi-admin@example.com',
        ], $attributes));
    }

    private function makeMember(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'usertype' => 'member',
            'email'    => 'kpi-member@example.com',
        ], $attributes));
    }

    /** @test */
    public function dashboard_granted_kpi_uses_approved_amount_for_approved_and_paid_loans()
    {
        $admin = $this->makeAdmin();
        $member1 = $this->makeMember(['email' => 'kpi-a@example.com']);
        $member2 = $this->makeMember(['email' => 'kpi-b@example.com']);

        CoopMember::factory()->create([
            'user_id'      => $member1->id,
            'share_capital' => 30000,
            'is_migs'      => false,
        ]);
        CoopMember::factory()->create([
            'user_id'      => $member2->id,
            'share_capital' => 30000,
            'is_migs'      => false,
        ]);

        // APPROVED loan: requested 50000, approved_amount 10000 -> counts 10000
        LoanApplication::factory()->create([
            'user_id'          => $member1->id,
            'email'            => $member1->email,
            'full_name'        => 'Approved Borrower',
            'loan_type'        => 'regular',
            'amount'           => 50000,
            'approved_amount'  => 10000,
            'status'           => LoanStatus::APPROVED,
            'monthly_income'   => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'    => 30000,
            'term_months'      => 12,
            'remaining_balance' => 0,
        ]);

        // PAID loan: requested 20000, approved_amount 15000 -> counts 15000
        LoanApplication::factory()->create([
            'user_id'          => $member2->id,
            'email'            => $member2->email,
            'full_name'        => 'Paid Borrower',
            'loan_type'        => 'regular',
            'amount'           => 20000,
            'approved_amount'  => 15000,
            'status'           => LoanStatus::PAID,
            'monthly_income'   => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'    => 30000,
            'term_months'      => 12,
            'remaining_balance' => 0,
        ]);

        // PENDING loan: requested 30000, no approved_amount -> must NOT count
        LoanApplication::factory()->create([
            'user_id'          => $this->makeMember(['email' => 'kpi-c@example.com'])->id,
            'email'            => 'kpi-c@example.com',
            'full_name'        => 'Pending Borrower',
            'loan_type'        => 'regular',
            'amount'           => 30000,
            'status'           => LoanStatus::PENDING,
            'monthly_income'   => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'    => 30000,
            'term_months'      => 12,
            'remaining_balance' => 0,
        ]);

        // REJECTED loan: must NOT count
        LoanApplication::factory()->create([
            'user_id'          => $this->makeMember(['email' => 'kpi-d@example.com'])->id,
            'email'            => 'kpi-d@example.com',
            'full_name'        => 'Rejected Borrower',
            'loan_type'        => 'regular',
            'amount'           => 40000,
            'status'           => LoanStatus::REJECTED,
            'monthly_income'   => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'    => 30000,
            'term_months'      => 12,
            'remaining_balance' => 0,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));
        $response->assertSuccessful();

        // Expected: 10000 + 15000 = 25000 (NOT 50000+20000=70000, NOT 50000+20000+30000=100000)
        $response->assertSee(number_format(25000, 2));
        $response->assertDontSee(number_format(70000, 2));
    }
}

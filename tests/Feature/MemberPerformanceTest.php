<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Member Borrowing Performance endpoint (Active Members modal).
 *
 * Covers GET /active-members/{id}/performance:
 * - Loan Officer can fetch a member's borrowing performance JSON.
 * - On-time / late / overdue / performance label are computed correctly.
 */
class MemberPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function loanOfficer(): User
    {
        return User::factory()->asLoanOfficer()->create();
    }

    private function memberWithUser(): CoopMember
    {
        $user = User::factory()->create();
        return CoopMember::factory()->create([
            'status'    => CoopMember::STATUS_ACTIVE,
            'full_name' => 'Juan Santos',
            'email'     => 'juan@example.com',
            'user_id'   => $user->id,
        ]);
    }

    /** @test */
    public function a_loan_officer_can_fetch_borrowing_performance()
    {
        $officer = $this->loanOfficer();
        $member  = $this->memberWithUser();

        $loan = LoanApplication::factory()->create([
            'user_id'        => $member->user_id,
            'status'         => 'approved',
            'next_due_date'  => '2025-03-01',
        ]);

        // 3 payments anchored to next_due_date = 2025-03-01:
        //   index 0 due = 2024-12-01, index 1 due = 2025-01-01, index 2 due = 2025-02-01
        Payment::factory()->create([
            'loan_application_id' => $loan->id,
            'payment_date'        => '2024-12-01',   // exactly on due date → on-time
        ]);
        Payment::factory()->create([
            'loan_application_id' => $loan->id,
            'payment_date'        => '2025-01-15',   // 14 days late → late
        ]);
        Payment::factory()->create([
            'loan_application_id' => $loan->id,
            'payment_date'        => '2025-04-15',   // 73 days late → late + overdue
        ]);

        $response = $this->actingAs($officer)
            ->getJson(route('active-members.performance', $member->id));

        $response->assertStatus(200)
            ->assertJsonStructure(['on_time_count', 'late_count', 'overdue_count', 'performance_label']);

        $this->assertSame(1, $response->json('on_time_count'));
        $this->assertSame(2, $response->json('late_count'));
        $this->assertSame(1, $response->json('overdue_count'));
        $this->assertSame('Poor', $response->json('performance_label'));
    }

    /** @test */
    public function a_member_with_no_loans_returns_zero_counts()
    {
        $officer = $this->loanOfficer();
        $member  = $this->memberWithUser();

        $response = $this->actingAs($officer)
            ->getJson(route('active-members.performance', $member->id));

        $response->assertStatus(200)
            ->assertJson([
                'on_time_count'      => 0,
                'late_count'         => 0,
                'overdue_count'      => 0,
                'performance_label'  => 'Good',
            ]);
    }

    /** @test */
    public function a_good_performance_label_is_returned_when_all_payments_are_on_time()
    {
        $officer = $this->loanOfficer();
        $member  = $this->memberWithUser();

        $loan = LoanApplication::factory()->create([
            'user_id'       => $member->user_id,
            'status'        => 'approved',
            'next_due_date' => '2025-03-01',
        ]);

        Payment::factory()->create([
            'loan_application_id' => $loan->id,
            'payment_date'        => '2024-11-15',   // early → on-time
        ]);
        Payment::factory()->create([
            'loan_application_id' => $loan->id,
            'payment_date'        => '2024-12-15',   // early → on-time
        ]);

        $response = $this->actingAs($officer)
            ->getJson(route('active-members.performance', $member->id));

        $response->assertStatus(200)
            ->assertJson([
                'on_time_count'      => 2,
                'late_count'         => 0,
                'overdue_count'      => 0,
                'performance_label'  => 'Good',
            ]);
    }
}

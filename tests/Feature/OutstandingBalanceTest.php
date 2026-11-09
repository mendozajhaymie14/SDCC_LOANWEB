<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\User;
use App\Services\CreditLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutstandingBalanceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_id_linked_loans_are_summed_correctly()
    {
        $user = User::factory()->create();
        $member = CoopMember::factory()->create(['user_id' => $user->id]);

        // Create approved loans for this user/member
        $loan1 = \App\Models\LoanApplication::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'remaining_balance' => 50000,
        ]);

        $loan2 = \App\Models\LoanApplication::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'remaining_balance' => 30000,
        ]);

        // Create a rejected loan (should not be counted)
        \App\Models\LoanApplication::factory()->create([
            'user_id' => $user->id,
            'status' => 'rejected',
            'remaining_balance' => 10000,
        ]);

        $outstanding = CoopMember::outstandingBalance($member);
        $this->assertEquals(80000, $outstanding);
    }

    /** @test */
    public function rejected_loan_not_counted_in_outstanding()
    {
        $user = User::factory()->create();
        $member = CoopMember::factory()->create(['user_id' => $user->id]);

        // One approved, one rejected
        $approved = \App\Models\LoanApplication::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'remaining_balance' => 50000,
        ]);

        $rejected = \App\Models\LoanApplication::factory()->create([
            'user_id' => $user->id,
            'status' => 'rejected',
            'remaining_balance' => 20000,
        ]);

        $outstanding = CoopMember::outstandingBalance($member);
        $this->assertEquals(50000, $outstanding);
    }

    /** @test */
    public function user_id_null_loan_only_counts_for_matching_email()
    {
        // Member A with user_id set
        $userA = User::factory()->create();
        $memberA = CoopMember::factory()->create(['user_id' => $userA->id, 'email' => 'memberA@sdcc.ph']);

        // Member B with user_id set, different email
        $userB = User::factory()->create();
        $memberB = CoopMember::factory()->create(['user_id' => $userB->id, 'email' => 'memberB@sdcc.ph']);

        // Staff-encoded loan (user_id = null) for Member A's email
        $loanA = \App\Models\LoanApplication::factory()->create([
            'user_id' => null,
            'email' => 'memberA@sdcc.ph',
            'status' => 'approved',
            'remaining_balance' => 25000,
        ]);

        // Staff-encoded loan (user_id = null) for Member B's email (different!)
        $loanB = \App\Models\LoanApplication::factory()->create([
            'user_id' => null,
            'email' => 'memberB@sdcc.ph',
            'status' => 'approved',
            'remaining_balance' => 35000,
        ]);

        $outstandingA = CoopMember::outstandingBalance($memberA);
        $outstandingB = CoopMember::outstandingBalance($memberB);

        // Member A should include the loan matched by email (user_id = null)
        $this->assertEquals(25000, $outstandingA, 'Member A should include loan matched by email fallback');

        // Member B should include the loan matched by email (user_id = null)
        $this->assertEquals(35000, $outstandingB, 'Member B should include loan matched by email fallback');
    }

    /** @test */
    public function outstanding_balance_zero_when_no_approved_loans()
    {
        $user = User::factory()->create();
        $member = CoopMember::factory()->create(['user_id' => $user->id]);

        $outstanding = CoopMember::outstandingBalance($member);
        $this->assertEquals(0, $outstanding);
    }

    /** @test */
    public function member_without_user_id_and_no_email_match_returns_zero()
    {
        // Member with user_id = null but no email on the member record
        $member = CoopMember::factory()->create(['user_id' => null, 'email' => '']);

        $outstanding = CoopMember::outstandingBalance($member);
        $this->assertEquals(0, $outstanding);
    }
}
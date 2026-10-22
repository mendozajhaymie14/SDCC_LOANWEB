<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberNotificationIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function member_cannot_mark_another_members_notification_as_read(): void
    {
        // Create two members
        $memberA = User::factory()->create(['usertype' => 'member', 'email' => 'memberA@example.com']);
        $memberB = User::factory()->create(['usertype' => 'member', 'email' => 'memberB@example.com']);

        CoopMember::factory()->create(['user_id' => $memberA->id, 'share_capital' => 30000]);
        CoopMember::factory()->create(['user_id' => $memberB->id, 'share_capital' => 30000]);

        // Create a loan for member B
        $loanB = LoanApplication::factory()->create([
            'user_id' => $memberB->id,
            'full_name' => $memberB->name,
            'email' => $memberB->email,
            'loan_type' => 'regular',
            'amount' => 5000,
            'status' => 'approved',
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital' => 30000,
            'term_months' => 12,
        ]);

        // Create a notification for member B
        $notificationB = AppNotification::create([
            'user_id' => $memberB->id,
            'loan_application_id' => $loanB->id,
            'title' => 'Payment Due Soon',
            'message' => 'Your payment is due in 3 days.',
            'type' => 'due_soon',
        ]);

        // Member A tries to mark member B's notification as read
        $this->actingAs($memberA)
            ->post("/user/notifications/{$notificationB->id}/read")
            ->assertForbidden();

        // Notification should still be unread for member B
        $this->assertDatabaseHas('app_notifications', [
            'id' => $notificationB->id,
            'read_at' => null,
        ]);
    }

    /** @test */
    public function member_notification_list_only_contains_own_notifications(): void
    {
        // Create two members
        $memberA = User::factory()->create(['usertype' => 'member', 'email' => 'memberA@example.com']);
        $memberB = User::factory()->create(['usertype' => 'member', 'email' => 'memberB@example.com']);

        CoopMember::factory()->create(['user_id' => $memberA->id, 'share_capital' => 30000]);
        CoopMember::factory()->create(['user_id' => $memberB->id, 'share_capital' => 30000]);

        // Create a loan for each
        $loanA = LoanApplication::factory()->create([
            'user_id' => $memberA->id,
            'full_name' => $memberA->name,
            'email' => $memberA->email,
            'loan_type' => 'regular',
            'amount' => 5000,
            'status' => 'approved',
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital' => 30000,
            'term_months' => 12,
        ]);

        $loanB = LoanApplication::factory()->create([
            'user_id' => $memberB->id,
            'full_name' => $memberB->name,
            'email' => $memberB->email,
            'loan_type' => 'regular',
            'amount' => 5000,
            'status' => 'approved',
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital' => 30000,
            'term_months' => 12,
        ]);

        // Create notifications for both
        AppNotification::create([
            'user_id' => $memberA->id,
            'loan_application_id' => $loanA->id,
            'title' => 'Payment Due Soon',
            'message' => 'Your payment is due in 3 days.',
            'type' => 'due_soon',
        ]);

        AppNotification::create([
            'user_id' => $memberB->id,
            'loan_application_id' => $loanB->id,
            'title' => 'Payment Overdue',
            'message' => 'Your payment is overdue.',
            'type' => 'overdue',
        ]);

        // Member A views their notifications page
        $response = $this->actingAs($memberA)->get('/user/notifications');

        $response->assertOk();
        $response->assertSee('Payment Due Soon');
        $response->assertDontSee('Payment Overdue');

        // Member A's list should only contain their own
        $notifications = $response->viewData('notifications') ?? [];
        $this->assertCount(1, $notifications);
        $this->assertEquals($memberA->id, $notifications[0]->user_id);
    }
}
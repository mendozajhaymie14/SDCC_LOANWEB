<?php

namespace Tests\Feature;

use App\Models\AppNotificationLog;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationsLogTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(): User
    {
        return User::factory()->create([
            'usertype' => 'branch_manager',
            'email'    => 'staff@example.com',
        ]);
    }

    private function makeMember(): User
    {
        return User::factory()->create([
            'usertype' => 'member',
            'email'    => 'member@example.com',
        ]);
    }

    /** @test */
    public function branch_manager_can_view_notifications_log(): void
    {
        $this->actingAs($this->makeStaff())
            ->get(route('admin.notifications-log'))
            ->assertOk();
    }

    /** @test */
    public function member_cannot_view_notifications_log(): void
    {
        $this->actingAs($this->makeMember())
            ->get(route('admin.notifications-log'))
            ->assertForbidden();
    }

    /** @test */
    public function pagination_renders_admin_view_without_unsized_svg(): void
    {
        $this->actingAs($this->makeStaff());

        $this->seedNotificationLogs(47);

        $response = $this->get(route('admin.notifications-log'));

        $response->assertOk();

        // New admin pagination markup present
        $response->assertSee('nl-pagination');
        $response->assertSee('Showing 1 to 20 of 47');
        $response->assertSee('Previous');
        $response->assertSee('Next');

        // No default Tailwind pagination with unsized SVGs
        $response->assertDontSee('<svg');
    }

    /** @test */
    public function pagination_page_two_shows_middle_rows(): void
    {
        $this->actingAs($this->makeStaff());

        $this->seedNotificationLogs(47);

        $response = $this->get(route('admin.notifications-log', ['page' => 2]));

        $response->assertOk();
        $response->assertSee('Showing 21 to 40 of 47');
    }

    /** @test */
    public function pagination_last_page_shows_remaining_rows(): void
    {
        $this->actingAs($this->makeStaff());

        $this->seedNotificationLogs(47);

        $response = $this->get(route('admin.notifications-log', ['page' => 3]));

        $response->assertOk();
        $response->assertSee('Showing 41 to 47 of 47');
    }

    private function seedNotificationLogs(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            AppNotificationLog::create([
                'notification_type' => 'system',
                'channel'           => 'email',
                'status'            => 'Sent',
                'recipient_email'   => 'member@example.com',
                'subject'           => 'Test notification ' . $i,
                'sent_at'           => now(),
            ]);
        }
    }
}

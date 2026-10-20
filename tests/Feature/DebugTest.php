<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use App\Services\PaymentReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_reminder()
    {
        Mail::fake();

        $member = User::factory()->create([
            'usertype' => 'member',
            'email'    => 'member@example.com',
        ]);

        CoopMember::factory()->create([
            'user_id'         => $member->id,
            'share_capital'   => 30000,
            'is_migs'         => false,
        ]);

        $loan = LoanApplication::factory()->create([
            'user_id'         => $member->id,
            'full_name'       => 'Test Borrower',
            'email'           => $member->email,
            'loan_type'       => 'regular',
            'amount'          => 5000,
            'status'          => 'approved',
            'monthly_income'    => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'   => 30000,
            'term_months'     => 12,
            'remaining_balance' => 800,
            'next_due_date'   => now()->subDays(10)->toDateString(),
        ]);

        // Reload with user relationship
        $loaded = LoanApplication::with('user')->where('id', $loan->id)->first();

        dump("Loan user_id: " . $loaded->user_id);
        dump("Has user: " . ($loaded->user ? "yes - " . $loaded->user->email : "no"));

        $result = app(PaymentReminderService::class)->run();

        dump("Result: " . json_encode($result));

        // Check notifications
        $notifications = \App\Models\AppNotification::all();
        dump("Notifications count: " . $notifications->count());
        foreach ($notifications as $n) {
            dump("  Notification type: " . $n->type . ", loan_id: " . $n->loan_application_id);
        }

        // Check mail
        $sentMail = \Illuminate\Support\Facades\Mail::sent(\App\Mail\PaymentReminderMail::class);
        $queuedMail = \Illuminate\Support\Facades\Mail::queued(\App\Mail\PaymentReminderMail::class);
        dump("Sent instances: " . count($sentMail));
        dump("Queued instances: " . count($queuedMail));

        $this->assertTrue(true);
    }
}

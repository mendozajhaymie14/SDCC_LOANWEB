<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailDebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_simple_mail_send()
    {
        Mail::fake();

        $user = User::factory()->create([
            'usertype' => 'member',
            'email'    => 'member@example.com',
        ]);

        CoopMember::factory()->create([
            'user_id'         => $user->id,
            'share_capital'   => 30000,
            'is_migs'         => false,
        ]);

        $loan = LoanApplication::factory()->create([
            'user_id'             => $user->id,
            'full_name'           => 'Test Borrower',
            'email'               => $user->email,
            'loan_type'           => 'regular',
            'amount'              => 5000,
            'status'              => 'approved',
            'monthly_income'      => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'       => 30000,
            'term_months'         => 12,
            'remaining_balance'   => 800,
            'next_due_date'       => now()->subDays(10)->toDateString(),
        ]);

        $loanWithUser = LoanApplication::with('user')->find($loan->id);
        $email = $loanWithUser->user?->email;
        dump("Email: " . var_export($email, true));

        Mail::to($email)->send(new \App\Mail\PaymentReminderMail($loanWithUser, '', 'Test Subject', 'Test Message'));

        $sentMail = Mail::sent(\App\Mail\PaymentReminderMail::class);
        $queuedMail = Mail::queued(\App\Mail\PaymentReminderMail::class);
        dump("Mailables count: " . $sentMail->count());
        dump("Queued count: " . $queuedMail->count());

        Mail::assertSent(\App\Mail\PaymentReminderMail::class);
        $this->assertTrue(true);
    }
}
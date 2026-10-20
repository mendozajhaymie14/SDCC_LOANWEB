<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\User;
use App\Models\LoanApplication;
use App\Models\CoopMember;
use Illuminate\Support\Facades\Mail;

$user = User::factory()->create([
    'usertype' => 'member',
    'email'    => 'member@example.com',
]);

$member = CoopMember::factory()->create([
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

echo "Loan ID: {$loan->id}\n";
echo "Loan user_id: {$loan->user_id}\n";

$loanWithUser = LoanApplication::with('user')->find($loan->id);
echo "LoanWithUser user_id: {$loanWithUser->user_id}\n";
var_dump($loanWithUser->user);
if ($loanWithUser->user) {
    echo "User email: {$loanWithUser->user->email}\n";
} else {
    echo "User is NULL\n";
}

Mail::fake();
$email = $loanWithUser->user?->email;
echo "Email from loan->user?->email: " . var_export($email, true) . "\n";

if (!$email) {
    echo "No email, would return early\n";
} else {
    echo "Would send email to: {$email}\n";
    Mail::to($email)->send(new \App\Mail\PaymentReminderMail($loanWithUser, '', 'Test Subject', 'Test Message'));
    echo "Mailables count: " . Mail::sent()->count() . "\n";
    echo "Queued count: " . Mail::queued()->count() . "\n";
}
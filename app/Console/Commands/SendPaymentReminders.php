<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\LoanApplication;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendPaymentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send-due';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send upcoming and overdue payment reminders to members';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting payment reminder job...');

        $today = Carbon::today();
        $threeDaysFromNow = Carbon::today()->addDays(3);

        $this->line("Today: {$today->toDateString()}");
        $this->line("Due soon window: up to {$threeDaysFromNow->toDateString()}");

        // 1. Due in 3 days (due_soon)
        $dueSoonCount = $this->sendDueSoonReminders($today, $threeDaysFromNow);

        // 2. Due today (due_today)
        $dueTodayCount = $this->sendDueTodayReminders($today);

        // 3. Overdue (overdue) - daily until paid
        $overdueCount = $this->sendOverdueReminders($today);

        $this->newLine();
        $this->info("Reminder job completed:");
        $this->line("  Due soon (3 days):    {$dueSoonCount}");
        $this->line("  Due today:            {$dueTodayCount}");
        $this->line("  Overdue:              {$overdueCount}");
        $this->line("  Total sent:           " . ($dueSoonCount + $dueTodayCount + $overdueCount));

        return self::SUCCESS;
    }

    /**
     * Send reminders for payments due in 3 days (excluding today).
     */
    protected function sendDueSoonReminders(Carbon $today, Carbon $threeDaysFromNow): int
    {
        $loans = LoanApplication::query()
            ->where('status', 'approved')
            ->whereNotNull('next_due_date')
            ->whereBetween('next_due_date', [$today->copy()->addDay(), $threeDaysFromNow])
            ->where('remaining_balance', '>', 0)
            ->get();

        $sent = 0;
        foreach ($loans as $loan) {
            if ($this->alreadySentToday($loan->id, AppNotification::TYPE_DUE_SOON)) {
                continue;
            }

            $this->createNotification($loan, AppNotification::TYPE_DUE_SOON, [
                'title'   => 'Upcoming Payment Due Soon',
                'message' => "Your payment of ₱" . number_format($loan->monthly_payment, 2) .
                    " is due on {$loan->next_due_date->format('M d, Y')} (in 3 days). " .
                    "Please ensure sufficient funds are available.",
            ]);

            $sent++;
        }

        return $sent;
    }

    /**
     * Send reminders for payments due today.
     */
    protected function sendDueTodayReminders(Carbon $today): int
    {
        $loans = LoanApplication::query()
            ->where('status', 'approved')
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', $today)
            ->where('remaining_balance', '>', 0)
            ->get();

        $sent = 0;
        foreach ($loans as $loan) {
            if ($this->alreadySentToday($loan->id, AppNotification::TYPE_DUE_TODAY)) {
                continue;
            }

            $this->createNotification($loan, AppNotification::TYPE_DUE_TODAY, [
                'title'   => 'Payment Due Today',
                'message' => "Your payment of ₱" . number_format($loan->monthly_payment, 2) .
                    " is due today ({$today->format('M d, Y')}). " .
                    "Please process your payment to avoid late fees.",
            ]);

            $sent++;
        }

        return $sent;
    }

    /**
     * Send daily reminders for overdue payments.
     */
    protected function sendOverdueReminders(Carbon $today): int
    {
        $loans = LoanApplication::query()
            ->where('status', 'approved')
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '<', $today)
            ->where('remaining_balance', '>', 0)
            ->get();

        $sent = 0;
        foreach ($loans as $loan) {
            if ($this->alreadySentToday($loan->id, AppNotification::TYPE_OVERDUE)) {
                continue;
            }

            $daysOverdue = $loan->next_due_date->diffInDays($today);
            $this->createNotification($loan, AppNotification::TYPE_OVERDUE, [
                'title'   => 'Overdue Payment Reminder',
                'message' => "Your payment of ₱" . number_format($loan->monthly_payment, 2) .
                    " was due {$daysOverdue} day(s) ago ({$loan->next_due_date->format('M d, Y')}). " .
                    "Outstanding balance: ₱" . number_format($loan->remaining_balance, 2) .
                    ". Please remit payment immediately to avoid further penalties.",
            ]);

            $sent++;
        }

        return $sent;
    }

    /**
     * Check if a notification of the given type was already sent today for this loan.
     */
    protected function alreadySentToday(int $loanId, string $type): bool
    {
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();

        return AppNotification::query()
            ->where('loan_application_id', $loanId)
            ->where('type', $type)
            ->whereBetween('sent_at', [$todayStart, $todayEnd])
            ->exists();
    }

    /**
     * Create a payment reminder notification for the loan's user.
     */
    protected function createNotification(LoanApplication $loan, string $type, array $content): void
    {
        DB::transaction(function () use ($loan, $type, $content) {
            AppNotification::create([
                'user_id'            => $loan->user_id,
                'loan_application_id' => $loan->id,
                'title'              => $content['title'],
                'message'            => $content['message'],
                'type'               => $type,
                'is_read'            => false,
                'sent_at'            => now(),
            ]);
        });
    }
}
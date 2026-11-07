<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\AppNotificationLog;
use App\Models\LoanApplication;
use App\Mail\PaymentReminderMail;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidTimeZoneException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Shared logic for sending payment reminders.
 * Used by both the Artisan command (reminders:send-due) and the admin HTTP endpoint.
 */
class PaymentReminderService
{
    /** How many days ahead to send "due soon" reminders. */
    public const DUE_SOON_WINDOW_DAYS = 3;

    /**
     * Run the full reminder cycle: due soon, due today, overdue.
     *
     * @return array{sent: int, skipped: int, errors: int, details: array<string, int>}
     */
    public function run(): array
    {
        // Use Asia/Manila timezone for date comparison so due dates are
        // compared against the local business day, not UTC.
        try {
            $today = Carbon::today(new \DateTimeZone('Asia/Manila'));
        } catch (\Throwable $e) {
            $today = Carbon::today();
        }
        $details     = [];
        $totals      = ['sent' => 0, 'skipped' => 0, 'errors' => 0];

        // 1. Due soon (within 3 days, excluding today)
        $details['due_soon'] = $this->sendBatch(
            $today,
            AppNotification::TYPE_DUE_SOON,
            'Upcoming Payment Due Soon',
            fn(LoanApplication $loan) => $this->buildMessage($loan, 'template_due_soon',
                "Your payment of " . \App\Support\Format::money((float) $loan->monthly_payment)
                    . $this->dueSoonText($loan, $today) . " Please ensure sufficient funds are available."),
            $totals
        );

        // 2. Due today
        $details['due_today'] = $this->sendBatch(
            $today,
            AppNotification::TYPE_DUE_TODAY,
            'Payment Due Today',
            fn(LoanApplication $loan) => $this->buildMessage($loan, 'template_due_today', "Your payment of " . \App\Support\Format::money((float) $loan->monthly_payment)
                . " is due today ({$today->format('M d, Y')}). "
                . "Please process your payment to avoid late fees."),
            $totals
        );

        // 3. Overdue (daily until paid)
        $details['overdue'] = $this->sendBatch(
            $today,
            AppNotification::TYPE_OVERDUE,
            'Overdue Payment Reminder',
            fn(LoanApplication $loan) => $this->buildMessage($loan, 'template_overdue',
                "Your payment of " . \App\Support\Format::money((float) $loan->monthly_payment)
                    . $this->overdueText($loan, $today)
                    . " Outstanding balance: " . \App\Support\Format::money((float) $loan->remaining_balance)
                    . ". Please remit payment immediately to avoid further penalties."),
            $totals
        );

        return ['sent' => $totals['sent'], 'skipped' => $totals['skipped'], 'errors' => $totals['errors'], 'details' => $details];
    }

    /**
     * Build the notification message using the Setting template if available,
     * falling back to the default message.
     */
    private function buildMessage(LoanApplication $loan, string $templateKey, string $fallback): string
    {
        $template = Setting::get($templateKey, '');
        if ($template !== null && $template !== '') {
            return Setting::render($templateKey, $fallback, [
                'member_name' => $loan->user?->name ?? 'Borrower',
                'amount'      => \App\Support\Format::money((float) $loan->monthly_payment),
                'due_date'    => $loan->next_due_date?->format('M d, Y') ?? 'N/A',
                'balance'     => \App\Support\Format::money((float) $loan->remaining_balance),
            ]);
        }
        return $fallback;
    }

    /**
     * Find eligible loans and send reminders for one type.
     *
     * @param  callable(LoanApplication): string  $messageBuilder
     * @param  array{sent: int, skipped: int, errors: int}  $totals
     * @return int  count of reminders sent for this type
     */
    private function sendBatch(
        Carbon $today,
        string $type,
        string $title,
        callable $messageBuilder,
        array &$totals
    ): int {
        $query = LoanApplication::query()
            ->whereIn('status', ['approved'])
            ->whereNotNull('next_due_date')
            ->where('remaining_balance', '>', 0)
            ->with('user');

        if ($type === AppNotification::TYPE_DUE_SOON) {
            // Due soon: within the next N days (excluding today), N from config
            $windowDays = (int) config('loan_matrix.reminders.due_soon_window_days', self::DUE_SOON_WINDOW_DAYS);
            $query->whereDate('next_due_date', '>', $today)
                ->whereDate('next_due_date', '<=', $today->copy()->addDays($windowDays));
        } elseif ($type === AppNotification::TYPE_DUE_TODAY) {
            // Due today: exactly today
            $query->whereDate('next_due_date', '=', $today);
        } else {
            // Overdue: before today
            $query->whereDate('next_due_date', '<', $today);
        }

        $loans = $query->get();
        $sent  = 0;

        foreach ($loans as $loan) {
            // Duplicate check: already sent today for this type?
            if ($this->alreadySentToday($loan->id, $type)) {
                $totals['skipped']++;
                continue;
            }

            try {
                $message = $messageBuilder($loan);

                // In-app notification: skip if user_id is null (staff-encoded loans)
                if ($loan->user_id) {
                    AppNotification::create([
                        'user_id'             => $loan->user_id,
                        'loan_application_id' => $loan->id,
                        'title'               => $title,
                        'message'             => $message,
                        'type'                => $type,
                        'is_read'             => false,
                        'sent_at'             => now(),
                    ]);
                }

                // Send email; check the return value to avoid false "Sent" log
                $emailSent = $this->sendEmail($loan, $title, $message);

                if ($emailSent) {
                    // Only log Sent after a successful send attempt
                    // Use the same fallback chain as sendEmail() so the logged
                    // recipient_email matches the actual email that was sent.
                    $logEmail = $loan->user?->coopMember?->email
                        ?? $loan->user?->email
                        ?? $loan->email
                        ?? config('mail.from.address');
                    AppNotificationLog::log($logEmail, $title, $type, 'Sent', 'email', $loan->user_id, $loan->id);

                    $sent++;
                    $totals['sent']++;
                } else {
                    // Email was not sent because there's no email on file — log as Skipped
                    $skipEmail = $loan->user?->coopMember?->email
                        ?? $loan->user?->email
                        ?? $loan->email
                        ?? config('mail.from.address');

                    AppNotificationLog::log($skipEmail, $title, $type, 'Skipped', 'email', $loan->user_id, $loan->id);

                    $totals['skipped']++;
                }

            } catch (\Throwable $e) {
                Log::error('Payment reminder failed for loan', [
                    'loan_id' => $loan->id,
                    'type'    => $type,
                    'error'   => $e->getMessage(),
                ]);

                // Log email failure with the error message
                // Use the same fallback chain for the error log
                $email = $loan->user?->coopMember?->email
                    ?? $loan->user?->email
                    ?? $loan->email;
                $errorEmail = $email ?? config('mail.from.address');

                AppNotificationLog::logFailed($errorEmail, $title, $type, $e->getMessage(), 'email', $loan->user_id, $loan->id);

                $totals['errors']++;
            }
        }

        return $sent;
    }

    /**
     * Check if a notification of this type was already sent today for this loan.
     */
    private function alreadySentToday(int $loanId, string $type): bool
    {
        try {
            $tz = new \DateTimeZone('Asia/Manila');
            $start = Carbon::today($tz)->startOfDay();
            $end   = Carbon::today($tz)->endOfDay();
        } catch (\Throwable $e) {
            $start = Carbon::today()->startOfDay();
            $end   = Carbon::today()->endOfDay();
        }

        return AppNotification::query()
            ->where('loan_application_id', $loanId)
            ->where('type', $type)
            ->whereBetween('sent_at', [$start, $end])
            ->exists();
    }

    /**
     * Whole calendar days from $today to the loan's due date.
     * Positive = due in the future, 0 = today, negative = overdue.
     *
     * The due date is rebuilt as a plain calendar day in the same timezone as
     * $today. Converting a UTC midnight into Manila time shifts it by 8 hours,
     * which turned 3 overdue days into "2" and 2 days ahead into "2.33".
     */
    private function daysFromToday(LoanApplication $loan, Carbon $today): int
    {
        $dueDay = Carbon::parse($loan->next_due_date->format('Y-m-d'), $today->getTimezone())->startOfDay();

        return (int) round($today->copy()->startOfDay()->diffInDays($dueDay, false));
    }

    /**
     * Build whole-day phrasing for the "due soon" category.
     * Never prints a negative or fractional number.
     */
    private function dueSoonText(LoanApplication $loan, Carbon $today): string
    {
        $date = $loan->next_due_date->format('M d, Y');
        $days = $this->daysFromToday($loan, $today);

        if ($days <= 0) {
            return " is due today ({$date}).";
        }
        if ($days === 1) {
            return " is due tomorrow ({$date}).";
        }
        return " is due in {$days} days ({$date}).";
    }

    /**
     * Build whole-day phrasing for the "overdue" category.
     */
    private function overdueText(LoanApplication $loan, Carbon $today): string
    {
        $date = $loan->next_due_date->format('M d, Y');
        $daysOverdue = max(1, abs($this->daysFromToday($loan, $today)));
        $label = $daysOverdue === 1 ? '1 day ago' : "{$daysOverdue} days ago";

        return " was due on {$date} ({$label}).";
    }

    /**
     * Send the reminder email to the borrower.
     * Returns true when dispatched, false when no email address available.
     * The caller must NOT log "Sent" unconditionally — check the return value.
     */
    private function sendEmail(LoanApplication $loan, string $title, string $message): bool
    {
        // Fallback chain: coop member email → user email → loan-stored email → no email
        $email = $loan->user?->coopMember?->email
            ?? $loan->user?->email
            ?? $loan->email
            ?? null;

        if (!$email) {
            Log::warning('Payment reminder email skipped — no email on file for loan', [
                'loan_id' => $loan->id,
            ]);
            return false;
        }

        Mail::to($email)->send(new PaymentReminderMail($loan, '', $title, $message));
        return true;
    }
}
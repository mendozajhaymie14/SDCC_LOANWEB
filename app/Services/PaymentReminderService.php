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
            fn(LoanApplication $loan) => $this->buildMessage($loan, 'template_due_soon', "Your payment of " . \App\Support\Format::money((float) $loan->monthly_payment)
                . " is due on {$loan->next_due_date->format('M d, Y')} ({$loan->next_due_date->diffInDays($today)} day(s) from today). "
                . "Please ensure sufficient funds are available."),
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
            fn(LoanApplication $loan) => $this->buildMessage($loan, 'template_overdue', "Your payment of " . \App\Support\Format::money((float) $loan->monthly_payment)
                . " was due " . intval(abs($loan->next_due_date->diffInDays($today))) . " day(s) ago ({$loan->next_due_date->format('M d, Y')}). "
                . "Outstanding balance: " . \App\Support\Format::money((float) $loan->remaining_balance)
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

                // In-app notification
                AppNotification::create([
                    'user_id'             => $loan->user_id,
                    'loan_application_id' => $loan->id,
                    'title'               => $title,
                    'message'             => $message,
                    'type'                => $type,
                    'is_read'             => false,
                    'sent_at'             => now(),
                ]);

                // Send email
                $this->sendEmail($loan, $title, $message);

                // Only log Sent AFTER a successful send attempt
                AppNotificationLog::log((string) ($loan->user?->email ?? config('mail.from.address')), $title, $type, 'Sent', 'email', $loan->user_id, $loan->id);

                $sent++;
                $totals['sent']++;

            } catch (\Throwable $e) {
                Log::error('Payment reminder failed for loan', [
                    'loan_id' => $loan->id,
                    'type'    => $type,
                    'error'   => $e->getMessage(),
                ]);

                // Log email failure with the error message
                AppNotificationLog::logFailed((string) ($loan->user?->email ?? config('mail.from.address')), $title, $type, $e->getMessage(), 'email', $loan->user_id, $loan->id);

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
     * Send the reminder email to the borrower.
     * Failures are caught and logged by the caller.
     */
    private function sendEmail(LoanApplication $loan, string $title, string $message): void
    {
        $email = $loan->user?->email;
        if (!$email) {
            Log::warning('Payment reminder email skipped — borrower has no email', [
                'loan_id' => $loan->id,
            ]);
            return;
        }

        Mail::to($email)->send(new PaymentReminderMail($loan, '', $title, $message));
    }
}

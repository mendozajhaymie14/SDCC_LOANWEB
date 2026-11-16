<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Mail\PaymentReceivedMail;
use App\Models\AppNotification;
use App\Models\AppNotificationLog;
use Illuminate\Support\Facades\Mail;

class NotifyMemberOfPayment
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PaymentReceived $event): void
    {
        $payment = $event->payment;
        $loan = $payment->loanApplication;

        $userId = $loan->user_id;

        // Create in-app notification ONLY when the loan has a portal user
        if ($userId) {
            AppNotification::create([
                'user_id'            => $userId,
                'loan_application_id' => $loan->id,
                'title'              => 'Payment Received',
                'message'            => "Your payment of " . \App\Support\Format::money($payment->amount_paid) .
                    " for loan {$loan->reference} has been recorded. " .
                    "Remaining balance: " . \App\Support\Format::money($payment->remaining_balance_after) . ".",
                'type'               => AppNotification::TYPE_PAYMENT_REC,
                'is_read'            => false,
            ]);
        }

        // Send email notification — resolve recipient using the same priority as the approve flow
        $email = $loan->getRecipientEmail();

        if ($email) {
            try {
                Mail::to($email)->send(new PaymentReceivedMail($loan));
                AppNotificationLog::log(
                    $email, 'Payment Received', 'payment_received', 'Sent',
                    'email', $userId, $loan->id
                );
            } catch (\Throwable $e) {
                AppNotificationLog::logFailed(
                    $email, 'Payment Received', 'payment_received', $e->getMessage(),
                    'email', $userId, $loan->id
                );
            }
        } else {
            // No resolvable email on file — log as Skipped (never silent)
            AppNotificationLog::log(
                null, 'Payment Received', 'payment_received', 'Skipped',
                'email', $userId, $loan->id, 'no email on file'
            );
        }
    }
}
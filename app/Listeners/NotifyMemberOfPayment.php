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

        // Create in-app notification
        AppNotification::create([
            'user_id'            => $loan->user_id,
            'loan_application_id' => $loan->id,
            'title'              => 'Payment Received',
            'message'            => "Your payment of " . \App\Support\Format::money($payment->amount_paid) .
                " for loan {$loan->reference} has been recorded. " .
                "Remaining balance: " . \App\Support\Format::money($payment->remaining_balance_after) . ".",
            'type'               => AppNotification::TYPE_PAYMENT_REC,
            'is_read'            => false,
        ]);

        // Send email notification
        if ($loan->user?->email) {
            try {
                Mail::to($loan->user->email)->send(new PaymentReceivedMail($loan));
                AppNotificationLog::log(
                $loan->user->email, 'Payment Received', 'payment_received', 'Sent',
                'email', $loan->user_id, $loan->id
            );
            } catch (\Throwable $e) {
                AppNotificationLog::logFailed(
                    $loan->user->email, 'Payment Received', 'payment_received', $e->getMessage(),
                    'email', $loan->user_id, $loan->id
                );
            }
        }
    }
}
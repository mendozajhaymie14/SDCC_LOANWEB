<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Models\AppNotification;

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

        AppNotification::create([
            'user_id'            => $loan->user_id,
            'loan_application_id' => $loan->id,
            'title'              => 'Payment Received',
            'message'            => "Your payment of ₱" . number_format($payment->amount_paid, 2) .
                " for loan {$loan->reference} has been recorded. " .
                "Remaining balance: ₱" . number_format($payment->remaining_balance_after, 2) . ".",
            'type'               => AppNotification::TYPE_PAYMENT_REC,
            'is_read'            => false,
        ]);
    }
}
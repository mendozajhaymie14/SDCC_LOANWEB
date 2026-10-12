<?php

namespace App\Listeners;

use App\Events\LoanApplicationDisbursed;
use App\Models\AppNotification;

class NotifyMemberOfDisbursement
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
    public function handle(LoanApplicationDisbursed $event): void
    {
        $loan = $event->loanApplication;

        AppNotification::create([
            'user_id'            => $loan->user_id,
            'loan_application_id' => $loan->id,
            'title'              => 'Payout Disbursed',
            'message'            => "Your loan {$loan->reference} has been disbursed. ₱" . number_format($loan->amount, 2) . " has been released to you.",
            'type'               => AppNotification::TYPE_PAYMENT_REC,
            'is_read'            => false,
        ]);
    }
}
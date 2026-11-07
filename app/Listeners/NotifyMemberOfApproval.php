<?php

namespace App\Listeners;

use App\Events\LoanApplicationApproved;
use App\Mail\LoanApprovedMail;
use App\Models\AppNotification;
use App\Models\AppNotificationLog;
use Illuminate\Support\Facades\Mail;

class NotifyMemberOfApproval
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
    public function handle(LoanApplicationApproved $event): void
    {
        $loan = $event->loanApplication;

        AppNotification::create([
            'user_id'            => $loan->user_id,
            'loan_application_id' => $loan->id,
            'title'              => 'Loan Approved',
            'message'            => "Your loan {$loan->reference} has been approved for " . \App\Support\Format::money($loan->amount),
            'type'               => AppNotification::TYPE_APPROVED,
            'is_read'            => false,
        ]);

        // Note: email and email log are handled by the AdminController directly
        // to avoid duplicate notifications. See Issue E.
    }
}
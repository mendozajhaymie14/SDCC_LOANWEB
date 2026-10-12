<?php

namespace App\Listeners;

use App\Events\LoanApplicationApproved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\AppNotification;

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
            'message'            => "Your loan {$loan->reference} has been approved for ₱" . number_format($loan->amount, 2),
            'type'               => AppNotification::TYPE_APPROVED,
            'is_read'            => false,
        ]);
    }
}
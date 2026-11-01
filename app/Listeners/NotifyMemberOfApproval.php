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

        // Send email notification to borrower
        if ($loan->user?->email) {
            try {
                Mail::to($loan->user->email)->send(new LoanApprovedMail($loan));
                AppNotificationLog::log(
                $loan->user->email, 'Your Loan Application Has Been Approved', 'approved', 'Sent',
                'email', $loan->user_id, $loan->id
            );
            } catch (\Throwable $e) {
                AppNotificationLog::logFailed(
                    $loan->user->email, 'Your Loan Application Has Been Approved', 'approved', $e->getMessage(),
                    'email', $loan->user_id, $loan->id
                );
            }
        }
    }
}
<?php

namespace App\Notifications;

use App\Models\LoanApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LoanStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public LoanApplication $application,
        public string $status
    ) {}

    /**
     * Safely populate notification data without throwing on null / undefined
     * relations (application may not be linked to a user account).
     */
    public function toArray($notifiable): array
    {
        return [
            'applicant_name' => $this->application->user->name ?? $this->application->borrower_name ?? 'Loan Member',
            'application_number' => $this->application->appId(),
            'loan_type' => $this->application->loan_type ?? 'Loan',
            'amount' => $this->application->amount ?? 0,
            'status' => $this->status ?? $this->application->status,
            'message' => 'Your loan application #' . ($this->application->appId()) . ' has been ' . ($this->status ?? 'updated') . '.',
        ];
    }
}

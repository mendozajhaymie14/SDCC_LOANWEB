<?php

namespace App\Mail;

use App\Models\LoanApplication;
use App\Models\Setting;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoanApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public LoanApplication $loan)
    {
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        $template = Setting::get('template_approval', '');
        $coopName = Setting::get('cooperative_name', config('cooperative.name', 'San Dionisio Credit Cooperative'));
        $loanApp = $this->loan;

        // Build context for template rendering
        $context = [
            'loan'              => $loanApp,
            'member_name'       => $loanApp->user?->name ?? $loanApp->full_name ?? 'Borrower',
            'amount'            => Format::money($loanApp->amount),
            'approved_amount'   => Format::money($loanApp->approved_amount ?? $loanApp->amount),
            'term_months'       => $loanApp->term_months ?? 12,
            'interest_rate'     => Setting::get('annual_interest_rate', config('loan_matrix.rate', 12)) . '%',
            'reference'         => $loanApp->reference ?? 'N/A',
            'coop_name'         => $coopName,
        ];

        return $this
            ->from(config('mail.from.address', 'hello@example.com'), $coopName)
            ->to($this->loan->user?->email ?? config('mail.from.address', 'hello@example.com'))
            ->subject('Your Loan Application Has Been Approved')
            ->markdown('emails.loan-approved', array_merge($context, ['template' => $template]));
    }
}
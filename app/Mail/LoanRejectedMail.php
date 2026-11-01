<?php

namespace App\Mail;

use App\Models\LoanApplication;
use App\Models\Setting;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoanRejectedMail extends Mailable
{
    public ?string $reason = null;

    /**
     * Create a new message instance.
     */
    public function __construct(public LoanApplication $loan, ?string $reason = null)
    {
        $this->reason = $reason;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        $template = Setting::get('template_rejection', '');
        $coopName = Setting::get('cooperative_name', config('cooperative.name', 'San Dionisio Credit Cooperative'));
        $loanApp = $this->loan;

        $context = [
            'loan'          => $loanApp,
            'member_name'   => $loanApp->user?->name ?? $loanApp->full_name ?? 'Borrower',
            'reason'        => $this->reason ?? 'N/A',
            'amount'        => Format::money($loanApp->amount),
            'reference'     => $loanApp->reference ?? 'N/A',
            'coop_name'     => $coopName,
        ];

        return $this
            ->from(config('mail.from.address', 'hello@example.com'), $coopName)
            ->to($this->loan->user?->email ?? config('mail.from.address', 'hello@example.com'))
            ->subject('Your Loan Application Update')
            ->markdown('emails.loan-rejected', array_merge($context, ['template' => $template]));
    }
}
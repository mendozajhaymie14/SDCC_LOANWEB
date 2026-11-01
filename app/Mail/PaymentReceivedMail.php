<?php

namespace App\Mail;

use App\Models\LoanApplication;
use App\Models\Setting;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentReceivedMail extends Mailable
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
        $coopName = Setting::get('cooperative_name', config('cooperative.name', 'San Dionisio Credit Cooperative'));
        $loanApp = $this->loan;

        $context = [
            'loan'              => $loanApp,
            'member_name'       => $loanApp->user?->name ?? $loanApp->full_name ?? 'Borrower',
            'amount'            => Format::money($loanApp->amount),
            'payment_amount'    => Format::money($loanApp->last_payment_amount ?? 0),
            'remaining_balance' => Format::money($loanApp->remaining_balance ?? 0),
            'due_date'          => $loanApp->next_due_date?->format('M d, Y') ?? 'N/A',
            'reference'         => $loanApp->reference ?? 'N/A',
            'coop_name'         => $coopName,
        ];

        return $this
            ->from(config('mail.from.address', 'hello@example.com'), $coopName)
            ->to($this->loan->user?->email ?? config('mail.from.address', 'hello@example.com'))
            ->subject('Payment Received - Thank You')
            ->markdown('emails.payment-received', $context);
    }
}
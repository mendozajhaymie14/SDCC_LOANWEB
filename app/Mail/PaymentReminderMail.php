<?php

namespace App\Mail;

use App\Models\LoanApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public LoanApplication $loan;
    public string $type;
    public string $title;
    public string $message;

    /**
     * Create a new message instance.
     */
    public function __construct(LoanApplication $loan, string $type, string $title, string $message)
    {
        $this->loan = $loan;
        $this->type = $type;
        $this->title = $title;
        $this->message = $message;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this
            ->from(config('mail.from.address', 'hello@example.com'), config('mail.from.name', 'SDCC'))
            ->to($this->loan->user?->email ?? config('mail.from.address', 'hello@example.com'))
            ->subject($this->title)
            ->markdown('emails.payment-reminder', [
                'loan'  => $this->loan,
                'type'  => $this->type,
                'title' => $this->title,
                'body'  => $this->message,
            ]);
    }
}

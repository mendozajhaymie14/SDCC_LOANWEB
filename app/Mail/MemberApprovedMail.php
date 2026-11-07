<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a membership application is approved. Tells the applicant their
 * SDCC Member ID and what to do next.
 */
class MemberApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;
    public string $memberId;
    public bool $hasAccount;

    public function __construct(string $name, string $memberId, bool $hasAccount = false)
    {
        $this->name = $name;
        $this->memberId = $memberId;
        $this->hasAccount = $hasAccount;
    }

    public function build(): self
    {
        return $this
            ->from(config('mail.from.address', 'hello@example.com'), config('mail.from.name', 'SDCC'))
            ->subject('Your SDCC membership is approved - here is your Member ID')
            ->markdown('emails.member-approved', [
                'name'       => $this->name,
                'memberId'   => $this->memberId,
                'hasAccount' => $this->hasAccount,
                'registerUrl' => url('/register'),
                'loginUrl'    => url('/login'),
            ]);
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a membership application is rejected. Tells the applicant the
 * rejection reason (staff names never appear).
 */
class MemberRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;
    public string $reason;

    public function __construct(string $name, string $reason)
    {
        $this->name  = $name;
        $this->reason = $reason;
    }

    public function build(): self
    {
        return $this
            ->from(config('mail.from.address', 'hello@example.com'), config('mail.from.name', 'SDCC'))
            ->subject('Your SDCC membership application was not approved')
            ->markdown('emails.member-rejected', [
                'name'   => $this->name,
                'reason' => $this->reason,
                'applyUrl' => url('/member-application'),
            ]);
    }
}

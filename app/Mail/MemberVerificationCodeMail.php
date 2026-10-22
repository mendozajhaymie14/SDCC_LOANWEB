<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The six-digit code that proves a member controls the e-mail address on
 * file for their SDCC member ID.
 */
class MemberVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $code;
    public string $memberId;

    public function __construct(string $code, string $memberId)
    {
        $this->code = $code;
        $this->memberId = $memberId;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this
            ->from(config('mail.from.address', 'hello@example.com'), config('mail.from.name', 'SDCC'))
            ->subject('Your San Dionisio Credit Cooperative verification code')
            ->markdown('emails.member-verification-code', [
                'code'     => $this->code,
                'memberId' => $this->memberId,
            ]);
    }
}

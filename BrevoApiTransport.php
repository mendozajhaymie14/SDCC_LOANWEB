<?php

namespace App\Mail;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends mail through Brevo's HTTPS API (https://api.brevo.com/v3/smtp/email).
 *
 * Needed on hosts that block outgoing SMTP (e.g. InfinityFree free hosting).
 * Uses only Laravel's HTTP client, so no extra Composer package is required.
 */
class BrevoApiTransport extends AbstractTransport
{
    public function __construct(private string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $from = $email->getFrom();
        if (empty($from)) {
            throw new TransportException('Brevo: the message has no From address.');
        }

        $payload = [
            'sender'  => $this->address($from[0]),
            'to'      => array_map(fn (Address $a) => $this->address($a), $email->getTo()),
            'subject' => (string) $email->getSubject(),
        ];

        $html = $email->getHtmlBody();
        $text = $email->getTextBody();

        if (is_string($html) && $html !== '') {
            $payload['htmlContent'] = $html;
        }
        if (is_string($text) && $text !== '') {
            $payload['textContent'] = $text;
        }
        if (! isset($payload['htmlContent']) && ! isset($payload['textContent'])) {
            $payload['textContent'] = ' ';
        }

        $cc = $email->getCc();
        if ($cc) {
            $payload['cc'] = array_map(fn (Address $a) => $this->address($a), $cc);
        }
        $bcc = $email->getBcc();
        if ($bcc) {
            $payload['bcc'] = array_map(fn (Address $a) => $this->address($a), $bcc);
        }
        $replyTo = $email->getReplyTo();
        if ($replyTo) {
            $payload['replyTo'] = $this->address($replyTo[0]);
        }

        $response = Http::withHeaders([
                'api-key' => $this->apiKey,
                'accept'  => 'application/json',
            ])
            ->timeout(20)
            ->post('https://api.brevo.com/v3/smtp/email', $payload);

        if ($response->failed()) {
            throw new TransportException(
                'Brevo API error ' . $response->status() . ': ' . $response->body()
            );
        }
    }

    private function address(Address $address): array
    {
        $data = ['email' => $address->getAddress()];
        if ($address->getName() !== '') {
            $data['name'] = $address->getName();
        }

        return $data;
    }

    public function __toString(): string
    {
        return 'brevo-api';
    }
}

<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends through Mailtrap's HTTP sending API instead of SMTP.
 *
 * Mailtrap's production stream accepts SMTP handoffs with a 250 but does not
 * always deliver them; the API returns a real per-message verdict, so failures
 * surface here as exceptions rather than silently disappearing after queueing.
 */
class MailtrapApiTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://send.api.mailtrap.io/api/send';

    public function __construct(private readonly string $token)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email    = MessageConverter::toEmail($message->getOriginalMessage());
        $envelope = $message->getEnvelope();

        $payload = array_filter([
            'from'     => $this->address($this->sender($email, $envelope)),
            'to'       => $this->addresses($email->getTo()),
            'cc'       => $this->addresses($email->getCc()),
            'bcc'      => $this->addresses($email->getBcc()),
            'reply_to' => ($r = $email->getReplyTo()) ? $this->address($r[0]) : null,
            'subject'  => $email->getSubject(),
            'text'     => $email->getTextBody(),
            'html'     => $email->getHtmlBody(),
        ], fn ($v) => $v !== null && $v !== [] && $v !== '');

        if ($attachments = $this->attachments($email)) {
            $payload['attachments'] = $attachments;
        }

        $response = Http::withHeaders([
            'Api-Token'    => $this->token,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post(self::ENDPOINT, $payload);

        if ($response->failed() || $response->json('success') !== true) {
            // Surface Mailtrap's own reason (unverified domain, quota, ban…)
            // instead of letting the send look like it succeeded.
            $errors = $response->json('errors');
            throw new TransportException(sprintf(
                'Mailtrap API rejected the message (HTTP %d): %s',
                $response->status(),
                is_array($errors) ? implode('; ', $errors) : $response->body()
            ));
        }

        $ids = $response->json('message_ids');
        if (is_array($ids) && $ids !== []) {
            $message->setMessageId($ids[0]);
        }
    }

    private function sender(Email $email, Envelope $envelope): Address
    {
        return $email->getFrom()[0] ?? $envelope->getSender();
    }

    /** @param Address[] $addresses */
    private function addresses(array $addresses): array
    {
        return array_map(fn (Address $a) => $this->address($a), $addresses);
    }

    private function address(Address $address): array
    {
        return array_filter([
            'email' => $address->getAddress(),
            'name'  => $address->getName() ?: null,
        ]);
    }

    private function attachments(Email $email): array
    {
        $out = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $out[]   = array_filter([
                'content'     => base64_encode($attachment->getBody()),
                'filename'    => $headers->getHeaderParameter('content-disposition', 'filename'),
                'type'        => $headers->get('content-type')?->getBody(),
                'disposition' => $headers->getHeaderBody('content-disposition') ?: 'attachment',
            ]);
        }

        return $out;
    }

    public function __toString(): string
    {
        return 'mailtrap+api://send.api.mailtrap.io';
    }
}

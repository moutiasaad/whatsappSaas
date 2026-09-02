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
 * Sends through Brevo's HTTP API.
 *
 * This host blocks outbound SMTP on 25/587/465, so SMTP-based providers are
 * unreachable regardless of credentials — only HTTPS gets out. Brevo also
 * verifies senders by email confirmation rather than DNS, so it can deliver
 * without touching the domain's records.
 */
class BrevoApiTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(private readonly string $key)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $payload = array_filter([
            'sender'      => $this->address($this->sender($email, $message->getEnvelope())),
            'to'          => $this->addresses($email->getTo()),
            'cc'          => $this->addresses($email->getCc()),
            'bcc'         => $this->addresses($email->getBcc()),
            'replyTo'     => ($r = $email->getReplyTo()) ? $this->address($r[0]) : null,
            'subject'     => $email->getSubject(),
            'textContent' => $email->getTextBody(),
            'htmlContent' => $email->getHtmlBody(),
        ], fn ($v) => $v !== null && $v !== [] && $v !== '');

        if ($attachments = $this->attachments($email)) {
            $payload['attachment'] = $attachments;
        }

        $response = Http::withHeaders([
            'api-key'      => $this->key,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ])->timeout(30)->post(self::ENDPOINT, $payload);

        if ($response->failed()) {
            // Surface Brevo's reason (unverified sender, quota, bad key) rather
            // than letting a rejected send look successful.
            throw new TransportException(sprintf(
                'Brevo API rejected the message (HTTP %d): %s',
                $response->status(),
                $response->json('message') ?? $response->body()
            ));
        }

        if ($id = $response->json('messageId')) {
            $message->setMessageId(is_array($id) ? ($id[0] ?? '') : $id);
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
                'content' => base64_encode($attachment->getBody()),
                'name'    => $headers->getHeaderParameter('content-disposition', 'filename'),
            ]);
        }

        return $out;
    }

    public function __toString(): string
    {
        return 'brevo+api://api.brevo.com';
    }
}

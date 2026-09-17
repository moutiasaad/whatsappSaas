<?php

namespace App\Services\Messenger;

use RuntimeException;

/**
 * MessengerSendException
 *
 * Thrown by MessengerService when a send can't be attempted or Meta
 * rejects it. Carries a stable machine-readable `code` so the caller
 * (inbox controller, AI worker) can branch without string-parsing
 * the message.
 *
 * Known codes:
 *   - empty_body                 caller tried to send an empty message
 *   - conversation_not_found     conversation id doesn't exist
 *   - page_disconnected          the Page was disconnected or disabled
 *   - outside_messaging_window   >24h since last inbound, no MESSAGE_TAG
 *   - meta_rejected              Meta returned an error on the send
 */
class MessengerSendException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}

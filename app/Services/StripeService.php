<?php

namespace App\Services;

use Stripe\Checkout\Session;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createCheckoutSession(array $params): Session
    {
        return Session::create($params);
    }

    public function retrieveSession(string $sessionId): Session
    {
        return Session::retrieve($sessionId);
    }

    public function isCompleted(Session $session): bool
    {
        return $session->payment_status === 'paid'
            || $session->status === 'complete';
    }

    public function constructWebhookEvent(string $payload, string $signature): \Stripe\Event
    {
        return Webhook::constructEvent(
            $payload,
            $signature,
            config('services.stripe.webhook_secret')
        );
    }
}

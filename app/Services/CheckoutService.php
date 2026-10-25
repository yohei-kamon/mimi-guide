<?php

namespace App\Services;

use App\Models\GuidePass;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class CheckoutService
{
    /**
     * Create a Stripe Checkout Session for the pending pass, priced at the pass amount in yen.
     */
    public function createSession(GuidePass $guidePass): Session
    {
        $museum = $guidePass->museum;

        return $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'jpy',
                    'unit_amount' => $guidePass->amount,
                    'product_data' => ['name' => "{$museum->name()} 音声ガイド"],
                ],
            ]],
            'metadata' => ['guide_pass_id' => (string) $guidePass->id],
            'client_reference_id' => (string) $guidePass->id,
            'success_url' => route('visitor.passes.complete').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('visitor.museum', $museum),
        ]);
    }

    /**
     * Retrieve a Checkout Session to confirm its payment status.
     */
    public function retrieveSession(string $sessionId): Session
    {
        return $this->client()->checkout->sessions->retrieve($sessionId);
    }

    /**
     * Verify the signature of a webhook request and build its event, or return null when it is not from Stripe.
     */
    public function constructEvent(string $payload, string $signature): ?Event
    {
        try {
            return Webhook::constructEvent($payload, $signature, (string) config('services.stripe.webhook_secret'));
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return null;
        }
    }

    /**
     * Get a Stripe client authenticated with the secret key.
     */
    private function client(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }
}

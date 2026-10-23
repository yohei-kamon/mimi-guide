<?php

namespace App\Actions;

use App\Mail\GuidePassPurchased;
use App\Models\GuidePass;
use Illuminate\Support\Facades\Mail;
use Stripe\Checkout\Session;

class ConfirmGuidePassPayment
{
    /**
     * Activate the pass when the Checkout Session is paid, and send the purchase email.
     *
     * Both the webhook and the completion page call this. Only the call that actually
     * moves the pass from pending to paid sends the email, so it is sent once.
     */
    public function handle(GuidePass $guidePass, Session $session): void
    {
        if ($session->id !== $guidePass->stripe_checkout_session_id || $session->payment_status !== 'paid') {
            return;
        }

        $email = $session->customer_details?->email;

        if (! $guidePass->markAsPaid($email)) {
            return;
        }

        if (filled($email)) {
            Mail::to($email)->send(new GuidePassPurchased($guidePass->load('museum.translations')));
        }
    }
}

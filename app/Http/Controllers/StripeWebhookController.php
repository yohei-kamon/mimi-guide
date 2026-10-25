<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmGuidePassPayment;
use App\Models\GuidePass;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Checkout\Session;

class StripeWebhookController extends Controller
{
    /**
     * Handle a webhook from Stripe. Only `checkout.session.completed` is used, to activate the paid pass.
     */
    public function __invoke(Request $request, CheckoutService $checkout, ConfirmGuidePassPayment $confirmPayment): Response
    {
        $event = $checkout->constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'));

        if ($event === null) {
            return response('Invalid signature', 400);
        }

        if ($event->type === 'checkout.session.completed' && $event->data->object instanceof Session) {
            $session = $event->data->object;

            $guidePassId = $session->metadata['guide_pass_id'] ?? null;

            $guidePass = is_string($guidePassId) ? GuidePass::query()->whereKey($guidePassId)->first() : null;

            if ($guidePass !== null) {
                $confirmPayment->handle($guidePass, $session);
            }
        }

        return response('OK');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\GuidePass;
use App\Models\Museum;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GuidePassController extends Controller
{
    /**
     * Start a pending pass for the museum, keep its token in a cookie, and send the visitor to Stripe Checkout.
     */
    public function store(Request $request, Museum $museum, CheckoutService $checkout): RedirectResponse
    {
        abort_unless($museum->is_active, 404);

        if ($museum->isFree() || $museum->validPass($museum->passTokenFrom($request)) !== null) {
            return redirect()->route('visitor.museum', $museum);
        }

        $guidePass = GuidePass::startFor($museum);

        $session = $checkout->createSession($guidePass);

        $guidePass->update(['stripe_checkout_session_id' => $session->id]);

        return redirect()->away((string) $session->url)->withCookie($museum->passCookie($guidePass));
    }
}

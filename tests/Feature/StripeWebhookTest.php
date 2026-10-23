<?php

use App\Enums\GuidePassStatus;
use App\Mail\GuidePassPurchased;
use App\Models\GuidePass;
use App\Services\CheckoutService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Stripe\Checkout\Session;

beforeEach(function () {
    config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
    Mail::fake();
});

/**
 * Build the payload of a completed Checkout Session event for the pass.
 */
function checkoutCompletedPayload(GuidePass $guidePass, string $paymentStatus = 'paid'): string
{
    return json_encode([
        'id' => 'evt_test_1',
        'object' => 'event',
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => $guidePass->stripe_checkout_session_id,
            'object' => 'checkout.session',
            'payment_status' => $paymentStatus,
            'metadata' => ['guide_pass_id' => (string) $guidePass->id],
            'customer_details' => ['email' => 'visitor@example.com'],
        ]],
    ]);
}

/**
 * Send the payload to the webhook, signed the way Stripe signs it with the given secret.
 */
function postStripeWebhook(string $payload, string $secret = 'whsec_test_secret'): TestResponse
{
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

    return test()->call(
        'POST',
        route('stripe.webhook'),
        server: ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}", 'CONTENT_TYPE' => 'application/json'],
        content: $payload,
    );
}

test('a signed checkout.session.completed event activates the pass and sends the purchase email', function () {
    $this->freezeSecond();
    $guidePass = GuidePass::factory()->create();

    postStripeWebhook(checkoutCompletedPayload($guidePass))->assertOk();

    $guidePass->refresh();

    expect($guidePass->status)->toBe(GuidePassStatus::Paid)
        ->and($guidePass->email)->toBe('visitor@example.com')
        ->and($guidePass->expires_at->equalTo(now()->addHours(24)))->toBeTrue();

    Mail::assertSent(GuidePassPurchased::class, fn (GuidePassPurchased $mail) => $mail->hasTo('visitor@example.com')
        && $mail->guidePass->is($guidePass));
});

test('the purchase email holds the amount, the expiry and the URL to restore the pass', function () {
    $guidePass = GuidePass::factory()->paid()->create(['amount' => 1500]);

    $mail = new GuidePassPurchased($guidePass);

    $mail->assertSeeInHtml($guidePass->museum->name())
        ->assertSeeInHtml('1,500円')
        ->assertSeeInHtml($guidePass->expiresAtLabel())
        ->assertSeeInHtml(route('visitor.passes.show', $guidePass->token))
        ->assertSeeInHtml(route('visitor.museum', $guidePass->museum));
});

test('a webhook with an invalid signature is rejected and changes nothing', function () {
    $guidePass = GuidePass::factory()->create();

    postStripeWebhook(checkoutCompletedPayload($guidePass), 'whsec_wrong_secret')->assertStatus(400);

    expect($guidePass->fresh()->status)->toBe(GuidePassStatus::Pending);
    Mail::assertNothingSent();
});

test('an unpaid session does not activate the pass', function () {
    $guidePass = GuidePass::factory()->create();

    postStripeWebhook(checkoutCompletedPayload($guidePass, 'unpaid'))->assertOk();

    expect($guidePass->fresh()->status)->toBe(GuidePassStatus::Pending);
    Mail::assertNothingSent();
});

test('the same event sent twice sends the email only once', function () {
    $guidePass = GuidePass::factory()->create();
    $payload = checkoutCompletedPayload($guidePass);

    postStripeWebhook($payload)->assertOk();
    postStripeWebhook($payload)->assertOk();

    Mail::assertSentCount(1);
});

test('confirming on the completion page and then by webhook sends the email only once', function () {
    $guidePass = GuidePass::factory()->create();

    $this->mock(CheckoutService::class)
        ->makePartial()
        ->shouldReceive('retrieveSession')
        ->andReturn(Session::constructFrom([
            'id' => $guidePass->stripe_checkout_session_id,
            'payment_status' => 'paid',
            'customer_details' => ['email' => 'visitor@example.com'],
        ]));

    $this->get(route('visitor.passes.complete', ['session_id' => $guidePass->stripe_checkout_session_id]))->assertOk();
    postStripeWebhook(checkoutCompletedPayload($guidePass))->assertOk();

    expect($guidePass->fresh()->status)->toBe(GuidePassStatus::Paid);
    Mail::assertSentCount(1);
});

test('a session that does not match the pass is ignored', function () {
    $guidePass = GuidePass::factory()->create();
    $otherPass = GuidePass::factory()->create();

    $payload = json_decode(checkoutCompletedPayload($otherPass), true);
    $payload['data']['object']['metadata']['guide_pass_id'] = (string) $guidePass->id;

    postStripeWebhook(json_encode($payload))->assertOk();

    expect($guidePass->fresh()->status)->toBe(GuidePassStatus::Pending);
    Mail::assertNothingSent();
});

test('other events are acknowledged and ignored', function () {
    $guidePass = GuidePass::factory()->create();

    postStripeWebhook(json_encode([
        'id' => 'evt_test_2',
        'object' => 'event',
        'type' => 'payment_intent.created',
        'data' => ['object' => ['id' => 'pi_test', 'object' => 'payment_intent']],
    ]))->assertOk();

    expect($guidePass->fresh()->status)->toBe(GuidePassStatus::Pending);
});

test('the pass URL from the email restores the pass into the browser', function () {
    $guidePass = GuidePass::factory()->paid()->create();

    $this->get(route('visitor.passes.show', $guidePass->token))
        ->assertRedirect(route('visitor.museum', $guidePass->museum))
        ->assertCookie($guidePass->museum->passCookieName(), $guidePass->token);
});

test('the pass URL of a pending or unknown pass is not found', function () {
    $pending = GuidePass::factory()->create();

    $this->get(route('visitor.passes.show', $pending->token))->assertNotFound();
    $this->get(route('visitor.passes.show', str_repeat('a', 40)))->assertNotFound();
});

<?php

use App\Enums\GuidePassStatus;
use App\Models\Artwork;
use App\Models\AudioGuide;
use App\Models\Exhibition;
use App\Models\GuidePass;
use App\Models\Museum;
use App\Services\CheckoutService;
use Illuminate\Support\Facades\Storage;
use Stripe\Checkout\Session;

/**
 * Create a published artwork with a stored Japanese audio file in the given museum.
 */
function paidMuseumArtwork(Museum $museum): Artwork
{
    $artwork = Artwork::factory()
        ->for(Exhibition::factory()->for($museum)->published())
        ->published()
        ->create();
    $artwork->translation()->update(['description' => '有料の解説文']);

    $audioGuide = AudioGuide::factory()->for($artwork)->create();
    Storage::disk(AudioGuide::DISK)->put($audioGuide->path, 'paid audio');

    return $artwork;
}

beforeEach(function () {
    Storage::fake(AudioGuide::DISK);
});

test('a paid museum hides the description and offers the pass without one', function () {
    $artwork = paidMuseumArtwork(Museum::factory()->paid(800)->create());

    $this->get(route('visitor.artwork', $artwork))
        ->assertOk()
        ->assertSee($artwork->title())
        ->assertDontSee('有料の解説文')
        ->assertSee('音声ガイドを購入（800円）')
        ->assertSee(route('visitor.passes.store', $artwork->exhibition->museum), false);

    $this->get(route('visitor.artwork.audio', $artwork))->assertForbidden();
});

test('buying a pass creates a pending pass at the museum price and sends the visitor to Stripe', function () {
    $museum = Museum::factory()->paid(800)->create();

    $this->mock(CheckoutService::class)
        ->shouldReceive('createSession')
        ->once()
        ->withArgs(fn (GuidePass $guidePass) => $guidePass->museum_id === $museum->id)
        ->andReturn(Session::constructFrom(['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_123']));

    $response = $this->post(route('visitor.passes.store', $museum))
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');

    $guidePass = $museum->guidePasses()->sole();

    expect($guidePass->status)->toBe(GuidePassStatus::Pending)
        ->and($guidePass->amount)->toBe(800)
        ->and($guidePass->token)->toHaveLength(40)
        ->and($guidePass->stripe_checkout_session_id)->toBe('cs_test_123');

    $response->assertCookie($museum->passCookieName(), $guidePass->token);
});

test('the pass keeps the price at the time of the order', function () {
    $guidePass = GuidePass::factory()->create(['amount' => 800]);

    $guidePass->museum->update(['guide_price' => 1200]);

    expect($guidePass->fresh()->amount)->toBe(800);
});

test('no pass is created and Stripe is not called for a free or stopped museum', function (Museum $museum, int $status) {
    $this->mock(CheckoutService::class)->shouldNotReceive('createSession');

    $this->post(route('visitor.passes.store', $museum))->assertStatus($status);

    expect(GuidePass::count())->toBe(0);
})->with([
    'free museum' => fn () => [Museum::factory()->create(), 302],
    'stopped museum' => fn () => [Museum::factory()->paid()->inactive()->create(), 404],
]);

test('visitors who already hold a valid pass are not charged again', function () {
    $guidePass = GuidePass::factory()->paid()->create();
    $museum = $guidePass->museum;

    $this->mock(CheckoutService::class)->shouldNotReceive('createSession');

    $this->withCookie($museum->passCookieName(), $guidePass->token)
        ->post(route('visitor.passes.store', $museum))
        ->assertRedirect(route('visitor.museum', $museum));

    expect($museum->guidePasses()->count())->toBe(1);
});

test('the completion page confirms the payment with Stripe and activates the pass for 24 hours', function () {
    $this->freezeSecond();
    $guidePass = GuidePass::factory()->create(['stripe_checkout_session_id' => 'cs_test_paid']);

    $this->mock(CheckoutService::class)
        ->shouldReceive('retrieveSession')
        ->once()
        ->with('cs_test_paid')
        ->andReturn(Session::constructFrom([
            'id' => 'cs_test_paid',
            'payment_status' => 'paid',
            'customer_details' => ['email' => 'visitor@example.com'],
        ]));

    $this->get(route('visitor.passes.complete', ['session_id' => 'cs_test_paid']))
        ->assertOk()
        ->assertSee('購入が完了しました')
        ->assertCookie($guidePass->museum->passCookieName(), $guidePass->token);

    $guidePass->refresh();

    expect($guidePass->status)->toBe(GuidePassStatus::Paid)
        ->and($guidePass->email)->toBe('visitor@example.com')
        ->and($guidePass->paid_at->equalTo(now()))->toBeTrue()
        ->and($guidePass->expires_at->equalTo(now()->addHours(24)))->toBeTrue();
});

test('the completion page keeps the pass pending until Stripe reports the payment', function () {
    $guidePass = GuidePass::factory()->create(['stripe_checkout_session_id' => 'cs_test_unpaid']);

    $this->mock(CheckoutService::class)
        ->shouldReceive('retrieveSession')
        ->andReturn(Session::constructFrom(['id' => 'cs_test_unpaid', 'payment_status' => 'unpaid']));

    $this->get(route('visitor.passes.complete', ['session_id' => 'cs_test_unpaid']))
        ->assertOk()
        ->assertSee('支払いを確認しています');

    expect($guidePass->fresh()->status)->toBe(GuidePassStatus::Pending);
});

test('the completion page does not ask Stripe again for a paid pass', function () {
    $guidePass = GuidePass::factory()->paid()->create();

    $this->mock(CheckoutService::class)->shouldNotReceive('retrieveSession');

    $this->get(route('visitor.passes.complete', ['session_id' => $guidePass->stripe_checkout_session_id]))
        ->assertOk()
        ->assertSee('購入が完了しました');
});

test('the completion page is not found for an unknown session', function () {
    $this->mock(CheckoutService::class)->shouldNotReceive('retrieveSession');

    $this->get(route('visitor.passes.complete', ['session_id' => 'cs_test_unknown']))->assertNotFound();
    $this->get(route('visitor.passes.complete'))->assertNotFound();
});

test('a pass is confirmed only once', function () {
    $guidePass = GuidePass::factory()->create();

    expect($guidePass->markAsPaid('first@example.com'))->toBeTrue()
        ->and($guidePass->fresh()->markAsPaid('second@example.com'))->toBeFalse()
        ->and($guidePass->fresh()->email)->toBe('first@example.com');
});

test('a valid pass opens the description, the audio and continuous playback', function () {
    $guidePass = GuidePass::factory()->paid()->create();
    $artwork = paidMuseumArtwork($guidePass->museum);

    $this->withCookie($guidePass->museum->passCookieName(), $guidePass->token);

    $this->get(route('visitor.artwork', $artwork))
        ->assertSee('有料の解説文')
        ->assertDontSee('音声ガイドを購入');

    $response = $this->get(route('visitor.artwork.audio', $artwork))->assertOk();
    expect($response->baseResponse->getFile()->getContent())->toBe('paid audio');

    $this->get(route('visitor.exhibition.play', $artwork->exhibition))
        ->assertDontSee('音声ガイドを購入')
        ->assertSee($artwork->slug);

    $this->get(route('visitor.museum', $guidePass->museum))
        ->assertSee('音声ガイドを利用できます');
});

test('the audio is forbidden with an expired, pending or another museum\'s pass', function (Closure $makePass) {
    $museum = Museum::factory()->paid(800)->create();
    $artwork = paidMuseumArtwork($museum);
    $guidePass = $makePass($museum);

    $this->withCookie($museum->passCookieName(), $guidePass->token)
        ->get(route('visitor.artwork.audio', $artwork))
        ->assertForbidden();
})->with([
    'expired pass' => fn (Museum $museum) => GuidePass::factory()->for($museum)->expired()->create(),
    'pending pass' => fn (Museum $museum) => GuidePass::factory()->for($museum)->create(),
    'another museum\'s pass' => fn (Museum $museum) => GuidePass::factory()->paid()->create(),
]);

<?php

use App\Models\Artwork;
use App\Models\Exhibition;
use App\Models\User;
use App\Services\QrCodeService;

test('staff see the QR code of their artwork that points to its visitor page on APP_URL', function () {
    config(['app.url' => 'https://guide.example.com']);

    $user = User::factory()->create();
    $artwork = Artwork::factory()->for(Exhibition::factory()->for($user->museum))->create();
    $url = "https://guide.example.com/a/{$artwork->slug}";

    $this->mock(QrCodeService::class)
        ->shouldReceive('svg')
        ->once()
        ->with($url)
        ->andReturn('<svg data-test="qr"></svg>');

    $this->actingAs($user)
        ->get(route('artworks.qr', $artwork))
        ->assertOk()
        ->assertSee('<svg data-test="qr"></svg>', escape: false)
        ->assertSee($url);
});

test('staff can print the QR codes of all artworks in their exhibition', function () {
    $user = User::factory()->create();
    $exhibition = Exhibition::factory()->for($user->museum)->create();
    $second = Artwork::factory()->for($exhibition)->create(['sort_order' => 2]);
    $first = Artwork::factory()->for($exhibition)->create(['sort_order' => 1]);

    $this->mock(QrCodeService::class)
        ->shouldReceive('svg')
        ->twice()
        ->andReturn('<svg></svg>');

    $this->actingAs($user)
        ->get(route('exhibitions.qr', $exhibition))
        ->assertOk()
        ->assertSeeInOrder([$first->title(), $second->title()]);
});

test('staff cannot open the QR codes of another museum', function () {
    $user = User::factory()->create();
    $artwork = Artwork::factory()->create();

    $this->actingAs($user)->get(route('artworks.qr', $artwork))->assertForbidden();
    $this->actingAs($user)->get(route('exhibitions.qr', $artwork->exhibition))->assertForbidden();
});

test('the QR code service renders the URL as SVG', function () {
    expect(app(QrCodeService::class)->svg('https://guide.example.com/a/abc'))->toContain('<svg');
});

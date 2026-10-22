<?php

use App\Models\Artwork;
use App\Models\Exhibition;
use App\Models\Museum;
use Livewire\Livewire;

/**
 * Create a published artwork in a published exhibition of the given museum.
 */
function visibleArtwork(?Museum $museum = null, array $attributes = []): Artwork
{
    $exhibition = Exhibition::factory()->for($museum ?? Museum::factory()->create())->published()->create();

    return Artwork::factory()->for($exhibition)->published()->create($attributes);
}

test('the museum top lists only exhibitions visible today', function () {
    $museum = Museum::factory()->create();
    $published = Exhibition::factory()->for($museum)->published()->create();
    $published->translation()->update(['name' => '常設展 日本美術']);
    $hidden = [
        Exhibition::factory()->for($museum)->create(),
        Exhibition::factory()->for($museum)->unpublished()->create(),
        Exhibition::factory()->for($museum)->published()->ended()->create(),
    ];

    foreach ($hidden as $index => $exhibition) {
        $exhibition->translation()->update(['name' => "見えない展示{$index}"]);
    }

    $this->get(route('visitor.museum', $museum))
        ->assertOk()
        ->assertSee('常設展 日本美術')
        ->assertDontSee('見えない展示');
});

test('the museum top of a stopped museum is not found', function () {
    $this->get(route('visitor.museum', Museum::factory()->inactive()->create()))->assertNotFound();
});

test('the exhibition page lists published artworks in playback order', function () {
    $exhibition = Exhibition::factory()->published()->create();
    $second = Artwork::factory()->for($exhibition)->published()->create(['sort_order' => 2]);
    $first = Artwork::factory()->for($exhibition)->published()->create(['sort_order' => 1]);
    $draft = Artwork::factory()->for($exhibition)->create();
    $draft->translation()->update(['title' => '下書きの作品']);

    $this->get(route('visitor.exhibition', $exhibition))
        ->assertOk()
        ->assertSeeInOrder([$first->title(), $second->title()])
        ->assertDontSee('下書きの作品');
});

test('the exhibition page is not found when the exhibition or museum is hidden', function (Exhibition $exhibition) {
    $this->get(route('visitor.exhibition', $exhibition))->assertNotFound();
})->with([
    'draft exhibition' => fn () => Exhibition::factory()->create(),
    'unpublished exhibition' => fn () => Exhibition::factory()->unpublished()->create(),
    'ended exhibition' => fn () => Exhibition::factory()->published()->ended()->create(),
    'stopped museum' => fn () => Exhibition::factory()->for(Museum::factory()->inactive())->published()->create(),
]);

test('guests can read an artwork of a free museum without logging in', function () {
    $artwork = visibleArtwork();
    $translation = $artwork->translation();

    $this->get(route('visitor.artwork', $artwork))
        ->assertOk()
        ->assertSee($translation->title)
        ->assertSee($translation->artist)
        ->assertSee($translation->production_year)
        ->assertSee($translation->description);
});

test('an artwork of a paid museum hides the description', function () {
    $artwork = visibleArtwork(Museum::factory()->paid(800)->create());
    $translation = $artwork->translation();

    $this->get(route('visitor.artwork', $artwork))
        ->assertOk()
        ->assertSee($translation->title)
        ->assertDontSee($translation->description)
        ->assertSee('音声ガイド 800円');
});

test('the artwork page is not found when the artwork, exhibition or museum is hidden', function (Artwork $artwork) {
    $this->get(route('visitor.artwork', $artwork))->assertNotFound();
})->with([
    'draft artwork' => fn () => Artwork::factory()->for(Exhibition::factory()->published())->create(),
    'unpublished artwork' => fn () => Artwork::factory()->for(Exhibition::factory()->published())->unpublished()->create(),
    'unpublished exhibition' => fn () => Artwork::factory()->for(Exhibition::factory()->unpublished())->published()->create(),
    'ended exhibition' => fn () => Artwork::factory()->for(Exhibition::factory()->published()->ended())->published()->create(),
    'stopped museum' => fn () => visibleArtwork(Museum::factory()->inactive()->create()),
]);

test('a guide number opens the artwork of the same exhibition', function () {
    $artwork = visibleArtwork(attributes: ['guide_number' => 12]);
    visibleArtwork(attributes: ['guide_number' => 13]);
    Artwork::factory()->for(Exhibition::factory()->published())->published()->create(['guide_number' => 12]);

    Livewire::test('pages::visitor.exhibition', ['exhibition' => $artwork->exhibition])
        ->set('guideNumber', '12')
        ->call('openByGuideNumber')
        ->assertHasNoErrors()
        ->assertRedirect(route('visitor.artwork', $artwork));
});

test('a guide number of another exhibition, a draft artwork or no artwork shows an error', function (int $guideNumber) {
    $artwork = visibleArtwork(attributes: ['guide_number' => 1]);
    Artwork::factory()->for($artwork->exhibition)->create(['guide_number' => 2]);
    visibleArtwork(attributes: ['guide_number' => 3]);

    Livewire::test('pages::visitor.exhibition', ['exhibition' => $artwork->exhibition])
        ->set('guideNumber', (string) $guideNumber)
        ->call('openByGuideNumber')
        ->assertHasErrors('guideNumber')
        ->assertNoRedirect();
})->with([
    'draft artwork' => 2,
    'another exhibition' => 3,
    'no artwork' => 99,
]);

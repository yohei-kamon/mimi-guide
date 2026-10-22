<?php

use App\Models\Artwork;
use App\Models\AudioGuide;
use App\Models\Exhibition;
use App\Models\Museum;
use Livewire\Livewire;

test('the playlist holds only published artworks with audio, in playback order', function () {
    $exhibition = Exhibition::factory()->published()->create();
    $second = Artwork::factory()->for($exhibition)->published()->has(AudioGuide::factory())->create(['sort_order' => 2]);
    $first = Artwork::factory()->for($exhibition)->published()->has(AudioGuide::factory())->create(['sort_order' => 1]);
    Artwork::factory()->for($exhibition)->has(AudioGuide::factory())->create(['sort_order' => 0]);
    Artwork::factory()->for($exhibition)->published()->create(['sort_order' => 0]);

    $component = Livewire::test('pages::visitor.play', ['exhibition' => $exhibition]);

    expect(array_column($component->instance()->playlist, 'slug'))->toBe([$first->slug, $second->slug])
        ->and($component->instance()->playlist[0]['audioUrl'])->toBe(route('visitor.artwork.audio', $first))
        ->and($component->instance()->startIndex)->toBe(0);
});

test('artworks with the same sort order are played in guide number order', function () {
    $exhibition = Exhibition::factory()->published()->create();
    $later = Artwork::factory()->for($exhibition)->published()->has(AudioGuide::factory())->create(['sort_order' => 1, 'guide_number' => 9]);
    $earlier = Artwork::factory()->for($exhibition)->published()->has(AudioGuide::factory())->create(['sort_order' => 1, 'guide_number' => 3]);

    $component = Livewire::test('pages::visitor.play', ['exhibition' => $exhibition]);

    expect(array_column($component->instance()->playlist, 'slug'))->toBe([$earlier->slug, $later->slug]);
});

test('playback starts from the requested artwork', function () {
    $exhibition = Exhibition::factory()->published()->create();
    Artwork::factory()->for($exhibition)->published()->has(AudioGuide::factory())->create(['sort_order' => 1]);
    $from = Artwork::factory()->for($exhibition)->published()->has(AudioGuide::factory())->create(['sort_order' => 2]);

    $component = Livewire::withQueryParams(['from' => $from->slug])
        ->test('pages::visitor.play', ['exhibition' => $exhibition]);

    expect($component->instance()->startIndex)->toBe(1);
});

test('the player page is shown from the exhibition and artwork pages', function () {
    $exhibition = Exhibition::factory()->published()->create();
    $artwork = Artwork::factory()->for($exhibition)->published()->has(AudioGuide::factory())->create();

    $this->get(route('visitor.exhibition.play', $exhibition))->assertOk();
    $this->get(route('visitor.exhibition', $exhibition))
        ->assertSee(route('visitor.exhibition.play', $exhibition), false);
    $this->get(route('visitor.artwork', $artwork))
        ->assertSee(route('visitor.exhibition.play', [$exhibition, 'from' => $artwork->slug]), false);
});

test('the player of a paid museum does not reveal the playlist without a pass', function () {
    $exhibition = Exhibition::factory()->for(Museum::factory()->paid(800))->published()->create();
    $artwork = Artwork::factory()->for($exhibition)->published()->has(AudioGuide::factory())->create();

    $this->get(route('visitor.exhibition.play', $exhibition))
        ->assertOk()
        ->assertSee('購入が必要です')
        ->assertDontSee($artwork->slug);
});

test('the player is not found when the exhibition or museum is hidden', function (Exhibition $exhibition) {
    $this->get(route('visitor.exhibition.play', $exhibition))->assertNotFound();
})->with([
    'draft exhibition' => fn () => Exhibition::factory()->create(),
    'ended exhibition' => fn () => Exhibition::factory()->published()->ended()->create(),
    'stopped museum' => fn () => Exhibition::factory()->for(Museum::factory()->inactive())->published()->create(),
]);

<?php

use App\Models\Artwork;
use App\Models\ArtworkTranslation;
use App\Models\AudioGuide;
use App\Models\Exhibition;
use App\Models\Museum;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Create a published artwork in a museum that supports Japanese and English, with an English title and description.
 */
function bilingualArtwork(array $english = ['title' => 'The Great Wave', 'description' => 'A wave off the coast.']): Artwork
{
    $museum = Museum::factory()->create(['locales' => ['ja', 'en'], 'default_locale' => 'ja']);
    $exhibition = Exhibition::factory()->for($museum)->published()->create();
    $artwork = Artwork::factory()->for($exhibition)->published()->create();
    $artwork->translation()->update(['title' => '神奈川沖浪裏', 'description' => '沖の大波。']);
    ArtworkTranslation::factory()->for($artwork)->locale('en')->create($english);

    return $artwork;
}

/**
 * Add an audio guide in the given locale with a stored file to the artwork.
 */
function storedLocaleAudioGuide(Artwork $artwork, string $locale, string $contents): AudioGuide
{
    $audioGuide = AudioGuide::factory()->for($artwork)->locale($locale)->create();
    Storage::disk(AudioGuide::DISK)->put($audioGuide->path, $contents);

    return $audioGuide;
}

test('the chosen locale shows the translations and is kept in the session', function () {
    $artwork = bilingualArtwork();

    $this->get(route('visitor.artwork', [$artwork, 'lang' => 'en']))
        ->assertOk()
        ->assertSee('The Great Wave')
        ->assertSee('A wave off the coast.')
        ->assertDontSee('神奈川沖浪裏')
        ->assertSessionHas(Museum::VISITOR_LOCALE_SESSION_KEY, 'en');

    $this->get(route('visitor.exhibition', $artwork->exhibition))
        ->assertOk()
        ->assertSee('The Great Wave');
});

test('items without a translation in the chosen locale are shown in the default locale', function () {
    $artwork = bilingualArtwork(['title' => 'The Great Wave', 'description' => null]);

    $this->get(route('visitor.artwork', [$artwork, 'lang' => 'en']))
        ->assertOk()
        ->assertSee('The Great Wave')
        ->assertSee('沖の大波。');
});

test('a locale the museum does not support is ignored', function (string $locale) {
    $artwork = Artwork::factory()->for(Exhibition::factory()->published())->published()->create();

    $this->get(route('visitor.artwork', [$artwork, 'lang' => $locale]))
        ->assertOk()
        ->assertSee($artwork->title('ja'))
        ->assertSessionMissing(Museum::VISITOR_LOCALE_SESSION_KEY);
})->with(['unsupported by the museum' => 'en', 'unknown' => 'xx']);

test('the language switcher lists the museum locales only when there is more than one', function () {
    $artwork = bilingualArtwork();

    $this->get(route('visitor.artwork', $artwork))
        ->assertSee('English')
        ->assertSee(route('visitor.artwork', [$artwork, 'lang' => 'en']), false);

    $this->get(route('visitor.artwork', Artwork::factory()->for(Exhibition::factory()->published())->published()->create()))
        ->assertDontSee('English');
});

test('the audio is streamed in the chosen locale', function () {
    Storage::fake(AudioGuide::DISK);
    $artwork = bilingualArtwork();
    storedLocaleAudioGuide($artwork, 'ja', 'japanese audio');
    storedLocaleAudioGuide($artwork, 'en', 'english audio');

    $this->get(route('visitor.artwork', [$artwork, 'lang' => 'en']))
        ->assertDontSee('この言語の音声はありません');

    $response = $this->get(route('visitor.artwork.audio', $artwork))->assertOk();

    expect($response->baseResponse->getFile()->getContent())->toBe('english audio');
});

test('the audio falls back to the default locale with a notice when the chosen locale has none', function () {
    Storage::fake(AudioGuide::DISK);
    $artwork = bilingualArtwork();
    storedLocaleAudioGuide($artwork, 'ja', 'japanese audio');

    $this->get(route('visitor.artwork', [$artwork, 'lang' => 'en']))
        ->assertSee('この言語の音声はありません');

    $response = $this->get(route('visitor.artwork.audio', $artwork))->assertOk();

    expect($response->baseResponse->getFile()->getContent())->toBe('japanese audio');
});

test('the playlist uses the chosen locale', function () {
    $artwork = bilingualArtwork();
    AudioGuide::factory()->for($artwork)->create();

    $component = Livewire::withQueryParams(['lang' => 'en'])
        ->test('pages::visitor.play', ['exhibition' => $artwork->exhibition]);

    expect($component->instance()->playlist[0]['title'])->toBe('The Great Wave')
        ->and($component->instance()->playlist[0]['description'])->toBe('A wave off the coast.');
});

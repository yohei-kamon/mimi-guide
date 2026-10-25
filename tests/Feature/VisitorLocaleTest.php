<?php

use App\Models\Artwork;
use App\Models\ArtworkTranslation;
use App\Models\AudioGuide;
use App\Models\Exhibition;
use App\Models\ExhibitionTranslation;
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

test('the chosen locale shows the translated description and is kept in the session', function () {
    $artwork = bilingualArtwork();

    $this->get(route('visitor.artwork', [$artwork, 'lang' => 'en']))
        ->assertOk()
        ->assertSee('A wave off the coast.')
        ->assertDontSee('沖の大波。')
        ->assertSessionHas(Museum::VISITOR_LOCALE_SESSION_KEY, 'en');

    $this->get(route('visitor.artwork', $artwork))
        ->assertOk()
        ->assertSee('A wave off the coast.');
});

test('the artwork title stays in the default locale in any chosen locale', function () {
    $artwork = bilingualArtwork();

    $this->get(route('visitor.artwork', [$artwork, 'lang' => 'en']))
        ->assertOk()
        ->assertSee('神奈川沖浪裏')
        ->assertDontSee('The Great Wave');

    $this->get(route('visitor.exhibition', $artwork->exhibition))
        ->assertOk()
        ->assertSee('神奈川沖浪裏')
        ->assertDontSee('The Great Wave');
});

test('items without a translation in the chosen locale are shown in the default locale', function () {
    $artwork = bilingualArtwork(['title' => 'The Great Wave', 'description' => null]);

    $this->get(route('visitor.artwork', [$artwork, 'lang' => 'en']))
        ->assertOk()
        ->assertSee('沖の大波。');
});

test('the exhibition name and description stay in the default locale in any chosen locale', function () {
    $artwork = bilingualArtwork();
    $exhibition = $artwork->exhibition;
    $exhibition->translation()->update(['name' => '企画展 浮世絵の世界', 'description' => '浮世絵の名品を紹介します。']);
    ExhibitionTranslation::factory()->for($exhibition)->locale('en')->create(['name' => 'The World of Ukiyo-e', 'description' => 'Masterpieces of ukiyo-e.']);

    $this->get(route('visitor.exhibition', [$exhibition, 'lang' => 'en']))
        ->assertOk()
        ->assertSee('企画展 浮世絵の世界')
        ->assertSee('浮世絵の名品を紹介します。')
        ->assertDontSee('The World of Ukiyo-e')
        ->assertDontSee('Masterpieces of ukiyo-e.');

    $this->get(route('visitor.museum', $exhibition->museum))
        ->assertSee('企画展 浮世絵の世界')
        ->assertDontSee('The World of Ukiyo-e');

    $this->get(route('visitor.artwork', $artwork))
        ->assertSee('企画展 浮世絵の世界')
        ->assertDontSee('The World of Ukiyo-e');
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

test('the audio URL changes with the chosen locale so the browser does not replay a cached file', function () {
    $artwork = bilingualArtwork();
    AudioGuide::factory()->for($artwork)->locale('ja')->create();
    AudioGuide::factory()->for($artwork)->locale('en')->create();
    $artwork->load('audioGuides');

    expect($artwork->audioUrl('ja'))->not->toBe($artwork->audioUrl('en'))
        ->and($artwork->audioUrl('ja'))->toBe($artwork->audioUrl('ja'))
        ->and($artwork->audioUrl('ja'))->toStartWith(route('visitor.artwork.audio', $artwork).'?v=');

    $this->get(route('visitor.artwork', [$artwork, 'lang' => 'en']))
        ->assertSee($artwork->audioUrl('en'), false);

    $this->get(route('visitor.artwork', [$artwork, 'lang' => 'ja']))
        ->assertSee($artwork->audioUrl('ja'), false)
        ->assertDontSee($artwork->audioUrl('en'), false);
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

test('the playlist uses the chosen locale for the description and the default locale for the title', function () {
    $artwork = bilingualArtwork();
    AudioGuide::factory()->for($artwork)->create();

    $component = Livewire::withQueryParams(['lang' => 'en'])
        ->test('pages::visitor.play', ['exhibition' => $artwork->exhibition]);

    expect($component->instance()->playlist[0]['title'])->toBe('神奈川沖浪裏')
        ->and($component->instance()->playlist[0]['description'])->toBe('A wave off the coast.');
});

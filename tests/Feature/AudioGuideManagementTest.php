<?php

use App\Models\Artwork;
use App\Models\ArtworkTranslation;
use App\Models\AudioGuide;
use App\Models\Exhibition;
use App\Models\Museum;
use App\Models\User;
use App\Services\TextToSpeechService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(AudioGuide::DISK);
});

/**
 * Create a staff user whose museum supports Japanese and English, with an artwork in it.
 *
 * @return array{0: User, 1: Artwork}
 */
function staffWithArtwork(): array
{
    $museum = Museum::factory()->create(['locales' => ['ja', 'en'], 'default_locale' => 'ja']);
    $user = User::factory()->for($museum)->create();

    return [$user, Artwork::factory()->for(Exhibition::factory()->for($museum))->create()];
}

test('staff can upload an audio guide for their artwork', function () {
    [$user, $artwork] = staffWithArtwork();

    Livewire::actingAs($user)
        ->test('pages::artworks.edit', ['artwork' => $artwork])
        ->set('audioForm.locale', 'en')
        ->set('audioForm.file', UploadedFile::fake()->create('guide.mp3', 500, 'audio/mpeg'))
        ->call('saveAudio')
        ->assertHasNoErrors()
        ->assertSee(route('audio-guides.preview', $artwork->audioGuides()->sole()), false);

    $audioGuide = $artwork->audioGuides()->sole();

    expect($audioGuide->locale)->toBe('en')
        ->and($audioGuide->mime_type)->toBe('audio/mpeg')
        ->and($audioGuide->size)->toBe(500 * 1024)
        ->and($audioGuide->path)->toStartWith("audio/{$user->museum_id}/")
        ->and($audioGuide->path)->not->toContain('guide.mp3');

    Storage::disk(AudioGuide::DISK)->assertExists($audioGuide->path);
});

test('uploading in the same locale replaces the audio guide and removes the old file', function () {
    [$user, $artwork] = staffWithArtwork();

    $component = Livewire::actingAs($user)
        ->test('pages::artworks.edit', ['artwork' => $artwork])
        ->set('audioForm.file', UploadedFile::fake()->create('first.mp3', 100, 'audio/mpeg'))
        ->call('saveAudio');

    $oldPath = $artwork->audioGuides()->sole()->path;

    $component
        ->set('audioForm.file', UploadedFile::fake()->create('second.m4a', 200, 'audio/mp4'))
        ->call('saveAudio')
        ->assertHasNoErrors();

    $audioGuide = $artwork->audioGuides()->sole();

    expect($audioGuide->path)->not->toBe($oldPath)
        ->and($audioGuide->mime_type)->toBe('audio/mp4');

    Storage::disk(AudioGuide::DISK)->assertMissing($oldPath);
    Storage::disk(AudioGuide::DISK)->assertExists($audioGuide->path);
});

test('audio guide uploads are validated and nothing is saved', function (array $input, string $field) {
    [$user, $artwork] = staffWithArtwork();

    Livewire::actingAs($user)
        ->test('pages::artworks.edit', ['artwork' => $artwork])
        ->fill($input)
        ->call('saveAudio')
        ->assertHasErrors($field);

    expect(AudioGuide::count())->toBe(0)
        ->and(Storage::disk(AudioGuide::DISK)->allFiles())->toBeEmpty();
})->with([
    'file is required' => fn () => [[], 'audioForm.file'],
    'file must be MP3 or M4A' => fn () => [['audioForm.file' => UploadedFile::fake()->create('guide.wav', 100, 'audio/wav')], 'audioForm.file'],
    'content must be audio' => fn () => [['audioForm.file' => UploadedFile::fake()->create('guide.mp3', 100, 'text/plain')], 'audioForm.file'],
    'file is at most 20MB' => fn () => [['audioForm.file' => UploadedFile::fake()->create('guide.mp3', 20481, 'audio/mpeg')], 'audioForm.file'],
    'locale must be supported by the museum' => fn () => [[
        'audioForm.locale' => 'ko',
        'audioForm.file' => UploadedFile::fake()->create('guide.mp3', 100, 'audio/mpeg'),
    ], 'audioForm.locale'],
]);

test('staff can delete an audio guide with its file', function () {
    [$user, $artwork] = staffWithArtwork();
    $audioGuide = AudioGuide::factory()->for($artwork)->create();
    Storage::disk(AudioGuide::DISK)->put($audioGuide->path, 'audio');

    Livewire::actingAs($user)
        ->test('pages::artworks.edit', ['artwork' => $artwork])
        ->call('deleteAudio', $audioGuide->id)
        ->assertHasNoErrors();

    expect(AudioGuide::count())->toBe(0);
    Storage::disk(AudioGuide::DISK)->assertMissing($audioGuide->path);
});

test('staff cannot delete an audio guide of another artwork through their artwork', function () {
    [$user, $artwork] = staffWithArtwork();
    $otherAudioGuide = AudioGuide::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::artworks.edit', ['artwork' => $artwork])
        ->call('deleteAudio', $otherAudioGuide->id)
        ->assertNotFound();

    expect($otherAudioGuide->fresh())->not->toBeNull();
});

test('deleting an artwork removes its audio files', function () {
    [$user, $artwork] = staffWithArtwork();
    $audioGuide = AudioGuide::factory()->for($artwork)->create();
    Storage::disk(AudioGuide::DISK)->put($audioGuide->path, 'audio');

    Livewire::actingAs($user)
        ->test('pages::artworks.edit', ['artwork' => $artwork])
        ->call('deleteArtwork');

    expect(AudioGuide::count())->toBe(0);
    Storage::disk(AudioGuide::DISK)->assertMissing($audioGuide->path);
});

test('staff can preview the audio guides of their museum only', function () {
    [$user, $artwork] = staffWithArtwork();
    $audioGuide = AudioGuide::factory()->for($artwork)->create();
    Storage::disk(AudioGuide::DISK)->put($audioGuide->path, 'audio');

    $this->actingAs($user)
        ->get(route('audio-guides.preview', $audioGuide))
        ->assertOk()
        ->assertHeader('Content-Type', 'audio/mpeg');

    $this->actingAs(User::factory()->create())
        ->get(route('audio-guides.preview', $audioGuide))
        ->assertForbidden();
});

test('staff can generate an audio guide from the description in the selected locale', function () {
    [$user, $artwork] = staffWithArtwork();
    ArtworkTranslation::factory()->for($artwork)->locale('en')->create(['description' => 'A wave off the coast.']);

    $this->mock(TextToSpeechService::class)
        ->shouldReceive('synthesize')
        ->once()
        ->with('A wave off the coast.', 'en')
        ->andReturn('mp3-audio');

    Livewire::actingAs($user)
        ->test('pages::artworks.edit', ['artwork' => $artwork])
        ->set('audioForm.locale', 'en')
        ->call('generateAudio')
        ->assertHasNoErrors();

    $audioGuide = $artwork->audioGuides()->sole();

    expect($audioGuide->locale)->toBe('en')
        ->and($audioGuide->mime_type)->toBe('audio/mpeg')
        ->and($audioGuide->size)->toBe(strlen('mp3-audio'))
        ->and($audioGuide->path)->toStartWith("audio/{$user->museum_id}/");

    expect(Storage::disk(AudioGuide::DISK)->get($audioGuide->path))->toBe('mp3-audio');
});

test('generating in a locale with an audio guide replaces it and removes the old file', function () {
    [$user, $artwork] = staffWithArtwork();
    $oldAudioGuide = AudioGuide::factory()->for($artwork)->create(['locale' => 'ja']);
    Storage::disk(AudioGuide::DISK)->put($oldAudioGuide->path, 'old');

    $this->mock(TextToSpeechService::class)
        ->shouldReceive('synthesize')
        ->once()
        ->andReturn('new');

    Livewire::actingAs($user)
        ->test('pages::artworks.edit', ['artwork' => $artwork])
        ->call('generateAudio')
        ->assertHasNoErrors();

    $audioGuide = $artwork->audioGuides()->sole();

    expect($audioGuide->path)->not->toBe($oldAudioGuide->path);
    Storage::disk(AudioGuide::DISK)->assertMissing($oldAudioGuide->path);
    Storage::disk(AudioGuide::DISK)->assertExists($audioGuide->path);
});

test('audio guide is not generated without a description in that exact locale', function () {
    [$user, $artwork] = staffWithArtwork();

    $this->mock(TextToSpeechService::class)->shouldNotReceive('synthesize');

    Livewire::actingAs($user)
        ->test('pages::artworks.edit', ['artwork' => $artwork])
        ->set('audioForm.locale', 'en')
        ->call('generateAudio')
        ->assertHasErrors('audioForm.locale');

    expect(AudioGuide::count())->toBe(0);
});

test('nothing is saved when text to speech fails', function () {
    [$user, $artwork] = staffWithArtwork();

    $this->mock(TextToSpeechService::class)
        ->shouldReceive('synthesize')
        ->andThrow(new ConnectionException('timeout'));

    Livewire::actingAs($user)
        ->test('pages::artworks.edit', ['artwork' => $artwork])
        ->call('generateAudio')
        ->assertOk();

    expect(AudioGuide::count())->toBe(0)
        ->and(Storage::disk(AudioGuide::DISK)->allFiles())->toBeEmpty();
});

test('staff cannot generate an audio guide for an artwork of another museum', function () {
    [$user] = staffWithArtwork();
    $otherArtwork = Artwork::factory()->create();

    $this->mock(TextToSpeechService::class)->shouldNotReceive('synthesize');

    Livewire::actingAs($user)
        ->test('pages::artworks.edit', ['artwork' => $otherArtwork])
        ->assertForbidden();
});

test('text to speech sends the text and language to ElevenLabs with the configured voice and model', function (string $locale, string $languageCode) {
    config([
        'services.elevenlabs.key' => 'test-key',
        'services.elevenlabs.voice_id' => 'voice-1',
        'services.elevenlabs.japanese_voice_id' => null,
        'services.elevenlabs.model_id' => 'eleven_v4',
    ]);
    Http::fake(['api.elevenlabs.io/*' => Http::response('mp3-audio')]);

    expect(app(TextToSpeechService::class)->synthesize('こんにちは', $locale))->toEndWith('mp3-audio');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.elevenlabs.io/v1/text-to-speech/voice-1?output_format=mp3_44100_128'
        && $request->hasHeader('xi-api-key', 'test-key')
        && $request['text'] === 'こんにちは'
        && $request['model_id'] === 'eleven_v4'
        && $request['language_code'] === $languageCode);
})->with([
    'Japanese' => ['ja', 'ja'],
    'Simplified Chinese' => ['zh-Hans', 'zh'],
]);

test('text to speech uses the Japanese voice for Japanese and the default voice for other languages', function (string $locale, string $voiceId) {
    config([
        'services.elevenlabs.voice_id' => 'voice-default',
        'services.elevenlabs.japanese_voice_id' => 'voice-ja',
    ]);
    Http::fake(['api.elevenlabs.io/*' => Http::response('mp3-audio')]);

    app(TextToSpeechService::class)->synthesize('こんにちは', $locale);

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}?"));
})->with([
    'Japanese' => ['ja', 'voice-ja'],
    'English' => ['en', 'voice-default'],
    'Simplified Chinese' => ['zh-Hans', 'voice-default'],
]);

test('text to speech falls back to the default voice for Japanese when no Japanese voice is configured', function () {
    config([
        'services.elevenlabs.voice_id' => 'voice-default',
        'services.elevenlabs.japanese_voice_id' => null,
    ]);
    Http::fake(['api.elevenlabs.io/*' => Http::response('mp3-audio')]);

    app(TextToSpeechService::class)->synthesize('こんにちは', 'ja');

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.elevenlabs.io/v1/text-to-speech/voice-default?'));
});

test('text to speech adds about half a second of silent frames before the speech', function () {
    Http::fake(['api.elevenlabs.io/*' => Http::response('mp3-audio')]);

    $audio = app(TextToSpeechService::class)->synthesize('こんにちは', 'ja');

    expect(strlen($audio))->toBe(20 * 417 + strlen('mp3-audio'))
        ->and($audio)->toStartWith("\xFF\xFB\x90\xC4")
        ->and(substr($audio, 19 * 417, 4))->toBe("\xFF\xFB\x90\xC4")
        ->and($audio)->toEndWith('mp3-audio');
});

test('text to speech adds the silence after the ID3 tag', function () {
    $tag = 'ID3'."\x04\x00\x00"."\x00\x00\x00\x05".'abcde';
    Http::fake(['api.elevenlabs.io/*' => Http::response($tag.'mp3-audio')]);

    $audio = app(TextToSpeechService::class)->synthesize('こんにちは', 'ja');

    expect($audio)->toStartWith($tag."\xFF\xFB\x90\xC4")
        ->and($audio)->toEndWith('mp3-audio')
        ->and(strlen($audio))->toBe(strlen($tag) + 20 * 417 + strlen('mp3-audio'));
});

test('text to speech does not send the language to a model that rejects it', function () {
    config(['services.elevenlabs.model_id' => 'eleven_multilingual_v2']);
    Http::fake(['api.elevenlabs.io/*' => Http::response('mp3-audio')]);

    app(TextToSpeechService::class)->synthesize('こんにちは', 'ja');

    Http::assertSent(fn (Request $request) => ! array_key_exists('language_code', $request->data()));
});

test('generating in the default locale reads its own description, not a translation', function () {
    [$user, $artwork] = staffWithArtwork();
    $artwork->translations()->where('locale', 'ja')->delete();
    ArtworkTranslation::factory()->for($artwork)->locale('ja')->create(['description' => '沖の波。']);
    ArtworkTranslation::factory()->for($artwork)->locale('en')->create(['description' => 'A wave off the coast.']);

    $this->mock(TextToSpeechService::class)
        ->shouldReceive('synthesize')
        ->once()
        ->with('沖の波。', 'ja')
        ->andReturn('mp3-audio');

    Livewire::actingAs($user)
        ->test('pages::artworks.edit', ['artwork' => $artwork])
        ->call('generateAudio')
        ->assertHasNoErrors();

    expect($artwork->audioGuides()->sole()->locale)->toBe('ja');
});

test('audio guide is not generated while the description in that locale has unsaved changes', function (string $locale, string $property) {
    [$user, $artwork] = staffWithArtwork();
    ArtworkTranslation::factory()->for($artwork)->locale('en')->create(['description' => 'A wave off the coast.']);

    $this->mock(TextToSpeechService::class)->shouldNotReceive('synthesize');

    Livewire::actingAs($user)
        ->test('pages::artworks.edit', ['artwork' => $artwork])
        ->set($property, 'Not saved yet.')
        ->set('audioForm.locale', $locale)
        ->call('generateAudio')
        ->assertHasErrors('audioForm.locale');

    expect(AudioGuide::count())->toBe(0);
})->with([
    'default locale' => ['ja', 'form.description'],
    'translation' => ['en', 'translationForm.description'],
]);

<?php

use App\Models\Artwork;
use App\Models\AudioGuide;
use App\Models\Exhibition;
use App\Models\Museum;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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

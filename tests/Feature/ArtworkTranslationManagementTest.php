<?php

use App\Models\Artwork;
use App\Models\ArtworkTranslation;
use App\Models\Exhibition;
use App\Models\Museum;
use App\Models\User;
use Livewire\Livewire;

/**
 * Create a staff user whose museum supports Japanese (default), English and Korean, with an artwork in it.
 *
 * @return array{0: User, 1: Artwork}
 */
function staffWithMultilingualArtwork(): array
{
    $museum = Museum::factory()->create(['locales' => ['ja', 'en', 'ko'], 'default_locale' => 'ja']);
    $user = User::factory()->for($museum)->create();

    return [$user, Artwork::factory()->for(Exhibition::factory()->for($museum))->create()];
}

test('staff can add a translation in a non-default locale', function () {
    [$user, $artwork] = staffWithMultilingualArtwork();

    Livewire::actingAs($user)
        ->test('pages::artworks.edit', ['artwork' => $artwork])
        ->assertSet('translationForm.locale', 'en')
        ->set('translationForm.title', 'Great Wave')
        ->set('translationForm.artist', 'Hokusai')
        ->set('translationForm.productionYear', 'c. 1831')
        ->set('translationForm.description', 'A wave off the coast.')
        ->call('saveTranslation')
        ->assertHasNoErrors();

    $translation = $artwork->translations()->where('locale', 'en')->sole();

    expect($translation->title)->toBe('Great Wave')
        ->and($translation->artist)->toBe('Hokusai')
        ->and($translation->production_year)->toBe('c. 1831')
        ->and($translation->description)->toBe('A wave off the coast.');
});

test('switching the locale fills the form with its translation, which can be replaced', function () {
    [$user, $artwork] = staffWithMultilingualArtwork();
    ArtworkTranslation::factory()->for($artwork)->locale('ko')->create(['title' => '파도', 'description' => '옛 설명']);

    Livewire::actingAs($user)
        ->test('pages::artworks.edit', ['artwork' => $artwork])
        ->set('translationForm.locale', 'ko')
        ->assertSet('translationForm.title', '파도')
        ->assertSet('translationForm.description', '옛 설명')
        ->set('translationForm.description', '새 설명')
        ->call('saveTranslation')
        ->assertHasNoErrors();

    expect($artwork->translations()->where('locale', 'ko')->sole()->description)->toBe('새 설명')
        ->and($artwork->translations()->where('locale', 'ja')->sole()->description)->not->toBe('새 설명');
});

test('translations are validated and nothing is saved', function (array $input, string $field) {
    [$user, $artwork] = staffWithMultilingualArtwork();
    $defaultTitle = $artwork->translations()->where('locale', 'ja')->value('title');

    Livewire::actingAs($user)
        ->test('pages::artworks.edit', ['artwork' => $artwork])
        ->set('translationForm.title', 'Great Wave')
        ->set($input)
        ->call('saveTranslation')
        ->assertHasErrors($field);

    expect($artwork->translations()->count())->toBe(1)
        ->and($artwork->translations()->where('locale', 'ja')->value('title'))->toBe($defaultTitle);
})->with([
    'title is required' => [['translationForm.title' => ''], 'translationForm.title'],
    'title is at most 255 characters' => [['translationForm.title' => str_repeat('a', 256)], 'translationForm.title'],
    'description is at most 10000 characters' => [['translationForm.description' => str_repeat('a', 10001)], 'translationForm.description'],
    'default locale is edited with the artwork' => [['translationForm.locale' => 'ja', 'translationForm.title' => 'Great Wave'], 'translationForm.locale'],
    'locale must be supported by the museum' => [['translationForm.locale' => 'zh-Hans', 'translationForm.title' => 'Great Wave'], 'translationForm.locale'],
]);

test('staff can delete a translation', function () {
    [$user, $artwork] = staffWithMultilingualArtwork();
    ArtworkTranslation::factory()->for($artwork)->locale('en')->create();

    Livewire::actingAs($user)
        ->test('pages::artworks.edit', ['artwork' => $artwork])
        ->call('deleteTranslation')
        ->assertHasNoErrors()
        ->assertSet('translationForm.title', '');

    expect($artwork->translations()->where('locale', 'en')->exists())->toBeFalse()
        ->and($artwork->translations()->where('locale', 'ja')->exists())->toBeTrue();
});

test('staff cannot edit translations of an artwork of another museum', function () {
    [$user] = staffWithMultilingualArtwork();

    Livewire::actingAs($user)
        ->test('pages::artworks.edit', ['artwork' => Artwork::factory()->create()])
        ->assertForbidden();
});

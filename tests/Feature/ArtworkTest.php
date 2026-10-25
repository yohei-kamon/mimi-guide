<?php

use App\Models\Artwork;
use App\Models\ArtworkTranslation;
use App\Models\Exhibition;
use App\Models\Museum;
use Illuminate\Database\QueryException;

test('a random 12 character slug is generated on create and kept on update', function () {
    $artwork = Artwork::factory()->create();
    $slug = $artwork->slug;

    $artwork->update(['sort_order' => 5]);

    expect($slug)->toMatch('/^[a-z0-9]{12}$/')
        ->and($artwork->fresh()->slug)->toBe($slug);
});

test('translation falls back to the museum default locale', function () {
    $museum = Museum::factory()->create(['locales' => ['ja', 'en'], 'default_locale' => 'ja']);
    $artwork = Artwork::factory()
        ->for(Exhibition::factory()->for($museum))
        ->has(ArtworkTranslation::factory()->locale('ja')->state(['title' => '富士']), 'translations')
        ->has(ArtworkTranslation::factory()->locale('en')->state(['title' => 'Mount Fuji']), 'translations')
        ->create();

    expect($artwork->title('en'))->toBe('Mount Fuji')
        ->and($artwork->title('ko'))->toBe('富士');
});

test('ordered scope sorts by sort order, then guide number', function () {
    $exhibition = Exhibition::factory()->create();
    $third = Artwork::factory()->for($exhibition)->create(['sort_order' => 2, 'guide_number' => 1]);
    $second = Artwork::factory()->for($exhibition)->create(['sort_order' => 1, 'guide_number' => 9]);
    $first = Artwork::factory()->for($exhibition)->create(['sort_order' => 1, 'guide_number' => 3]);

    expect($exhibition->artworks()->ordered()->pluck('id')->all())
        ->toBe([$first->id, $second->id, $third->id]);
});

test('an exhibition with artworks cannot be deleted', function () {
    $artwork = Artwork::factory()->create();

    expect(fn () => $artwork->exhibition->delete())->toThrow(QueryException::class);
});

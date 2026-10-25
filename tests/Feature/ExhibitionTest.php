<?php

use App\Models\Exhibition;
use App\Models\ExhibitionTranslation;
use App\Models\Museum;
use Illuminate\Database\QueryException;

test('a random 12 character slug is generated on create and kept on update', function () {
    $exhibition = Exhibition::factory()->create();
    $slug = $exhibition->slug;

    $exhibition->update(['starts_on' => today()]);

    expect($slug)->toMatch('/^[a-z0-9]{12}$/')
        ->and($exhibition->fresh()->slug)->toBe($slug);
});

test('translation falls back to the museum default locale', function () {
    $museum = Museum::factory()->create(['locales' => ['ja', 'en'], 'default_locale' => 'ja']);
    $exhibition = Exhibition::factory()->for($museum)
        ->has(ExhibitionTranslation::factory()->locale('ja')->state(['name' => '常設展']), 'translations')
        ->has(ExhibitionTranslation::factory()->locale('en')->state(['name' => 'Permanent']), 'translations')
        ->create();

    expect($exhibition->name('en'))->toBe('Permanent')
        ->and($exhibition->name('ko'))->toBe('常設展');
});

test('visible scope returns only published exhibitions within the period', function () {
    $permanent = Exhibition::factory()->published()->create();
    $current = Exhibition::factory()->published()->create(['starts_on' => today(), 'ends_on' => today()]);
    Exhibition::factory()->create();
    Exhibition::factory()->unpublished()->create();
    Exhibition::factory()->published()->ended()->create();
    Exhibition::factory()->published()->create(['starts_on' => today()->addDay()]);

    expect(Exhibition::visible()->pluck('id')->sort()->values()->all())
        ->toBe([$permanent->id, $current->id]);
});

test('a museum with exhibitions cannot be deleted', function () {
    $exhibition = Exhibition::factory()->create();

    expect(fn () => $exhibition->museum->delete())->toThrow(QueryException::class);
});

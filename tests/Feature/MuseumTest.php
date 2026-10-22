<?php

use App\Models\Museum;
use App\Models\MuseumTranslation;
use App\Models\User;
use Illuminate\Database\QueryException;

function bilingualMuseum(): Museum
{
    return Museum::factory()
        ->has(MuseumTranslation::factory()->locale('ja')->state(['name' => 'ミミ美術館']), 'translations')
        ->has(MuseumTranslation::factory()->locale('en')->state(['name' => 'Mimi Museum']), 'translations')
        ->create(['locales' => ['ja', 'en'], 'default_locale' => 'ja']);
}

test('translation returns the requested locale', function () {
    expect(bilingualMuseum()->name('en'))->toBe('Mimi Museum');
});

test('translation falls back to the default locale', function () {
    expect(bilingualMuseum()->name('ko'))->toBe('ミミ美術館');
});

test('factory adds the default locale translation when only another locale is given', function () {
    $museum = Museum::factory()
        ->has(MuseumTranslation::factory()->locale('en'), 'translations')
        ->create(['locales' => ['ja', 'en'], 'default_locale' => 'ja']);

    expect($museum->translations()->pluck('locale')->sort()->values()->all())->toBe(['en', 'ja']);
});

test('deleting a museum deletes its translations', function () {
    $museum = Museum::factory()->create();

    $museum->delete();

    expect(MuseumTranslation::count())->toBe(0);
});

test('a museum with staff cannot be deleted', function () {
    $user = User::factory()->create();

    expect(fn () => $user->museum->delete())->toThrow(QueryException::class);
});

test('active scope excludes stopped museums', function () {
    $active = Museum::factory()->create();
    Museum::factory()->inactive()->create();

    expect(Museum::active()->pluck('id')->all())->toBe([$active->id]);
});

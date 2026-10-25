<?php

use App\Enums\PublishStatus;
use App\Models\Artwork;
use App\Models\Exhibition;
use App\Models\Museum;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('artworks.index', Exhibition::factory()->create()))->assertRedirect(route('login'));
});

test('administrators cannot open the artwork screens', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('artworks.index', Exhibition::factory()->create()))->assertForbidden();
});

test('staff see the artworks of their exhibition in playback order', function () {
    $user = User::factory()->create();
    $exhibition = Exhibition::factory()->for($user->museum)->create();
    $second = Artwork::factory()->for($exhibition)->create(['sort_order' => 2]);
    $first = Artwork::factory()->for($exhibition)->create(['sort_order' => 1]);

    $this->actingAs($user)
        ->get(route('artworks.index', $exhibition))
        ->assertOk()
        ->assertSeeInOrder([$first->title(), $second->title()]);
});

test('staff can add an artwork to their exhibition', function () {
    $user = User::factory()->create();
    $exhibition = Exhibition::factory()->for($user->museum)->create();
    Artwork::factory()->for($exhibition)->create(['guide_number' => 4]);

    Livewire::actingAs($user)
        ->test('pages::artworks.create', ['exhibition' => $exhibition])
        ->assertSet('form.guideNumber', '5')
        ->set('form.title', '富嶽三十六景')
        ->set('form.artist', '葛飾北斎')
        ->set('form.productionYear', '1831年頃')
        ->set('form.description', '富士山を描いた連作')
        ->set('form.sortOrder', '3')
        ->set('form.status', PublishStatus::Published->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('artworks.index', $exhibition));

    $artwork = $exhibition->artworks()->where('guide_number', 5)->sole();
    $translation = $artwork->translation();

    expect($artwork->sort_order)->toBe(3)
        ->and($artwork->status)->toBe(PublishStatus::Published)
        ->and($artwork->slug)->toMatch('/^[a-z0-9]{12}$/')
        ->and($translation->title)->toBe('富嶽三十六景')
        ->and($translation->artist)->toBe('葛飾北斎')
        ->and($translation->production_year)->toBe('1831年頃');
});

test('the guide number must be unique within the exhibition only', function () {
    $user = User::factory()->create();
    $exhibition = Exhibition::factory()->for($user->museum)->create();
    $otherExhibition = Exhibition::factory()->for($user->museum)->create();
    Artwork::factory()->for($exhibition)->create(['guide_number' => 1]);

    Livewire::actingAs($user)
        ->test('pages::artworks.create', ['exhibition' => $exhibition])
        ->set('form.title', '重複')
        ->set('form.guideNumber', '1')
        ->call('save')
        ->assertHasErrors('form.guideNumber');

    Livewire::actingAs($user)
        ->test('pages::artworks.create', ['exhibition' => $otherExhibition])
        ->set('form.title', '別の展示')
        ->set('form.guideNumber', '1')
        ->call('save')
        ->assertHasNoErrors();
});

test('artwork input is validated', function (array $input, string $field) {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::artworks.create', ['exhibition' => Exhibition::factory()->for($user->museum)->create()])
        ->fill(['form.title' => '作品', ...$input])
        ->call('save')
        ->assertHasErrors($field);
})->with([
    'title is required' => [['form.title' => ''], 'form.title'],
    'title is at most 255 characters' => [['form.title' => str_repeat('あ', 256)], 'form.title'],
    'artist is at most 255 characters' => [['form.artist' => str_repeat('あ', 256)], 'form.artist'],
    'guide number is at least 1' => [['form.guideNumber' => '0'], 'form.guideNumber'],
    'sort order is not negative' => [['form.sortOrder' => '-1'], 'form.sortOrder'],
]);

test('staff can update their artwork, keeping its own guide number and slug', function () {
    $user = User::factory()->create();
    $artwork = Artwork::factory()->for(Exhibition::factory()->for($user->museum))->create(['guide_number' => 7]);
    $slug = $artwork->slug;

    Livewire::actingAs($user)
        ->test('pages::artworks.edit', ['artwork' => $artwork])
        ->assertSet('form.guideNumber', '7')
        ->set('form.title', '新しい作品名')
        ->call('save')
        ->assertHasNoErrors();

    $artwork->refresh();

    expect($artwork->title())->toBe('新しい作品名')
        ->and($artwork->slug)->toBe($slug)
        ->and($artwork->translations()->count())->toBe(1);
});

test('staff can toggle publishing and delete their artwork', function () {
    $user = User::factory()->create();
    $artwork = Artwork::factory()->for(Exhibition::factory()->for($user->museum))->create();

    $component = Livewire::actingAs($user)->test('pages::artworks.edit', ['artwork' => $artwork]);

    $component->call('togglePublish');
    expect($artwork->fresh()->status)->toBe(PublishStatus::Published);

    $component->call('togglePublish');
    expect($artwork->fresh()->status)->toBe(PublishStatus::Unpublished);

    $component->call('deleteArtwork')->assertRedirect(route('artworks.index', $artwork->exhibition));
    expect(Artwork::count())->toBe(0);
});

test('staff cannot open another museum artwork screens', function (string $route) {
    $this->actingAs(User::factory()->create());
    $artwork = Artwork::factory()->create();

    $url = $route === 'artworks.edit'
        ? route($route, $artwork)
        : route($route, $artwork->exhibition);

    $this->get($url)->assertForbidden();
})->with(['artworks.index', 'artworks.create', 'artworks.edit']);

test('adding an artwork is forbidden after the user loses access to the exhibition', function () {
    $user = User::factory()->create();
    $exhibition = Exhibition::factory()->for($user->museum)->create();

    $component = Livewire::actingAs($user)
        ->test('pages::artworks.create', ['exhibition' => $exhibition])
        ->set('form.title', '作品');

    $user->museum()->associate(Museum::factory()->create())->save();

    $component->call('save')->assertForbidden();

    expect($exhibition->artworks()->count())->toBe(0);
});

test('actions are forbidden after the user loses access to the artwork', function (string $method) {
    $user = User::factory()->create();
    $artwork = Artwork::factory()->for(Exhibition::factory()->for($user->museum))->create();

    $component = Livewire::actingAs($user)->test('pages::artworks.edit', ['artwork' => $artwork]);

    $user->museum()->associate(Museum::factory()->create())->save();

    $component->call($method)->assertForbidden();

    expect($artwork->fresh())->not->toBeNull()
        ->and($artwork->fresh()->status)->toBe(PublishStatus::Draft);
})->with(['save', 'togglePublish', 'deleteArtwork']);

test('an exhibition with artworks is not deleted and the staff is told why', function () {
    $user = User::factory()->create();
    $artwork = Artwork::factory()->for(Exhibition::factory()->for($user->museum))->create();

    Livewire::actingAs($user)
        ->test('pages::exhibitions.edit', ['exhibition' => $artwork->exhibition])
        ->call('deleteExhibition')
        ->assertNoRedirect();

    expect($artwork->exhibition->fresh())->not->toBeNull();
});

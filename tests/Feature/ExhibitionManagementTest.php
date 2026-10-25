<?php

use App\Enums\PublishStatus;
use App\Models\Exhibition;
use App\Models\Museum;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('exhibitions.index'))->assertRedirect(route('login'));
});

test('administrators cannot open the exhibition screens', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('exhibitions.index'))->assertForbidden();
});

test('staff see only their own museum exhibitions', function () {
    $user = User::factory()->create();
    $own = Exhibition::factory()->for($user->museum)->create();
    $other = Exhibition::factory()->create();

    $this->actingAs($user)
        ->get(route('exhibitions.index'))
        ->assertOk()
        ->assertSee($own->name())
        ->assertDontSee($other->name());
});

test('staff can create an exhibition in their own museum', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::exhibitions.create')
        ->set('form.name', '企画展 浮世絵')
        ->set('form.description', '北斎と広重')
        ->set('form.startsOn', '2026-11-01')
        ->set('form.endsOn', '2026-12-31')
        ->set('form.status', PublishStatus::Published->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('exhibitions.index'));

    $exhibition = Exhibition::sole();

    expect($exhibition->museum_id)->toBe($user->museum_id)
        ->and($exhibition->status)->toBe(PublishStatus::Published)
        ->and($exhibition->ends_on->toDateString())->toBe('2026-12-31')
        ->and($exhibition->slug)->toMatch('/^[a-z0-9]{12}$/')
        ->and($exhibition->name())->toBe('企画展 浮世絵');
});

test('the museum is taken from the user, not from the request', function () {
    $user = User::factory()->create();
    $otherMuseum = Museum::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::exhibitions.create')
        ->set('form.name', '常設展')
        ->call('save');

    expect(Exhibition::sole()->museum_id)->toBe($user->museum_id)
        ->and($otherMuseum->exhibitions()->count())->toBe(0);
});

test('exhibition input is validated', function (array $input, string $field) {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::exhibitions.create')
        ->fill(['form.name' => '常設展', ...$input])
        ->call('save')
        ->assertHasErrors($field);
})->with([
    'name is required' => [['form.name' => ''], 'form.name'],
    'name is at most 255 characters' => [['form.name' => str_repeat('あ', 256)], 'form.name'],
    'end date is not before the start date' => [['form.startsOn' => '2026-12-01', 'form.endsOn' => '2026-11-30'], 'form.endsOn'],
    'status must be a known value' => [['form.status' => 'archived'], 'form.status'],
]);

test('staff can update their exhibition without changing the slug', function () {
    $user = User::factory()->create();
    $exhibition = Exhibition::factory()->for($user->museum)->create();
    $slug = $exhibition->slug;

    Livewire::actingAs($user)
        ->test('pages::exhibitions.edit', ['exhibition' => $exhibition])
        ->set('form.name', '新しい展示名')
        ->set('form.endsOn', '')
        ->call('save')
        ->assertHasNoErrors();

    $exhibition->refresh();

    expect($exhibition->name())->toBe('新しい展示名')
        ->and($exhibition->slug)->toBe($slug)
        ->and($exhibition->translations()->count())->toBe(1);
});

test('staff can toggle publishing and delete their exhibition', function () {
    $user = User::factory()->create();
    $exhibition = Exhibition::factory()->for($user->museum)->create();

    $component = Livewire::actingAs($user)->test('pages::exhibitions.edit', ['exhibition' => $exhibition]);

    $component->call('togglePublish');
    expect($exhibition->fresh()->status)->toBe(PublishStatus::Published);

    $component->call('togglePublish');
    expect($exhibition->fresh()->status)->toBe(PublishStatus::Unpublished);

    $component->call('deleteExhibition')->assertRedirect(route('exhibitions.index'));
    expect(Exhibition::count())->toBe(0);
});

test('staff cannot open another museum exhibition', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('exhibitions.edit', Exhibition::factory()->create()))->assertForbidden();
});

test('actions are forbidden after the user loses access to the exhibition', function (string $method) {
    $user = User::factory()->create();
    $exhibition = Exhibition::factory()->for($user->museum)->create();

    $component = Livewire::actingAs($user)->test('pages::exhibitions.edit', ['exhibition' => $exhibition]);

    $user->museum()->associate(Museum::factory()->create())->save();

    $component->call($method)->assertForbidden();

    expect($exhibition->fresh())->not->toBeNull()
        ->and($exhibition->fresh()->status)->toBe(PublishStatus::Draft);
})->with(['save', 'togglePublish', 'deleteExhibition']);

<?php

use App\Models\Museum;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('admin.museums.index'))->assertRedirect(route('login'));
});

test('staff cannot open the museum screens', function (string $routeName) {
    $museum = Museum::factory()->create();

    $this->actingAs(User::factory()->create());

    $this->get(route($routeName, $routeName === 'admin.museums.edit' ? $museum : []))->assertForbidden();
})->with(['admin.museums.index', 'admin.museums.create', 'admin.museums.edit']);

test('administrators see all museums', function () {
    $active = Museum::factory()->paid(800)->create();
    $inactive = Museum::factory()->inactive()->create();
    User::factory()->count(2)->for($active)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.museums.index'))
        ->assertOk()
        ->assertSee($active->name())
        ->assertSee($inactive->name())
        ->assertSee('800円')
        ->assertSee('2人');
});

test('administrators can register a museum', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.museums.create')
        ->set('form.name', '岩手山麓美術館')
        ->set('form.slug', 'iwate-sanroku')
        ->set('form.locales', ['en', 'ja'])
        ->set('form.defaultLocale', 'ja')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.museums.index'));

    $museum = Museum::sole();

    expect($museum->slug)->toBe('iwate-sanroku')
        ->and($museum->locales)->toBe(['ja', 'en'])
        ->and($museum->default_locale)->toBe('ja')
        ->and($museum->is_active)->toBeTrue()
        ->and($museum->isFree())->toBeTrue()
        ->and($museum->name())->toBe('岩手山麓美術館');
});

test('museum input is validated', function (array $input, string $field) {
    Museum::factory()->create(['slug' => 'taken']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.museums.create')
        ->fill(['form.name' => '岩手山麓美術館', 'form.slug' => 'iwate', ...$input])
        ->call('save')
        ->assertHasErrors($field);
})->with([
    'name is required' => [['form.name' => ''], 'form.name'],
    'slug uses lowercase letters, digits and hyphens' => [['form.slug' => 'Iwate_Museum'], 'form.slug'],
    'slug does not end with a hyphen' => [['form.slug' => 'iwate-'], 'form.slug'],
    'slug is unique' => [['form.slug' => 'taken'], 'form.slug'],
    'at least one locale is required' => [['form.locales' => []], 'form.locales'],
    'locales must be configured' => [['form.locales' => ['ja', 'fr']], 'form.locales.1'],
    'default locale is one of the locales' => [['form.locales' => ['ja'], 'form.defaultLocale' => 'en'], 'form.defaultLocale'],
]);

test('administrators can update a museum and keep its own slug', function () {
    $museum = Museum::factory()->create(['slug' => 'iwate']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.museums.edit', ['museum' => $museum])
        ->set('form.name', '新しい館名')
        ->call('save')
        ->assertHasNoErrors();

    $museum->refresh();

    expect($museum->slug)->toBe('iwate')
        ->and($museum->name())->toBe('新しい館名')
        ->and($museum->translations()->count())->toBe(1);
});

test('changing the default locale saves the name in the new default locale', function () {
    $museum = Museum::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.museums.edit', ['museum' => $museum])
        ->set('form.locales', ['ja', 'en'])
        ->set('form.defaultLocale', 'en')
        ->set('form.name', 'Iwate Museum')
        ->call('save')
        ->assertHasNoErrors();

    $museum->refresh();

    expect($museum->default_locale)->toBe('en')
        ->and($museum->name())->toBe('Iwate Museum')
        ->and($museum->translations()->count())->toBe(2);
});

test('administrators can stop and resume a museum', function () {
    $museum = Museum::factory()->create();
    $admin = User::factory()->admin()->create();

    $list = Livewire::actingAs($admin)->test('pages::admin.museums.index');

    $list->call('toggleActive', $museum->id);
    expect($museum->fresh()->is_active)->toBeFalse();

    $list->call('toggleActive', $museum->id);
    expect($museum->fresh()->is_active)->toBeTrue();

    Livewire::actingAs($admin)
        ->test('pages::admin.museums.edit', ['museum' => $museum])
        ->call('toggleActive');
    expect($museum->fresh()->is_active)->toBeFalse();
});

test('actions are forbidden after the user loses the administrator role', function () {
    $museum = Museum::factory()->create();
    $user = User::factory()->admin()->create();

    $component = Livewire::actingAs($user)->test('pages::admin.museums.edit', ['museum' => $museum]);

    $user->forceFill(['role' => 'staff', 'museum_id' => $museum->id])->save();

    $component->call('toggleActive')->assertForbidden();

    expect($museum->fresh()->is_active)->toBeTrue();
});

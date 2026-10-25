<?php

use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('museum.settings'))->assertRedirect(route('login'));
});

test('administrators cannot open the museum settings', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('museum.settings'))
        ->assertForbidden();
});

test('staff can set the guide price of their museum', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::museum.settings')
        ->assertSet('guidePrice', '0')
        ->set('guidePrice', '800')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->museum->fresh()->guide_price)->toBe(800);
});

test('staff can make their museum free again', function () {
    $user = User::factory()->create();
    $user->museum->update(['guide_price' => 800]);

    Livewire::actingAs($user)
        ->test('pages::museum.settings')
        ->set('guidePrice', '0')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->museum->fresh()->isFree())->toBeTrue();
});

test('the guide price must be free or a chargeable whole number of yen', function (string $price) {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::museum.settings')
        ->set('guidePrice', $price)
        ->call('save')
        ->assertHasErrors('guidePrice');

    expect($user->museum->fresh()->guide_price)->toBe(0);
})->with([
    'empty' => '',
    'negative' => '-100',
    'decimal' => '500.5',
    'below the Stripe minimum' => '49',
    'too high' => '100001',
]);

test('staff can only set the price of their own museum', function () {
    $user = User::factory()->create();
    $otherStaff = User::factory()->create();

    expect($user->can('updatePrice', $user->museum))->toBeTrue()
        ->and($user->can('updatePrice', $otherStaff->museum))->toBeFalse()
        ->and($user->can('update', $user->museum))->toBeFalse();
});

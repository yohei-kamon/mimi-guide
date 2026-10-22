<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'role:admin'])->get('/_test/admin', fn () => 'ok');
    Route::middleware(['web', 'auth', 'role:admin,staff'])->get('/_test/either', fn () => 'ok');
});

test('role middleware allows the permitted role', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/_test/admin')->assertOk()->assertSee('ok');
});

test('role middleware forbids other roles', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/_test/admin')->assertForbidden();
});

test('role middleware redirects guests to login', function () {
    $this->get('/_test/admin')->assertRedirect(route('login'));
});

test('role middleware accepts any of multiple roles', function (bool $admin) {
    $user = $admin ? User::factory()->admin()->create() : User::factory()->create();

    $this->actingAs($user)->get('/_test/either')->assertOk();
})->with([
    'admin' => true,
    'staff' => false,
]);

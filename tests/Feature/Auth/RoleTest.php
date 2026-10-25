<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

test('staff are redirected to the dashboard after login', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
});

test('admins are redirected to the museum list after login', function () {
    $user = User::factory()->admin()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/admin/museums');
});

test('admins can not visit the staff dashboard', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('dashboard'))->assertForbidden();
});

test('registration is not available', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'admin',
    ])->assertNotFound();

    $this->assertDatabaseMissing('users', ['email' => 'admin@example.com']);
});

test('role can not be mass assigned', function () {
    $user = User::create([
        'name' => 'Test',
        'email' => 'test@example.com',
        'password' => 'password',
        'role' => 'admin',
    ]);

    expect($user->fresh()->role)->toBe(UserRole::Staff);
});

test('role defaults to staff when the column is omitted', function () {
    DB::table('users')->insert([
        'name' => 'Raw',
        'email' => 'raw@example.com',
        'password' => 'x',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(User::where('email', 'raw@example.com')->first()->role)->toBe(UserRole::Staff);
});

test('users are returned to the intended url after login', function (bool $admin) {
    $user = $admin ? User::factory()->admin()->create() : User::factory()->create();

    $this->get(route('profile.edit'))->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('profile.edit', absolute: false));
})->with([
    'staff' => false,
    'admin' => true,
]);

test('users are redirected by role after two factor challenge', function (bool $admin, string $expected) {
    $user = ($admin ? User::factory()->admin() : User::factory())->withTwoFactor()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'));

    $this->post(route('two-factor.login.store'), [
        'recovery_code' => 'recovery-code-1',
    ])->assertRedirect($expected);

    $this->assertAuthenticatedAs($user);
})->with([
    'admin' => [true, '/admin/museums'],
    'staff' => [false, '/dashboard'],
]);

test('authenticated users visiting the login page are redirected by role', function (bool $admin, string $expected) {
    $user = $admin ? User::factory()->admin()->create() : User::factory()->create();

    $this->actingAs($user)->get(route('login'))->assertRedirect($expected);
})->with([
    'admin' => [true, '/admin/museums'],
    'staff' => [false, '/dashboard'],
]);

test('admins are redirected to the museum list after verifying their email', function () {
    $user = User::factory()->admin()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)->assertRedirect('/admin/museums?verified=1');
});

test('the dashboard menu is shown only to staff', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('profile.edit'))
        ->assertDontSee(route('dashboard'));

    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertSee(route('dashboard'));
});

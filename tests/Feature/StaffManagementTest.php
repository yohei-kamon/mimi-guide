<?php

use App\Enums\UserRole;
use App\Mail\StaffInvitation;
use App\Models\Museum;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
});

test('administrators see the staff of a museum', function () {
    $museum = Museum::factory()->create();
    $staff = User::factory()->for($museum)->create(['name' => '山田 花子']);
    User::factory()->create(['name' => '他館の職員']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.staff.index', $museum))
        ->assertOk()
        ->assertSee('山田 花子')
        ->assertSee($staff->email)
        ->assertDontSee('他館の職員');
});

test('administrators can issue a staff account and the invitation is sent', function () {
    $museum = Museum::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.staff.create', ['museum' => $museum])
        ->set('form.name', '山田 花子')
        ->set('form.email', 'hanako@example.com')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.staff.index', $museum));

    $user = User::where('email', 'hanako@example.com')->sole();

    expect($user->role)->toBe(UserRole::Staff)
        ->and($user->museum_id)->toBe($museum->id)
        ->and($user->name)->toBe('山田 花子')
        ->and($user->email_verified_at)->toBeNull();

    Mail::assertSent(StaffInvitation::class, fn (StaffInvitation $mail) => $mail->hasTo('hanako@example.com')
        && Password::broker()->tokenExists($user, $mail->token));
});

test('the invitation holds the museum, the login email and the link to set the password', function () {
    $user = User::factory()->unverified()->create();

    $mail = new StaffInvitation($user, 'invitation-token');

    $mail->assertSeeInHtml($user->museum->name())
        ->assertSeeInHtml($user->email)
        ->assertSeeInHtml(route('password.reset', ['token' => 'invitation-token', 'email' => $user->email]), escape: false);
});

test('setting the password from the invitation lets the staff member in with a verified email', function () {
    $user = User::factory()->unverified()->create();
    $token = Password::broker()->createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

test('the staff email must be valid and unused', function (string $email) {
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.staff.create', ['museum' => Museum::factory()->create()])
        ->set('form.name', '山田 花子')
        ->set('form.email', $email)
        ->call('save')
        ->assertHasErrors('form.email');

    Mail::assertNothingSent();
})->with([
    'taken' => 'taken@example.com',
    'invalid' => 'not-an-email',
    'empty' => '',
]);

test('administrators can resend the invitation to a staff member of the museum only', function () {
    $museum = Museum::factory()->create();
    $invited = User::factory()->for($museum)->unverified()->create();
    $otherMuseumStaff = User::factory()->unverified()->create();

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.staff.index', ['museum' => $museum])
        ->call('resendInvitation', $invited->id);

    Mail::assertSent(StaffInvitation::class, fn (StaffInvitation $mail) => $mail->hasTo($invited->email));

    $component->call('resendInvitation', $otherMuseumStaff->id)->assertNotFound();

    Mail::assertSentCount(1);
});

test('staff cannot open the staff screens', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.staff.index', $user->museum))->assertForbidden();
    $this->actingAs($user)->get(route('admin.staff.create', $user->museum))->assertForbidden();
});

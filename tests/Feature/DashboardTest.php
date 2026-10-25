<?php

use App\Models\Artwork;
use App\Models\ArtworkTranslation;
use App\Models\AudioGuide;
use App\Models\Exhibition;
use App\Models\GuidePass;
use App\Models\Museum;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('administrators cannot open the dashboard', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertForbidden();
});

test('the dashboard counts only the exhibitions and artworks of the staff member\'s museum', function () {
    $user = User::factory()->create();
    $exhibitions = Exhibition::factory()->for($user->museum)->count(2)->create();
    Artwork::factory()->for($exhibitions[0])->count(2)->create();
    Artwork::factory()->for($exhibitions[1])->create();
    Artwork::factory()->count(4)->create();

    $component = Livewire::actingAs($user)->test('pages::dashboard');

    expect($component->instance()->exhibitionCount)->toBe(2)
        ->and($component->instance()->artworks)->toHaveCount(3);
});

test('the dashboard lists the artworks missing a translation or audio in the museum locales', function () {
    $museum = Museum::factory()->create(['locales' => ['ja', 'en']]);
    $user = User::factory()->for($museum)->create();
    $exhibition = Exhibition::factory()->for($museum)->create();

    $complete = Artwork::factory()->for($exhibition)->create();
    ArtworkTranslation::factory()->for($complete)->locale('en')->create();
    AudioGuide::factory()->for($complete)->create();
    AudioGuide::factory()->for($complete)->locale('en')->create();
    $complete->translation()->update(['title' => '揃っている作品']);

    $incomplete = Artwork::factory()->for($exhibition)->create();
    AudioGuide::factory()->for($incomplete)->create();
    $incomplete->translation()->update(['title' => '英語が足りない作品']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('英語が足りない作品')
        ->assertSee('翻訳: English')
        ->assertSee('音声: English')
        ->assertDontSee('揃っている作品')
        ->assertSee(route('artworks.edit', $incomplete), false);
});

test('the dashboard shows the passes sold and the total of the staff member\'s museum only', function () {
    $user = User::factory()->create();
    GuidePass::factory()->for($user->museum)->paid()->create(['amount' => 800]);
    GuidePass::factory()->for($user->museum)->expired()->create(['amount' => 1200]);
    GuidePass::factory()->for($user->museum)->create(['amount' => 5000]);
    GuidePass::factory()->paid()->create(['amount' => 9000]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('2枚')
        ->assertSee('2,000円')
        ->assertDontSee('9,000円')
        ->assertDontSee('7,000円');
});

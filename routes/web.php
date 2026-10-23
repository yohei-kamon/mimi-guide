<?php

use App\Http\Controllers\AudioGuideController;
use App\Http\Controllers\GuidePassController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::livewire('m/{museum:slug}', 'pages::visitor.museum')->name('visitor.museum');
Route::livewire('e/{exhibition:slug}', 'pages::visitor.exhibition')->name('visitor.exhibition');
Route::livewire('e/{exhibition:slug}/play', 'pages::visitor.play')->name('visitor.exhibition.play');
Route::livewire('a/{artwork:slug}', 'pages::visitor.artwork')->name('visitor.artwork');
Route::get('a/{artwork:slug}/audio', [AudioGuideController::class, 'show'])->name('visitor.artwork.audio');
Route::post('m/{museum:slug}/passes', [GuidePassController::class, 'store'])->middleware('throttle:10,1')->name('visitor.passes.store');
Route::livewire('passes/complete', 'pages::visitor.pass-complete')->name('visitor.passes.complete');
Route::get('passes/{token}', [GuidePassController::class, 'show'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:30,1')->name('visitor.passes.show');
Route::post('stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::middleware(['auth', 'verified', 'role:staff'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('exhibitions', 'pages::exhibitions.index')->name('exhibitions.index');
    Route::livewire('exhibitions/create', 'pages::exhibitions.create')->name('exhibitions.create');
    Route::livewire('exhibitions/{exhibition}/edit', 'pages::exhibitions.edit')->name('exhibitions.edit');
    Route::livewire('exhibitions/{exhibition}/qr', 'pages::exhibitions.qr')->name('exhibitions.qr');

    Route::livewire('exhibitions/{exhibition}/artworks', 'pages::artworks.index')->name('artworks.index');
    Route::livewire('exhibitions/{exhibition}/artworks/create', 'pages::artworks.create')->name('artworks.create');
    Route::livewire('artworks/{artwork}/edit', 'pages::artworks.edit')->name('artworks.edit');
    Route::livewire('artworks/{artwork}/qr', 'pages::artworks.qr')->name('artworks.qr');

    Route::livewire('museum/settings', 'pages::museum.settings')->name('museum.settings');

    Route::get('audio-guides/{audioGuide}', [AudioGuideController::class, 'preview'])->name('audio-guides.preview');
});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('museums', 'pages::admin.museums.index')->name('museums.index');
    Route::livewire('museums/create', 'pages::admin.museums.create')->name('museums.create');
    Route::livewire('museums/{museum}/edit', 'pages::admin.museums.edit')->name('museums.edit');
});

require __DIR__.'/settings.php';

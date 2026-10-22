<?php

use App\Http\Controllers\AudioGuideController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::livewire('m/{museum:slug}', 'pages::visitor.museum')->name('visitor.museum');
Route::livewire('e/{exhibition:slug}', 'pages::visitor.exhibition')->name('visitor.exhibition');
Route::livewire('a/{artwork:slug}', 'pages::visitor.artwork')->name('visitor.artwork');
Route::get('a/{artwork:slug}/audio', [AudioGuideController::class, 'show'])->name('visitor.artwork.audio');

Route::middleware(['auth', 'verified', 'role:staff'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('exhibitions', 'pages::exhibitions.index')->name('exhibitions.index');
    Route::livewire('exhibitions/create', 'pages::exhibitions.create')->name('exhibitions.create');
    Route::livewire('exhibitions/{exhibition}/edit', 'pages::exhibitions.edit')->name('exhibitions.edit');

    Route::livewire('exhibitions/{exhibition}/artworks', 'pages::artworks.index')->name('artworks.index');
    Route::livewire('exhibitions/{exhibition}/artworks/create', 'pages::artworks.create')->name('artworks.create');
    Route::livewire('artworks/{artwork}/edit', 'pages::artworks.edit')->name('artworks.edit');

    Route::get('audio-guides/{audioGuide}', [AudioGuideController::class, 'preview'])->name('audio-guides.preview');
});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('museums', 'pages::admin.museums.index')->name('museums.index');
    Route::livewire('museums/create', 'pages::admin.museums.create')->name('museums.create');
    Route::livewire('museums/{museum}/edit', 'pages::admin.museums.edit')->name('museums.edit');
});

require __DIR__.'/settings.php';

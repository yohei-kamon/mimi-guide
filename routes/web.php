<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', 'role:staff'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('exhibitions', 'pages::exhibitions.index')->name('exhibitions.index');
    Route::livewire('exhibitions/create', 'pages::exhibitions.create')->name('exhibitions.create');
    Route::livewire('exhibitions/{exhibition}/edit', 'pages::exhibitions.edit')->name('exhibitions.edit');

    Route::livewire('exhibitions/{exhibition}/artworks', 'pages::artworks.index')->name('artworks.index');
    Route::livewire('exhibitions/{exhibition}/artworks/create', 'pages::artworks.create')->name('artworks.create');
    Route::livewire('artworks/{artwork}/edit', 'pages::artworks.edit')->name('artworks.edit');
});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('museums', 'pages::admin.museums.index')->name('museums.index');
    Route::livewire('museums/create', 'pages::admin.museums.create')->name('museums.create');
    Route::livewire('museums/{museum}/edit', 'pages::admin.museums.edit')->name('museums.edit');
});

require __DIR__.'/settings.php';

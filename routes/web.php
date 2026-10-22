<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', 'role:staff'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('exhibitions', 'pages::exhibitions.index')->name('exhibitions.index');
    Route::livewire('exhibitions/create', 'pages::exhibitions.create')->name('exhibitions.create');
    Route::livewire('exhibitions/{exhibition}/edit', 'pages::exhibitions.edit')->name('exhibitions.edit');
});

require __DIR__.'/settings.php';

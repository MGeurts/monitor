<?php

use App\Livewire\StockLookup;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('stock-lookup', StockLookup::class)->name('stock-lookup');
});

require __DIR__.'/settings.php';

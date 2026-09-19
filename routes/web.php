<?php

use App\Http\Controllers\DashboardController;
use App\Livewire\Stock\BatchRunPage;
use App\Livewire\Stock\EanLookup;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::livewire('stock/lookup', EanLookup::class)->name('stock.lookup');
    Route::livewire('stock/batch', BatchRunPage::class)->name('stock.batch');
});

require __DIR__.'/settings.php';

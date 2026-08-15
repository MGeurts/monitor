<?php

use App\Livewire\StockLookup;
use App\Models\StockSource;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('requires authentication', function () {
    $this->get(route('stock-lookup'))->assertRedirect(route('login'));
});

it('aggregates results from every active source', function () {
    Http::fake([
        'api.onlinefact.be/*' => Http::response([
            'results' => [['product_id' => '3', 'reference' => 'JUPILER25CL', 'stock' => '4.00']],
            'succes' => 1,
        ]),
    ]);

    $user = User::factory()->create();
    StockSource::factory()->onlinefact()->create(['label' => 'Onlinefact ERP']);
    StockSource::factory()->onlinefact()->inactive()->create(['label' => 'Disabled source']);

    Livewire::actingAs($user)
        ->test(StockLookup::class)
        ->set('ean', '6937186640697')
        ->call('search')
        ->assertSet('searched', true)
        ->assertSee('Onlinefact ERP')
        ->assertDontSee('Disabled source');
});

it('validates the ean before searching', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(StockLookup::class)
        ->set('ean', 'abc')
        ->call('search')
        ->assertHasErrors(['ean']);
});

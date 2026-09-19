<div class="flex w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('Product lookup') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('Look up a product by Onlinefact reference or barcode and compare it across every connected source.') }}
            </flux:text>
        </div>

        <x-card>
            <form wire:submit="lookup" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <x-input
                        wire:model="ean"
                        label="{{ __('Reference or barcode') }}"
                        placeholder="e.g. CIDN68010 or 8719505560000"
                        autofocus
                        clearable
                    />
                </div>

                <x-button type="submit" loading="lookup" primary>
                    {{ __('Check stock') }}
                </x-button>
            </form>
        </x-card>

        @if ($candidates)
            <x-card>
                <div>
                    <flux:heading size="xl" class="text-red-600 dark:text-red-400">{{ __('Multiple Onlinefact products found') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ __('This barcode occurs on more than one product. Select the product to check; no channel has been queried yet.') }}
                    </flux:text>
                </div>

                <div class="mt-4 overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-zinc-500">{{ __('Reference') }}</th>
                                <th class="px-4 py-2 text-left font-medium text-zinc-500">{{ __('Description') }}</th>
                                <th class="px-4 py-2 text-left font-medium text-zinc-500">{{ __('Product ID') }}</th>
                                <th class="px-4 py-2 text-right font-medium text-zinc-500">{{ __('Stock') }}</th>
                                <th class="px-4 py-2"><span class="sr-only">{{ __('Select') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($candidates as $candidate)
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ $candidate['reference'] ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $candidate['description'] ?? '—' }}</td>
                                    <td class="px-4 py-3 font-mono text-zinc-600 dark:text-zinc-300">{{ $candidate['product_id'] }}</td>
                                    <td class="px-4 py-3 text-right font-mono">{{ $candidate['stock'] === null ? '—' : rtrim(rtrim(number_format($candidate['stock'], 2, '.', ''), '0'), '.') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <x-button wire:click="selectProduct({{ $candidate['product_id'] }})" loading="selectProduct" primary>
                                            {{ __('Select') }}
                                        </x-button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <flux:text class="mt-4 text-amber-700 dark:text-amber-300">
                    {{ __('Products with the same EAN should be avoided. Please check the affected products and adjust them in Onlinefact where necessary.') }}
                </flux:text>
            </x-card>
        @endif

        @if ($check)
            <div wire:loading.remove wire:target="lookup">
            <x-card>
                @php
                    $masterResult = collect($check['results'])->firstWhere('is_master', true);
                    $product = $masterResult['metadata'] ?? [];
                    $location = implode(' · ', array_filter([
                        $product['location'] ?? null,
                        $product['alternative_location'] ?? null,
                    ]));
                    $barcode = trim((string) ($product['barcode'] ?? $check['ean']));
                    $eanColor = static fn ($value): string => blank($value)
                        ? ''
                        : (trim((string) $value) === $barcode
                        ? 'text-green-600 dark:text-green-400'
                        : 'text-red-600 dark:text-red-400');
                    $priceColor = static fn ($value, $reference): string => $value !== null && $reference !== null && abs((float) $value - (float) $reference) > 0.004
                        ? 'text-red-600 dark:text-red-400'
                        : '';
                    $channelPrice = static function (array $result, array $product): ?float {
                        if ($result['is_master']) {
                            return null;
                        }

                        return match (true) {
                            $result['source_key'] === 'dekookwinkel' => $product['price_incl'] ?? null,
                            $result['source_key'] === 'koraly', ($result['metadata']['shop'] ?? null) === 'koraly' => $product['price_incl_3'] ?? null,
                            $result['source_key'] === 'outlet_elektro', ($result['metadata']['shop'] ?? null) === 'outlet_elektro' => $product['price_incl_2'] ?? null,
                            default => null,
                        };
                    };
                    $allInSync = collect($check['results'])
                        ->reject(fn (array $result): bool => $result['is_master'])
                        ->every(function (array $result) use ($channelPrice, $product): bool {
                            if ($result['error'] || ! $result['found'] || (float) ($result['diff'] ?? 0) !== 0.0) {
                                return false;
                            }

                            $expectedPrice = $channelPrice($result, $product);

                            return $expectedPrice === null || ($result['price'] !== null && abs((float) $result['price'] - (float) $expectedPrice) <= 0.004);
                        });
                @endphp

                <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:heading size="lg">{{ $masterResult['description'] ?? $check['ean'] }}</flux:heading>
                            @if ($allInSync)
                                <x-badge text="{{ __('in sync') }}" color="green" sm />
                            @endif
                        </div>
                        <flux:text class="mt-1">
                            {{ __('Onlinefact product') }}{{ isset($product['product_id']) ? ' #'.$product['product_id'] : '' }}
                        </flux:text>
                    </div>

                </div>

                <div class="mb-4 grid gap-4 lg:grid-cols-3">
                    <section class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 lg:col-span-2">
                        <flux:heading size="sm">{{ __('Product details') }}</flux:heading>
                        <dl class="mt-3 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <dt class="text-xs text-zinc-500">{{ __('Description') }}</dt>
                                <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $masterResult['description'] ?? '—' }}</dd>
                            </div>
                            <div><dt class="text-xs text-zinc-500">{{ __('Reference') }}</dt><dd class="font-semibold">{{ $masterResult['sku'] ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-zinc-500">{{ __('Barcode') }}</dt><dd class="font-semibold">{{ $barcode }}</dd></div>
                            <div><dt class="text-xs text-zinc-500">{{ __('EAN Koraly') }}</dt><dd class="font-semibold {{ $eanColor($product['ean_koraly'] ?? null) }}">{{ $product['ean_koraly'] ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-zinc-500">{{ __('EAN Outlet') }}</dt><dd class="font-semibold {{ $eanColor($product['ean_outlet'] ?? null) }}">{{ $product['ean_outlet'] ?? '—' }}</dd></div>
                            @if (filled($product['unit'] ?? null))<div><dt class="text-xs text-zinc-500">{{ __('Unit') }}</dt><dd class="font-semibold">{{ $product['unit'] }}</dd></div>@endif
                            @if (($product['tax'] ?? null) !== null)<div><dt class="text-xs text-zinc-500">{{ __('VAT') }}</dt><dd class="font-semibold">{{ rtrim(rtrim(number_format($product['tax'], 2, '.', ''), '0'), '.') }}%</dd></div>@endif
                            @if (filled($product['supplier'] ?? null))<div class="sm:col-span-2"><dt class="text-xs text-zinc-500">{{ __('Supplier') }}</dt><dd class="font-semibold">{{ $product['supplier'] }}</dd></div>@endif
                        </dl>
                    </section>

                    <section class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:heading size="sm">{{ __('Stock management') }}</flux:heading>
                        <dl class="mt-3 space-y-3 text-sm">
                            <div class="flex items-baseline justify-between gap-3"><dt class="text-zinc-500">{{ __('Stock') }}</dt><dd class="text-lg font-semibold">{{ $check['master_stock'] === null ? '—' : rtrim(rtrim(number_format($check['master_stock'], 2, '.', ''), '0'), '.') }}</dd></div>
                            @if (($product['stock_minimum'] ?? null) !== null)<div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Minimum') }}</dt><dd class="font-medium">{{ rtrim(rtrim(number_format($product['stock_minimum'], 2, '.', ''), '0'), '.') }}</dd></div>@endif
                            @if (filled($location))<div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Location') }}</dt><dd class="text-right font-medium">{{ $location }}</dd></div>@endif
                            @if (($product['webshop'] ?? null) !== null)<div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Visible on webshop') }}</dt><dd class="font-medium">{{ $product['webshop'] ? __('yes') : __('no') }}</dd></div>@endif
                        </dl>
                    </section>
                </div>

                @if (($product['price_incl'] ?? null) !== null || ($product['price_incl_2'] ?? null) !== null || ($product['price_incl_3'] ?? null) !== null)
                    <section class="mb-5 rounded-lg border border-zinc-200 px-4 py-3 text-sm dark:border-zinc-700">
                        <div class="flex flex-wrap items-center gap-y-2" style="column-gap: 4rem; row-gap: 0.5rem;">
                            <span class="font-medium">{{ __('Prices') }}</span>
                            @if (($product['price_incl'] ?? null) !== null)
                                <span><span class="text-zinc-500">{{ __('Prijs') }}</span> <strong>€ {{ number_format($product['price_incl'], 2, ',', '.') }}</strong></span>
                            @endif
                            @if (($product['price_incl_3'] ?? null) !== null)
                                <span><span class="text-zinc-500">{{ __('BOL Koraly') }}</span> <strong>€ {{ number_format($product['price_incl_3'], 2, ',', '.') }}</strong></span>
                            @endif
                            @if (($product['price_incl_2'] ?? null) !== null)
                                <span><span class="text-zinc-500">{{ __('BOL Outlet') }}</span> <strong>€ {{ number_format($product['price_incl_2'], 2, ',', '.') }}</strong></span>
                            @endif
                        </div>
                    </section>
                @endif

                <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-[960px] divide-y divide-zinc-200 text-xs sm:min-w-full sm:text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60">
                            <tr>
                                <th class="px-2 py-1.5 text-left font-medium text-zinc-500 sm:px-4 sm:py-2 dark:text-zinc-400">{{ __('Bron') }}</th>
                                <th class="px-2 py-1.5 text-left font-medium text-zinc-500 sm:px-4 sm:py-2 dark:text-zinc-400">{{ __('Barcode/Referentie') }}</th>
                                <th class="px-2 py-1.5 text-right font-medium text-zinc-500 sm:px-4 sm:py-2 dark:text-zinc-400">{{ __('Voorraad') }}</th>
                                <th class="px-2 py-1.5 text-right font-medium text-zinc-500 sm:px-4 sm:py-2 dark:text-zinc-400">{{ __('Verschil') }}</th>
                                <th class="px-2 py-1.5 text-right font-medium text-zinc-500 sm:px-4 sm:py-2 dark:text-zinc-400">{{ __('Prijs') }}</th>
                                <th class="px-2 py-1.5 text-right font-medium text-zinc-500 sm:px-4 sm:py-2 dark:text-zinc-400">{{ __('Prijs Koraly') }}</th>
                                <th class="px-2 py-1.5 text-right font-medium text-zinc-500 sm:px-4 sm:py-2 dark:text-zinc-400">{{ __('Prijs Outlet') }}</th>
                                <th class="px-2 py-1.5 text-center font-medium text-zinc-500 sm:px-4 sm:py-2 dark:text-zinc-400">{{ __('Published') }}</th>
                                <th class="px-2 py-1.5 text-left font-medium text-zinc-500 sm:px-4 sm:py-2 dark:text-zinc-400">{{ __('Status') }}</th>
                                <th class="px-2 py-1.5 text-right font-medium text-zinc-500 sm:px-4 sm:py-2 dark:text-zinc-400"><span class="sr-only">{{ __('API response') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($check['results'] as $result)
                                <tr class="{{ $result['is_master'] ? 'bg-zinc-50 dark:bg-zinc-800/40' : '' }}">
                                    @php
                                        $bolEan = filled($result['metadata']['ean'] ?? null) ? trim((string) $result['metadata']['ean']) : null;
                                        $bolSearchUrl = $result['group'] === 'Bol.com' && $result['found'] && $bolEan
                                            ? 'https://www.bol.com/be/nl/s/?searchtext='.rawurlencode($bolEan)
                                            : null;
                                    @endphp
                                    <td class="max-w-28 px-2 py-1.5 sm:max-w-none sm:px-4 sm:py-2">
                                        <div class="truncate font-medium text-zinc-900 dark:text-zinc-100">
                                            @if (filled($result['metadata']['product_url'] ?? null))
                                                <a href="{{ $result['metadata']['product_url'] }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline dark:text-indigo-400">
                                                    {{ $result['source_label'] }} <span aria-hidden="true">↗</span>
                                                </a>
                                            @elseif ($bolSearchUrl)
                                                <a href="{{ $bolSearchUrl }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline dark:text-indigo-400">
                                                    {{ $result['source_label'] }} <span aria-hidden="true">↗</span>
                                                </a>
                                            @else
                                                {{ $result['source_label'] }}
                                            @endif
                                            @if ($result['is_master'])
                                                <x-badge text="{{ __('master') }}" color="indigo" sm />
                                            @endif
                                        </div>
                                        @if ($result['group'] === 'Bol.com')
                                            <div class="hidden text-xs text-zinc-500 sm:block">
                                                <strong>
                                                    Bol.com
                                                    @if (filled($result['metadata']['retailer_id'] ?? null))
                                                        ({{ $result['metadata']['retailer_id'] }})
                                                    @endif
                                                </strong>
                                            </div>
                                        @else
                                            <div class="hidden text-xs text-zinc-500 sm:block">{{ $result['group'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-2 py-1.5 text-zinc-600 sm:px-4 sm:py-2 dark:text-zinc-300">
                                        @if ($result['group'] === 'Bol.com')
                                            <div><span class="text-zinc-500">{{ __('EAN') }}:</span> {{ $result['metadata']['ean'] ?? '—' }}</div>
                                        @else
                                            <div><span class="text-zinc-500">{{ __('Barcode') }}:</span> {{ $result['metadata']['ean'] ?? $result['metadata']['barcode'] ?? $check['ean'] }}</div>
                                            <div class="mt-0.5"><span class="text-zinc-500">{{ __('Referentie') }}:</span> {{ $result['sku'] ?? '—' }}</div>
                                        @endif
                                    </td>
                                    <td class="px-2 py-1.5 text-right font-mono sm:px-4 sm:py-2 {{ ! $result['is_master'] && $result['found'] && (float) ($result['diff'] ?? 0) !== 0.0 ? 'text-red-600 dark:text-red-400' : '' }}">
                                        {{ $result['found'] ? rtrim(rtrim(number_format($result['stock'], 2, '.', ''), '0'), '.') : '—' }}
                                    </td>
                                    <td class="px-2 py-1.5 text-right font-mono sm:px-4 sm:py-2">
                                        @if ($result['diff'] !== null)
                                            <span class="{{ (float) $result['diff'] === 0.0 ? 'text-zinc-500' : 'text-red-600 dark:text-red-400' }}">
                                                {{ (float) $result['diff'] > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($result['diff'], 2, '.', ''), '0'), '.') }}
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-2 py-1.5 text-right font-mono sm:px-4 sm:py-2">
                                        @if ($result['is_master'] && ($result['metadata']['price_incl'] ?? null) !== null)
                                            € {{ number_format($result['metadata']['price_incl'], 2, ',', '.') }}
                                        @elseif ($result['source_key'] === 'dekookwinkel' && $result['price'] !== null)
                                            <span class="{{ $priceColor($result['price'], $product['price_incl'] ?? null) }}">€ {{ number_format($result['price'], 2, ',', '.') }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-2 py-1.5 text-right font-mono sm:px-4 sm:py-2">
                                        @if ($result['is_master'] && ($result['metadata']['price_incl_3'] ?? null) !== null)
                                            € {{ number_format($result['metadata']['price_incl_3'], 2, ',', '.') }}
                                        @elseif (($result['source_key'] === 'koraly' || ($result['metadata']['shop'] ?? null) === 'koraly') && $result['price'] !== null)
                                            <span class="{{ $priceColor($result['price'], $product['price_incl_3'] ?? null) }}">€ {{ number_format($result['price'], 2, ',', '.') }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-2 py-1.5 text-right font-mono sm:px-4 sm:py-2">
                                        @if ($result['is_master'] && ($result['metadata']['price_incl_2'] ?? null) !== null)
                                            € {{ number_format($result['metadata']['price_incl_2'], 2, ',', '.') }}
                                        @elseif (($result['source_key'] === 'outlet_elektro' || ($result['metadata']['shop'] ?? null) === 'outlet_elektro') && $result['price'] !== null)
                                            <span class="{{ $priceColor($result['price'], $product['price_incl_2'] ?? null) }}">€ {{ number_format($result['price'], 2, ',', '.') }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-2 py-1.5 text-center text-lg sm:px-4 sm:py-2">
                                        @if (! in_array($result['group'], ['Bol.com', 'WooCommerce'], true) || $result['error'])
                                            <span class="text-zinc-400">—</span>
                                        @elseif (($result['metadata']['published'] ?? false) === true)
                                            <span class="font-bold text-green-600 dark:text-green-400" title="{{ __('yes') }}" aria-label="{{ __('Published') }}: {{ __('yes') }}">✓</span>
                                        @else
                                            <span class="font-bold text-red-600 dark:text-red-400" title="{{ __('no') }}" aria-label="{{ __('Published') }}: {{ __('no') }}">✕</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-1.5 sm:px-4 sm:py-2">
                                        @if ($result['error'])
                                            <x-badge text="{{ __('error') }}" color="red" sm />
                                        @elseif (! $result['found'])
                                            <x-badge text="{{ __('not found') }}" color="zinc" sm />
                                        @elseif ($result['is_master'])
                                            <x-badge text="{{ __('reference') }}" color="indigo" sm />
                                        @elseif ((float) ($result['diff'] ?? 0) === 0.0)
                                            <x-badge text="{{ __('in sync') }}" color="green" sm />
                                        @else
                                            <x-badge text="{{ __('mismatch') }}" color="amber" sm />
                                        @endif

                                        @if ($result['error'])
                                            <div class="mt-1 text-xs text-red-500">{{ $result['error'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-2 py-1.5 text-right sm:px-4 sm:py-2">
                                        @if ($result['found'] && is_array($result['metadata']['api_response'] ?? null))
                                            <flux:button size="sm" variant="filled" wire:click="openApiResponse('{{ $result['source_key'] }}')">
                                                {{ __('API JSON') }}
                                            </flux:button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
            </div>
        @endif

        <div wire:loading wire:target="lookup">
            <x-card>
                <flux:text>{{ __('Looking up product…') }}</flux:text>
            </x-card>
        </div>

        <flux:modal
            name="api-response"
            class="max-w-5xl"
            wire:model="showApiResponse"
            @close="closeApiResponse"
        >
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('API response') }}</flux:heading>
                    @if ($apiResponseSource)
                        <flux:text class="mt-1">{{ $apiResponseSource }}</flux:text>
                    @endif
                </div>

                <pre class="max-h-[65vh] overflow-auto rounded-lg bg-zinc-950 p-4 text-xs leading-5 text-zinc-100"><code>{{ json_encode($apiResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code></pre>

                <div class="flex justify-end">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>
</div>

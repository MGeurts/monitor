<div class="flex w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('EAN stock lookup') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('Look up a single EAN and compare its stock level across every connected source.') }}
            </flux:text>
        </div>

        <x-card>
            <form wire:submit="lookup" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <x-input
                        wire:model="ean"
                        label="{{ __('EAN / barcode') }}"
                        placeholder="e.g. 8719505560000"
                        autofocus
                        clearable
                    />
                </div>

                <x-button type="submit" loading="lookup" primary>
                    {{ __('Check stock') }}
                </x-button>
            </form>
        </x-card>

        @if ($check)
            <x-card>
                @php
                    $masterResult = collect($check['results'])->firstWhere('is_master', true);
                    $product = $masterResult['metadata'] ?? [];
                    $location = implode(' · ', array_filter([
                        $product['location'] ?? null,
                        $product['alternative_location'] ?? null,
                    ]));
                    $barcode = trim((string) ($product['barcode'] ?? $check['ean']));
                    $eanColor = static fn ($value): string => trim((string) $value) === $barcode
                        ? 'text-green-600 dark:text-green-400'
                        : 'text-red-600 dark:text-red-400';
                    $priceColor = static fn ($value, $reference): string => $value !== null && $reference !== null && abs((float) $value - (float) $reference) > 0.004
                        ? 'text-red-600 dark:text-red-400'
                        : '';
                @endphp

                <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ $masterResult['description'] ?? $check['ean'] }}</flux:heading>
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
                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $masterResult['description'] ?? '—' }}</dd>
                            </div>
                            <div><dt class="text-xs text-zinc-500">{{ __('Reference') }}</dt><dd class="font-medium">{{ $masterResult['sku'] ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-zinc-500">{{ __('Barcode') }}</dt><dd class="font-medium">{{ $barcode }}</dd></div>
                            @if (filled($product['ean_koraly'] ?? null))<div><dt class="text-xs text-zinc-500">{{ __('EAN Koraly') }}</dt><dd class="font-medium {{ $eanColor($product['ean_koraly']) }}">{{ $product['ean_koraly'] }}</dd></div>@endif
                            @if (filled($product['ean_outlet'] ?? null))<div><dt class="text-xs text-zinc-500">{{ __('EAN Outlet') }}</dt><dd class="font-medium {{ $eanColor($product['ean_outlet']) }}">{{ $product['ean_outlet'] }}</dd></div>@endif
                            @if (filled($product['unit'] ?? null))<div><dt class="text-xs text-zinc-500">{{ __('Unit') }}</dt><dd class="font-medium">{{ $product['unit'] }}</dd></div>@endif
                            @if (($product['tax'] ?? null) !== null)<div><dt class="text-xs text-zinc-500">{{ __('VAT') }}</dt><dd class="font-medium">{{ rtrim(rtrim(number_format($product['tax'], 2, '.', ''), '0'), '.') }}%</dd></div>@endif
                            @if (filled($product['supplier'] ?? null))<div class="sm:col-span-2"><dt class="text-xs text-zinc-500">{{ __('Supplier') }}</dt><dd class="font-medium">{{ $product['supplier'] }}</dd></div>@endif
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
                        <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                            <span class="font-medium">{{ __('Prices') }}</span>
                            @if (($product['price_incl'] ?? null) !== null)<span><span class="text-zinc-500">{{ __('Prijs') }}</span> <strong>€ {{ number_format($product['price_incl'], 2, ',', '.') }}</strong></span>@endif
                            @if (($product['price_incl_3'] ?? null) !== null)<span><span class="text-zinc-500">{{ __('BOL Koraly') }}</span> <strong>€ {{ number_format($product['price_incl_3'], 2, ',', '.') }}</strong></span>@endif
                            @if (($product['price_incl_2'] ?? null) !== null)<span><span class="text-zinc-500">{{ __('BOL Outlet') }}</span> <strong>€ {{ number_format($product['price_incl_2'], 2, ',', '.') }}</strong></span>@endif
                        </div>
                    </section>
                @endif

                <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('Source') }}</th>
                                <th class="px-4 py-2 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('SKU / reference') }}</th>
                                <th class="px-4 py-2 text-right font-medium text-zinc-500 dark:text-zinc-400">{{ __('Stock') }}</th>
                                <th class="px-4 py-2 text-right font-medium text-zinc-500 dark:text-zinc-400">{{ __('Difference') }}</th>
                                <th class="px-4 py-2 text-right font-medium text-zinc-500 dark:text-zinc-400">{{ __('Prijs') }}</th>
                                <th class="px-4 py-2 text-right font-medium text-zinc-500 dark:text-zinc-400">{{ __('Prijs Koraly') }}</th>
                                <th class="px-4 py-2 text-right font-medium text-zinc-500 dark:text-zinc-400">{{ __('Prijs Outlet') }}</th>
                                <th class="px-4 py-2 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                                <th class="px-4 py-2 text-right font-medium text-zinc-500 dark:text-zinc-400"><span class="sr-only">{{ __('API response') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($check['results'] as $result)
                                <tr class="{{ $result['is_master'] ? 'bg-zinc-50 dark:bg-zinc-800/40' : '' }}">
                                    <td class="px-4 py-2">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                            @if (filled($result['metadata']['product_url'] ?? null))
                                                <a href="{{ $result['metadata']['product_url'] }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline dark:text-indigo-400">
                                                    {{ $result['source_label'] }} <span aria-hidden="true">↗</span>
                                                </a>
                                            @else
                                                {{ $result['source_label'] }}
                                            @endif
                                            @if ($result['is_master'])
                                                <x-badge text="{{ __('master') }}" color="indigo" sm />
                                            @endif
                                        </div>
                                        <div class="text-xs text-zinc-500">{{ $result['group'] }}</div>
                                    </td>
                                    <td class="px-4 py-2 text-zinc-600 dark:text-zinc-300">
                                        <div>{{ $result['sku'] ?? '—' }}</div>
                                        @if ($result['group'] === 'WooCommerce')
                                            <div class="mt-0.5 text-xs text-zinc-500">
                                                {{ __('EAN') }}{{ filled($result['metadata']['ean_field'] ?? null) ? ' ('.$result['metadata']['ean_field'].')' : '' }}: {{ $result['metadata']['ean'] ?? '—' }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono">
                                        {{ $result['found'] ? rtrim(rtrim(number_format($result['stock'], 2, '.', ''), '0'), '.') : '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono">
                                        @if ($result['diff'] !== null)
                                            <span class="{{ (float) $result['diff'] === 0.0 ? 'text-zinc-500' : ((float) $result['diff'] < 0 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">
                                                {{ (float) $result['diff'] > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($result['diff'], 2, '.', ''), '0'), '.') }}
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono">
                                        @if ($result['is_master'] && ($result['metadata']['price_incl'] ?? null) !== null)
                                            € {{ number_format($result['metadata']['price_incl'], 2, ',', '.') }}
                                        @elseif ($result['source_key'] === 'dekookwinkel' && $result['price'] !== null)
                                            <span class="{{ $priceColor($result['price'], $product['price_incl'] ?? null) }}">€ {{ number_format($result['price'], 2, ',', '.') }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono">
                                        @if ($result['is_master'] && ($result['metadata']['price_incl_3'] ?? null) !== null)
                                            € {{ number_format($result['metadata']['price_incl_3'], 2, ',', '.') }}
                                        @elseif (($result['source_key'] === 'koraly' || ($result['metadata']['shop'] ?? null) === 'koraly') && $result['price'] !== null)
                                            <span class="{{ $priceColor($result['price'], $product['price_incl_3'] ?? null) }}">€ {{ number_format($result['price'], 2, ',', '.') }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono">
                                        @if ($result['is_master'] && ($result['metadata']['price_incl_2'] ?? null) !== null)
                                            € {{ number_format($result['metadata']['price_incl_2'], 2, ',', '.') }}
                                        @elseif (($result['source_key'] === 'outlet_elektro' || ($result['metadata']['shop'] ?? null) === 'outlet_elektro') && $result['price'] !== null)
                                            <span class="{{ $priceColor($result['price'], $product['price_incl_2'] ?? null) }}">€ {{ number_format($result['price'], 2, ',', '.') }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
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
                                    <td class="px-4 py-2 text-right">
                                        @if ($result['found'] && is_array($result['metadata']['api_response'] ?? null))
                                            <flux:button size="sm" variant="primary" wire:click="openApiResponse('{{ $result['source_key'] }}')">
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
        @endif

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

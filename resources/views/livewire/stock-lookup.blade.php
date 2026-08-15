<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Stock lookup') }}</flux:heading>
        <flux:subheading>{{ __('Compare stock levels across all connected sources for a single EAN.') }}</flux:subheading>
    </div>

    <form wire:submit="search" class="flex items-end gap-3">
        <flux:input
            wire:model="ean"
            label="{{ __('EAN / barcode') }}"
            placeholder="6937186640697"
            autofocus
            class="max-w-xs"
        />

        <flux:button
            type="submit"
            variant="primary"
            icon="magnifying-glass"
            wire:loading.attr="disabled"
            wire:target="search"
        >
            {{ __('Search') }}
        </flux:button>
    </form>

    @if ($searched)
        <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-start text-sm">
                <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('Source') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('Stock') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('SKU / reference') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($results as $result)
                        <tr wire:key="{{ $result->sourceKey }}">
                            <td class="px-4 py-3 font-medium">{{ $result->sourceLabel }}</td>
                            <td class="px-4 py-3">
                                @if ($result->error)
                                    <flux:badge color="red" size="sm">{{ __('Error') }}</flux:badge>
                                @elseif (! $result->found)
                                    <flux:badge color="zinc" size="sm">{{ __('Not found') }}</flux:badge>
                                @else
                                    <flux:badge color="green" size="sm">{{ __('OK') }}</flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($result->quantity !== null)
                                    {{ rtrim(rtrim(number_format($result->quantity, 2, '.', ''), '0'), '.') }}
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">
                                {{ $result->sku ?? '—' }}
                                @if ($result->error)
                                    <div class="text-xs text-red-500">{{ $result->error }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No stock sources are configured yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>

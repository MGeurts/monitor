<div
    class="flex w-full flex-1 flex-col gap-6"
    @if ($this->isLive) wire:poll.2s @endif
>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('Batch lookup') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Check every EAN in one go and watch results come in live.') }}
                </flux:text>
            </div>

            @if ($this->recentRuns->isNotEmpty())
                <x-select
                    wire:model.live="batchRunId"
                    :options="$this->recentRuns->map(fn ($run) => [
                        'value' => $run->id,
                        'label' => $run->created_at->format('d/m/Y H:i').' — '.$run->status.' ('.$run->processed.'/'.$run->total.')',
                    ])"
                    option-value="value"
                    option-label="label"
                    placeholder="{{ __('Select a run') }}"
                    class="min-w-72"
                />
            @endif
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <x-card class="lg:col-span-1">
                <flux:heading size="lg">{{ __('Start a new run') }}</flux:heading>

                <div class="mt-4 space-y-4">
                    <div>
                        <flux:text class="mb-2 font-medium">{{ __('All products from Onlinefact') }}</flux:text>
                        <flux:text class="mb-2 text-xs">{{ __('Pulls every EAN known to Onlinefact and checks it against every source.') }}</flux:text>
                        <x-button wire:click="startFromErp" loading="startFromErp" primary block>
                            {{ __('Launch full comparison') }}
                        </x-button>
                    </div>

                    <hr class="border-zinc-200 dark:border-zinc-700">

                    <form wire:submit="startFromPaste" class="space-y-2">
                        <flux:text class="font-medium">{{ __('Or paste a list of EANs') }}</flux:text>
                        <x-textarea
                            wire:model="pastedEans"
                            placeholder="{{ __('One EAN per line, or separated by commas/spaces') }}"
                            rows="5"
                        />
                        <x-button type="submit" loading="startFromPaste" block>
                            {{ __('Launch this list') }}
                        </x-button>
                    </form>
                </div>
            </x-card>

            <div class="lg:col-span-2">
                @if ($this->batchRun)
                    <x-card>
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <flux:heading size="lg">
                                    {{ __('Run #:id', ['id' => $this->batchRun->id]) }}
                                    <x-badge
                                        text="{{ ucfirst($this->batchRun->status) }}"
                                        color="{{ match ($this->batchRun->status) {
                                            'completed' => 'green',
                                            'running', 'pending' => 'indigo',
                                            default => 'zinc',
                                        } }}"
                                        sm
                                    />
                                </flux:heading>
                                <flux:text class="mt-1">
                                    {{ __(':processed / :total checked · :mismatches mismatches · :errors errors', [
                                        'processed' => $this->batchRun->processed,
                                        'total' => $this->batchRun->total,
                                        'mismatches' => $this->batchRun->mismatches,
                                        'errors' => $this->batchRun->errors,
                                    ]) }}
                                </flux:text>
                            </div>

                            <div class="flex gap-2">
                                <x-button wire:click="$set('filter', 'all')" :outline="$filter !== 'all'" sm>{{ __('All') }}</x-button>
                                <x-button wire:click="$set('filter', 'mismatches')" :outline="$filter !== 'mismatches'" sm>{{ __('Mismatches') }}</x-button>
                                <x-button wire:click="$set('filter', 'errors')" :outline="$filter !== 'errors'" sm>{{ __('Errors') }}</x-button>
                                <x-button wire:click="$set('filter', 'pending')" :outline="$filter !== 'pending'" sm>{{ __('Pending') }}</x-button>
                            </div>
                        </div>

                        <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div
                                class="h-full rounded-full bg-indigo-500 transition-all"
                                style="width: {{ $this->batchRun->progressPercentage() }}%"
                            ></div>
                        </div>

                        <div class="mt-4 overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                                <thead class="bg-zinc-50 dark:bg-zinc-800/60">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('EAN') }}</th>
                                        <th class="px-4 py-2 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('Description') }}</th>
                                        <th class="px-4 py-2 text-right font-medium text-zinc-500 dark:text-zinc-400">{{ __('Onlinefact stock') }}</th>
                                        <th class="px-4 py-2 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('Per source') }}</th>
                                        <th class="px-4 py-2 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @forelse ($items ?? [] as $item)
                                        <tr>
                                            <td class="px-4 py-2 font-mono">{{ $item->ean }}</td>
                                            <td class="px-4 py-2 text-zinc-600 dark:text-zinc-300">{{ $item->description ?? '—' }}</td>
                                            <td class="px-4 py-2 text-right font-mono">
                                                {{ $item->master_stock === null ? '—' : rtrim(rtrim(number_format($item->master_stock, 2, '.', ''), '0'), '.') }}
                                            </td>
                                            <td class="px-4 py-2">
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach ($item->results ?? [] as $result)
                                                        @if (! $result['is_master'])
                                                            <span
                                                                title="{{ $result['source_label'] }}: {{ $result['error'] ?? ($result['found'] ? $result['stock'] : __('not found')) }}"
                                                                @class([
                                                                    'inline-flex items-center rounded px-1.5 py-0.5 text-xs font-mono',
                                                                    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => $result['error'],
                                                                    'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' => ! $result['error'] && ! $result['found'],
                                                                    'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' => ! $result['error'] && $result['found'] && (float) ($result['diff'] ?? 0) === 0.0,
                                                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => ! $result['error'] && $result['found'] && (float) ($result['diff'] ?? 0) !== 0.0,
                                                                ])
                                                            >
                                                                {{ $result['source_key'] }}: {{ $result['found'] ? rtrim(rtrim(number_format($result['stock'], 2, '.', ''), '0'), '.') : '✕' }}
                                                            </span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td class="px-4 py-2">
                                                @if ($item->status === 'pending')
                                                    <x-badge text="{{ __('pending') }}" color="zinc" sm />
                                                @elseif ($item->has_error)
                                                    <x-badge text="{{ __('error') }}" color="red" sm />
                                                @elseif ($item->has_mismatch)
                                                    <x-badge text="{{ __('mismatch') }}" color="amber" sm />
                                                @else
                                                    <x-badge text="{{ __('ok') }}" color="green" sm />
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-4 py-6 text-center text-zinc-500">
                                                {{ __('No items yet.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($items)
                            <div class="mt-4">
                                {{ $items->links() }}
                            </div>
                        @endif
                    </x-card>
                @else
                    <x-card>
                        <flux:text>{{ __('No batch run yet — start one on the left.') }}</flux:text>
                    </x-card>
                @endif
            </div>
        </div>
</div>

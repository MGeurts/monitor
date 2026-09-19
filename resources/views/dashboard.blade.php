<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="grid auto-rows-min gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($bolSummaries as $summary)
                <section class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-sm font-medium text-neutral-600 dark:text-neutral-300">{{ $summary['label'] }}</p>

                    @if ($summary['error'])
                        <p class="mt-5 text-sm text-amber-600 dark:text-amber-400">{{ $summary['error'] }}</p>
                    @else
                        <dl class="mt-5 grid grid-cols-2 gap-4">
                            <div>
                                <dt class="text-xs text-neutral-500">Gepubliceerd</dt>
                                <dd class="mt-1 text-3xl font-semibold tracking-tight text-teal-600 dark:text-teal-400">{{ number_format($summary['published'], 0, ',', '.') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-neutral-500">Alle offers</dt>
                                <dd class="mt-1 text-3xl font-semibold tracking-tight">{{ number_format($summary['total'], 0, ',', '.') }}</dd>
                            </div>
                        </dl>
                    @endif
                </section>
            @endforeach
        </div>
        <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
        </div>
    </div>
</x-layouts::app>

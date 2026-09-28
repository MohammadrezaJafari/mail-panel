<x-filament-widgets::widget>
    <x-filament::section heading="Mail platform health" description="Provider: {{ $provider }}">
        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($health as $component => $info)
                @php
                    $status = $info['status'] ?? 'unknown';
                    $color = match ($status) { 'ok' => 'success', 'down' => 'danger', default => 'warning' };
                @endphp
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold uppercase">{{ $component }}</span>
                        <x-filament::badge :color="$color">{{ $status }}</x-filament::badge>
                    </div>
                    @if (! empty($info['message']))
                        <p class="mt-1 truncate text-xs text-gray-500" title="{{ $info['message'] }}">{{ $info['message'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

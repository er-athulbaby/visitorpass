<x-sidebar-layout>
    <div class="mx-auto max-w-5xl px-4 py-6 md:px-8 md:py-10">
        <h1 class="text-headline-md text-on-surface">{{ __('Reception Dashboard') }}</h1>

        <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                <p class="text-label-sm text-on-surface-variant">{{ __('Total Today') }}</p>
                <p class="mt-1 text-headline-md text-on-surface">{{ $totalToday }}</p>
            </div>
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                <p class="text-label-sm text-on-surface-variant">{{ __('Currently Inside') }}</p>
                <p class="mt-1 text-headline-md text-on-surface">{{ $currentlyInside }}</p>
            </div>
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                <p class="text-label-sm text-on-surface-variant">{{ __('Checked Out') }}</p>
                <p class="mt-1 text-headline-md text-on-surface">{{ $checkedOutToday }}</p>
            </div>
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                <p class="text-label-sm text-on-surface-variant">{{ __('New Visitors') }}</p>
                <p class="mt-1 text-headline-md text-on-surface">{{ $newVisitorsToday }}</p>
            </div>
        </div>

        <div class="mt-8 rounded-xl border border-outline-variant bg-surface-container-lowest p-6">
            <h2 class="text-headline-sm text-on-surface">{{ __('Quick Registration') }}</h2>
            <a href="{{ route('visits.create') }}" class="mt-4 inline-flex items-center gap-2 rounded bg-primary px-4 py-2 text-label-md text-on-primary">
                <x-icon name="qr_code_scanner" /> {{ __('Register Visitor') }}
            </a>
        </div>

        <div class="mt-6 rounded-xl border border-outline-variant bg-surface-container-lowest p-6">
            <div class="flex items-center justify-between">
                <h2 class="text-headline-sm text-on-surface">{{ __('Recent Activity') }}</h2>
                <a href="{{ route('visits.index') }}" class="text-label-md text-primary">{{ __('View All') }}</a>
            </div>

            <div class="mt-4 divide-y divide-outline-variant">
                @forelse ($recentVisits as $visit)
                    <div class="flex items-center justify-between py-3">
                        <div>
                            <p class="text-label-md text-on-surface">{{ $visit->visitor->name }}</p>
                            <p class="text-label-sm text-on-surface-variant">
                                {{ __('Host') }}: {{ $mode === 'company' ? $visit->employee?->name : $visit->company?->name }}
                            </p>
                        </div>
                        <div class="text-end">
                            <p class="text-label-sm text-on-surface-variant">
                                {{ $visit->isOpen() ? __('Inside') : __('Checked Out') }}
                            </p>
                            <p class="text-label-sm text-on-surface-variant">{{ $visit->check_in_at->translatedFormat('g:i A') }}</p>
                        </div>
                    </div>
                @empty
                    <p class="py-4 text-body-md text-on-surface-variant">{{ __('No visitors registered yet today.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</x-sidebar-layout>

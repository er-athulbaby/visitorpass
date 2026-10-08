@php($firstName = \Illuminate\Support\Str::of(Auth::user()->name)->trim()->explode(' ')->first())

<x-sidebar-layout>
    <x-page-header :title="__('Welcome back, :name', ['name' => $firstName])" :subtitle="__('Here is today\'s visitor activity at reception.')">
        <a href="{{ route('visits.create') }}" class="btn btn-primary btn-lg">
            <x-icon name="person_add" /> {{ __('Register Visitor') }}
        </a>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 md:gap-4 xl:grid-cols-4">
        <x-stat-card :label="__('Currently Inside')" :value="$currentlyInside" icon="sensor_occupied" tone="success" :live="true" :hint="__('In the building now')" />
        <x-stat-card :label="__('Total Today')" :value="$totalToday" icon="login" tone="primary" :hint="__('Checked in since midnight')" />
        <x-stat-card :label="__('Checked Out')" :value="$checkedOutToday" icon="logout" tone="neutral" :hint="__('Visits completed today')" />
        <x-stat-card :label="__('New Visitors')" :value="$newVisitorsToday" icon="person_add" tone="info" :hint="__('First visit ever')" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="card lg:col-span-2" aria-labelledby="recent-heading">
            <div class="card-header">
                <h2 id="recent-heading" class="card-title">{{ __('Recent Activity') }}</h2>
                <a href="{{ route('history.index') }}" class="btn btn-ghost btn-sm">{{ __('View All') }} <x-icon name="arrow_forward" class="icon-directional" /></a>
            </div>

            @if ($recentVisits->isEmpty())
                <x-empty-state icon="event_available" :title="__('No visitors yet today')" :text="__('Check-ins will appear here as visitors arrive.')">
                    <a href="{{ route('visits.create') }}" class="btn btn-secondary btn-sm"><x-icon name="person_add" /> {{ __('Register Visitor') }}</a>
                </x-empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table min-w-[560px]">
                        <thead>
                            <tr>
                                <th>{{ __('Visitor') }}</th>
                                <th>{{ __('Host') }}</th>
                                <th>{{ __('Check-In') }}</th>
                                <th>{{ __('Check-Out') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentVisits as $visit)
                                <tr>
                                    <td>
                                        <p class="font-semibold text-on-surface">{{ $visit->visitor->name }}</p>
                                        @if ($visit->visitor->company_name)
                                            <p class="text-[13px] text-on-surface-variant">{{ $visit->visitor->company_name }}</p>
                                        @endif
                                    </td>
                                    <td>{{ ($mode === 'company' ? $visit->employee?->name : $visit->company?->name) ?? '—' }}</td>
                                    <td class="whitespace-nowrap">{{ $visit->check_in_at->translatedFormat('g:i A') }}</td>
                                    <td class="whitespace-nowrap">{{ $visit->check_out_at?->translatedFormat('g:i A') ?? '—' }}</td>
                                    <td>
                                        @if ($visit->isOpen())
                                            <span class="badge badge-success">{{ __('Inside') }}</span>
                                        @else
                                            <span class="badge badge-neutral">{{ __('Checked Out') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card" aria-labelledby="inside-heading">
            <div class="card-header">
                <h2 id="inside-heading" class="card-title">{{ __('Inside now') }}</h2>
                <a href="{{ route('visits.index') }}" class="btn btn-ghost btn-sm">{{ __('Manage') }} <x-icon name="arrow_forward" class="icon-directional" /></a>
            </div>

            @if ($insideNow->isEmpty())
                <x-empty-state icon="door_front" :title="__('Nobody is inside')" :text="__('Visitors who have checked in and not yet left appear here.')" />
            @else
                <ul class="divide-y divide-outline-variant">
                    @foreach ($insideNow as $visit)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-success-container text-[13px] font-semibold text-success">
                                {{ mb_strtoupper(mb_substr($visit->visitor->name, 0, 1)) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-on-surface">{{ $visit->visitor->name }}</p>
                                <p class="truncate text-[13px] text-on-surface-variant">{{ ($mode === 'company' ? $visit->employee?->name : $visit->company?->name) ?? '—' }}</p>
                            </div>
                            <p class="shrink-0 text-end text-[13px] text-on-surface-variant tabular">
                                {{ $visit->check_in_at->translatedFormat('g:i A') }}
                                <span class="block text-[12px]">{{ $visit->check_in_at->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE, short: true) }}</span>
                            </p>
                        </li>
                    @endforeach
                </ul>
                @if ($currentlyInside > $insideNow->count())
                    <a href="{{ route('visits.index') }}" class="block border-t border-outline-variant px-5 py-3 text-center text-label-md font-semibold text-primary transition-colors duration-150 hover:bg-surface-container-low">
                        {{ __('View all :count inside', ['count' => $currentlyInside]) }}
                    </a>
                @endif
            @endif
        </section>
    </div>
</x-sidebar-layout>

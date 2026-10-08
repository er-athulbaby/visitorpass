<x-sidebar-layout>
    <x-page-header :title="__('Visitors')" :subtitle="trans_choice(':count visitor is inside the building right now.|:count visitors are inside the building right now.', $openVisits->count(), ['count' => $openVisits->count()])">
        <a href="{{ route('visits.create') }}" class="btn btn-primary btn-lg">
            <x-icon name="person_add" /> {{ __('Register New Visitor') }}
        </a>
    </x-page-header>

    @if (session('status'))
        <div class="alert alert-success mb-6" role="status"><x-icon name="check_circle" class="shrink-0" /> {{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error mb-6" role="alert"><x-icon name="error" class="shrink-0" /> {{ session('error') }}</div>
    @endif

    <section class="card" aria-labelledby="open-visits-heading">
        <div class="card-header">
            <h2 id="open-visits-heading" class="card-title">{{ __('Open Visits') }}</h2>
            <span class="badge badge-success tabular">{{ $openVisits->count() }} {{ __('Inside') }}</span>
        </div>

        @if ($openVisits->isEmpty())
            <x-empty-state icon="door_front" :title="__('Nobody is inside')" :text="__('Visitors who have checked in and not yet left appear here.')">
                <a href="{{ route('visits.create') }}" class="btn btn-secondary btn-sm"><x-icon name="person_add" /> {{ __('Register Visitor') }}</a>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="data-table min-w-[640px]">
                    <thead>
                        <tr>
                            <th>{{ __('Visitor') }}</th>
                            <th>{{ __('Visiting') }}</th>
                            <th>{{ __('Checked In') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($openVisits as $visit)
                            <tr>
                                <td>
                                    <p class="font-semibold text-on-surface">{{ $visit->visitor->name }}</p>
                                    @if ($visit->visitor->company_name)
                                        <p class="text-[13px] text-on-surface-variant">{{ $visit->visitor->company_name }}</p>
                                    @endif
                                </td>
                                <td>{{ $visit->employee?->name ?? $visit->company?->name }}</td>
                                <td class="whitespace-nowrap">
                                    <span class="text-on-surface">{{ $visit->check_in_at->format('Y-m-d H:i') }}</span>
                                    <span class="block text-[12px]">{{ $visit->check_in_at->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE, short: true) }}</span>
                                </td>
                                <td><span class="badge badge-success">{{ __('Inside') }}</span></td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('visits.check-out', $visit) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-secondary btn-sm"><x-icon name="logout" class="icon-directional" /> {{ __('Check Out') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-sidebar-layout>

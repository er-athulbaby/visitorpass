<x-sidebar-layout>
    <x-page-header :title="__('Visitor History')" :subtitle="__('Comprehensive logs of all facility access events.')">
        <a href="{{ route('history.export', $filters) }}" class="btn btn-secondary"><x-icon name="download" /> {{ __('Export to CSV') }}</a>
    </x-page-header>

    <form method="GET" action="{{ route('history.index') }}" class="card card-body grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-12 lg:items-end">
        <div class="sm:col-span-2 lg:col-span-4">
            <label for="search" class="field-label">{{ __('Search') }}</label>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-on-surface-variant" />
                <input id="search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Name, CPR, or mobile...') }}" class="field ps-10">
            </div>
        </div>
        <div class="lg:col-span-2">
            <label for="date_from" class="field-label">{{ __('From') }}</label>
            <input id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="field tabular">
        </div>
        <div class="lg:col-span-2">
            <label for="date_to" class="field-label">{{ __('To') }}</label>
            <input id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="field tabular">
        </div>
        @if ($mode === 'company')
            <div class="lg:col-span-2">
                <label for="department_id" class="field-label">{{ __('Department') }}</label>
                <select id="department_id" name="department_id" class="field">
                    <option value="">{{ __('All Departments') }}</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected(($filters['department_id'] ?? null) == $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
        @else
            <div class="lg:col-span-2">
                <label for="company_id" class="field-label">{{ __('Company') }}</label>
                <select id="company_id" name="company_id" class="field">
                    <option value="">{{ __('All Companies') }}</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}" @selected(($filters['company_id'] ?? null) == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="lg:col-span-2">
            <label for="status" class="field-label">{{ __('Status') }}</label>
            <select id="status" name="status" class="field">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="inside" @selected(($filters['status'] ?? null) === 'inside')>{{ __('Inside') }}</option>
                <option value="checked_out" @selected(($filters['status'] ?? null) === 'checked_out')>{{ __('Checked Out') }}</option>
            </select>
        </div>
        <div class="flex flex-wrap items-center gap-2 sm:col-span-2 lg:col-span-12">
            <button type="submit" class="btn btn-primary"><x-icon name="filter_alt" /> {{ __('Filter') }}</button>
            @if (array_filter($filters))
                <a href="{{ route('history.index') }}" class="btn btn-ghost"><x-icon name="close" /> {{ __('Clear filters') }}</a>
            @endif
        </div>
    </form>

    <section class="card mt-6 overflow-hidden" aria-label="{{ __('Visitor History') }}">
        @if ($visits->isEmpty())
            <x-empty-state icon="manage_search" :title="__('No records match these filters.')" :text="__('Try a wider date range or clear the filters.')" />
        @else
            <div class="overflow-x-auto">
                <table class="data-table min-w-[960px]">
                    <thead>
                        <tr>
                            <th>{{ __('Visitor Name') }}</th>
                            <th>{{ __('CPR Number') }}</th>
                            <th>{{ __('Mobile') }}</th>
                            <th>{{ __('Company') }}</th>
                            <th>{{ __('Department') }}</th>
                            <th>{{ __('Person to Visit') }}</th>
                            <th>{{ __('Check-In') }}</th>
                            <th>{{ __('Check-Out') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($visits as $visit)
                            <tr>
                                <td class="font-semibold text-on-surface">{{ $visit->visitor->name }}</td>
                                @php
                                    $cpr = $visit->visitor->cpr_number;
                                    $maskedCpr = mb_strlen($cpr) > 4
                                        ? str_repeat('•', mb_strlen($cpr) - 4).mb_substr($cpr, -4)
                                        : str_repeat('•', mb_strlen($cpr));
                                @endphp
                                <td class="whitespace-nowrap">{{ $maskedCpr }}</td>
                                <td class="whitespace-nowrap">{{ $visit->mobile_number ?? $visit->visitor->mobile_number }}</td>
                                <td>{{ $visit->visitor->company_name ?? '—' }}</td>
                                <td>{{ $visit->department?->name ?? '—' }}</td>
                                <td>{{ $visit->employee?->name ?? $visit->company?->name }}</td>
                                <td class="whitespace-nowrap">
                                    <span class="text-on-surface">{{ $visit->check_in_at->translatedFormat('M j, Y') }}</span>
                                    <span class="block text-[12px]">{{ $visit->check_in_at->translatedFormat('g:i A') }}</span>
                                </td>
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

    @if ($visits->hasPages())
        <div class="mt-4">
            {{ $visits->links() }}
        </div>
    @endif
</x-sidebar-layout>

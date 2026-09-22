<x-sidebar-layout>
    <div class="mx-auto max-w-6xl px-4 py-6 md:px-8 md:py-10">
        <h1 class="text-headline-md text-on-surface">{{ __('Visitor History') }}</h1>
        <p class="mt-1 text-body-md text-on-surface-variant">{{ __('Comprehensive logs of all facility access events.') }}</p>

        <form method="GET" action="{{ route('history.index') }}" class="mt-6 grid grid-cols-1 gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-5 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <label class="text-label-sm text-on-surface-variant">{{ __('Search') }}</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Name, CPR, or mobile...') }}" class="border rounded ps-3 pe-3 py-2 block w-full">
            </div>
            <div>
                <label class="text-label-sm text-on-surface-variant">{{ __('From') }}</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="border rounded ps-3 pe-3 py-2 block w-full">
            </div>
            <div>
                <label class="text-label-sm text-on-surface-variant">{{ __('To') }}</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="border rounded ps-3 pe-3 py-2 block w-full">
            </div>
            @if ($mode === 'company')
                <div>
                    <label class="text-label-sm text-on-surface-variant">{{ __('Department') }}</label>
                    <select name="department_id" class="border rounded ps-3 pe-3 py-2 block w-full">
                        <option value="">{{ __('All Departments') }}</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(($filters['department_id'] ?? null) == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <div>
                    <label class="text-label-sm text-on-surface-variant">{{ __('Company') }}</label>
                    <select name="company_id" class="border rounded ps-3 pe-3 py-2 block w-full">
                        <option value="">{{ __('All Companies') }}</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected(($filters['company_id'] ?? null) == $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label class="text-label-sm text-on-surface-variant">{{ __('Status') }}</label>
                <select name="status" class="border rounded ps-3 pe-3 py-2 block w-full">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="inside" @selected(($filters['status'] ?? null) === 'inside')>{{ __('Inside') }}</option>
                    <option value="checked_out" @selected(($filters['status'] ?? null) === 'checked_out')>{{ __('Checked Out') }}</option>
                </select>
            </div>
            <div class="flex items-end gap-2 lg:col-span-5">
                <button type="submit" class="bg-primary text-on-primary rounded px-4 py-2">{{ __('Filter') }}</button>
                <a href="{{ route('history.export', $filters) }}" class="border rounded px-4 py-2">{{ __('Export to CSV') }}</a>
            </div>
        </form>

        <div class="mt-6 overflow-x-auto rounded-xl border border-outline-variant bg-surface-container-lowest">
            @if ($visits->isEmpty())
                <p class="p-6 text-center text-body-md text-on-surface-variant">{{ __('No records match these filters.') }}</p>
            @else
                <table class="w-full min-w-[900px] text-start">
                    <thead class="bg-surface-container-low text-label-sm uppercase tracking-wide text-on-surface-variant">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('Visitor Name') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('CPR Number') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Mobile') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Company') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Department') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Person to Visit') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Check-In') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Check-Out') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant text-body-md">
                        @foreach ($visits as $visit)
                            <tr>
                                <td class="px-4 py-3 font-medium text-on-surface">{{ $visit->visitor->name }}</td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ \Illuminate\Support\Str::mask($visit->visitor->cpr_number, '•', 0, -4) }}</td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ $visit->visitor->mobile_number }}</td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ $visit->visitor->company_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ $visit->department?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ $visit->employee?->name ?? $visit->company?->name }}</td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ $visit->check_in_at->format('M j, Y g:i A') }}</td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ $visit->check_out_at?->format('g:i A') ?? '—' }}</td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ $visit->isOpen() ? __('Inside') : __('Checked Out') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="mt-4">
            {{ $visits->links() }}
        </div>
    </div>
</x-sidebar-layout>

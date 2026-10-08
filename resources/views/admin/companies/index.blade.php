<x-admin-page>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <section class="card" aria-labelledby="add-heading">
                <div class="card-header"><h2 id="add-heading" class="card-title">{{ __('Add company') }}</h2></div>
                <form method="POST" action="{{ route('admin.companies.store') }}" class="card-body space-y-4">
                    @csrf
                    <div>
                        <label for="new-company-name" class="field-label">{{ __('Company name') }}</label>
                        <input id="new-company-name" type="text" name="name" class="field" required>
                    </div>
                    <div>
                        <label for="new-company-email" class="field-label">{{ __('Contact email (optional)') }}</label>
                        <input id="new-company-email" type="email" name="contact_email" class="field">
                        <p class="field-help">{{ __('Used to email the company when their visitor checks in.') }}</p>
                    </div>
                    <button type="submit" class="btn btn-primary w-full"><x-icon name="add" /> {{ __('Add') }}</button>
                </form>
            </section>

            @include('admin.partials.import', ['route' => 'admin.companies', 'columns' => 'Company Name, Contact Email (optional)'])
        </div>

        <section class="card lg:col-span-2" aria-labelledby="list-heading">
            <div class="card-header">
                <h2 id="list-heading" class="card-title">{{ __('Companies') }}</h2>
                <span class="badge badge-neutral tabular">{{ $companies->count() }}</span>
            </div>
            @if ($companies->isEmpty())
                <x-empty-state icon="domain" :title="__('No companies yet')" :text="__('Add the companies in this building, or import them from a CSV file.')" />
            @else
                <div class="overflow-x-auto">
                    <table class="data-table min-w-[480px]">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Contact Email') }}</th>
                                <th class="text-end"><span class="sr-only">{{ __('Actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($companies as $company)
                                <tr>
                                    <td class="font-semibold text-on-surface">{{ $company->name }}</td>
                                    <td>{{ $company->contact_email ?? '—' }}</td>
                                    <td>
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('admin.companies.edit', $company) }}" class="btn btn-secondary btn-sm"><x-icon name="edit" /> {{ __('Edit') }}</a>
                                            <form method="POST" action="{{ route('admin.companies.destroy', $company) }}" onsubmit="return confirm({{ Js::from(__('Are you sure you want to delete this?')) }})">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm"><x-icon name="delete" /> {{ __('Delete') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-admin-page>

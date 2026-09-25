<x-sidebar-layout>
    <div class="p-6 max-w-2xl">
        <h1 class="text-xl font-semibold mb-4">{{ __('Companies') }}</h1>

        @if (session('status'))
            <p class="text-green-700 mb-3">{{ session('status') }}</p>
        @endif

        @include('admin.partials.import', ['route' => 'admin.companies', 'columns' => 'Company Name, Contact Email (optional)'])

        <form method="POST" action="{{ route('admin.companies.store') }}" class="flex gap-2 mb-6">
            @csrf
            <input type="text" name="name" placeholder="{{ __('Company name') }}" class="border rounded ps-3 pe-3 py-2 flex-1" required>
            <input type="email" name="contact_email" placeholder="{{ __('Contact email (optional)') }}" class="border rounded ps-3 pe-3 py-2 flex-1">
            <button type="submit" class="bg-primary text-on-primary rounded px-4 py-2">{{ __('Add') }}</button>
        </form>

        <ul>
            @foreach ($companies as $company)
                <li class="flex justify-between items-center border-b py-2">
                    <span>{{ $company->name }}</span>
                    <form method="POST" action="{{ route('admin.companies.destroy', $company) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600">{{ __('Delete') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
</x-sidebar-layout>

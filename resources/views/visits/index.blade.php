<x-sidebar-layout>
    <div class="p-6">
        <h1 class="text-xl font-semibold mb-4">{{ __('Open Visits') }}</h1>

        @if (session('status'))
            <p class="text-green-700 mb-3">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p class="text-red-600 mb-3" role="alert">{{ session('error') }}</p>
        @endif

        <a href="{{ route('visits.create') }}" class="inline-block bg-primary text-on-primary rounded px-4 py-2 mb-4">
            {{ __('Register New Visitor') }}
        </a>

        <table class="w-full text-start">
            <thead>
                <tr class="border-b">
                    <th class="text-start py-2">{{ __('Visitor') }}</th>
                    <th class="text-start py-2">{{ __('Visiting') }}</th>
                    <th class="text-start py-2">{{ __('Checked In') }}</th>
                    <th class="text-start py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($openVisits as $visit)
                    <tr class="border-b">
                        <td class="py-2">{{ $visit->visitor->name }}</td>
                        <td class="py-2">{{ $visit->employee?->name ?? $visit->company?->name }}</td>
                        <td class="py-2">{{ $visit->check_in_at->format('Y-m-d H:i') }}</td>
                        <td class="py-2">
                            <form method="POST" action="{{ route('visits.check-out', $visit) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="text-primary">{{ __('Check Out') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-sidebar-layout>

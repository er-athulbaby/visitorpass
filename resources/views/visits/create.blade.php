<x-app-layout>
    <div class="p-6 max-w-xl">
        <h1 class="text-xl font-semibold mb-4">{{ __('Register Visitor') }}</h1>

        @if ($errors->any())
            <div role="alert" tabindex="-1" class="border border-red-400 bg-red-50 p-3 mb-4 rounded">
                <h2 class="font-semibold text-red-700">{{ __('There is a problem') }}</h2>
                <ul class="list-disc ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('visits.store') }}">
            @csrf

            <div class="bg-blue-50 rounded p-4 mb-4">
                <h2 class="font-semibold mb-2">{{ __('Visitor Information') }}</h2>

                <div class="mb-3">
                    <label for="cpr_number">{{ __('CPR Number') }}</label>
                    <input id="cpr_number" name="cpr_number" type="text" class="border rounded ps-3 pe-3 py-2 block w-full" value="{{ old('cpr_number') }}" aria-describedby="cpr_number-error" required>
                    @error('cpr_number')
                        <p id="cpr_number-error" class="text-red-600 text-sm">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="name">{{ __('Visitor Name') }}</label>
                    <input id="name" name="name" type="text" class="border rounded ps-3 pe-3 py-2 block w-full" value="{{ old('name') }}" required>
                </div>

                <div>
                    <label for="company_name">{{ __('Company Name') }}</label>
                    <input id="company_name" name="company_name" type="text" class="border rounded ps-3 pe-3 py-2 block w-full" value="{{ old('company_name') }}">
                </div>
            </div>

            <div class="bg-amber-50 rounded p-4 mb-4">
                <h2 class="font-semibold mb-2">{{ __('Visit Details') }}</h2>

                <div class="mb-3">
                    <label for="mobile_number">{{ __('Mobile Number') }}</label>
                    <input id="mobile_number" name="mobile_number" type="text" class="border rounded ps-3 pe-3 py-2 block w-full" value="{{ old('mobile_number') }}">
                </div>

                @if ($mode === 'company')
                    <div>
                        <label for="employee_id">{{ __('Person to Visit') }}</label>
                        <select id="employee_id" name="employee_id" class="border rounded ps-3 pe-3 py-2 block w-full" aria-describedby="employee_id-error" required>
                            <option value="">{{ __('Select...') }}</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }} — {{ $employee->department->name }}</option>
                            @endforeach
                        </select>
                        @error('employee_id')
                            <p id="employee_id-error" class="text-red-600 text-sm">{{ $message }}</p>
                        @enderror
                    </div>
                @else
                    <div>
                        <label for="company_id">{{ __('Company Visiting') }}</label>
                        <select id="company_id" name="company_id" class="border rounded ps-3 pe-3 py-2 block w-full" aria-describedby="company_id-error" required>
                            <option value="">{{ __('Select...') }}</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                        @error('company_id')
                            <p id="company_id-error" class="text-red-600 text-sm">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            </div>

            <button type="submit" class="bg-primary text-on-primary rounded px-4 py-2">{{ __('Check In') }}</button>
        </form>
    </div>
</x-app-layout>

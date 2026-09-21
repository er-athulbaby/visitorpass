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

        <div x-data="cprScan()">
            <form method="POST" action="{{ route('visits.store') }}" @submit="stopAutocomplete()">
                @csrf

                <div class="bg-blue-50 rounded p-4 mb-4">
                    <h2 class="font-semibold mb-2">{{ __('Visitor Information') }}</h2>

                    <div class="mb-3 relative">
                        <label for="cpr_number">{{ __('CPR Number') }}</label>
                        <input
                            id="cpr_number"
                            name="cpr_number"
                            type="text"
                            class="border rounded ps-3 pe-3 py-2 block w-full"
                            aria-describedby="cpr_number-error"
                            x-model="cprNumber"
                            @input="onCprInput()"
                            @blur="lookupCpr()"
                            autocomplete="off"
                            required
                        >
                        @error('cpr_number')
                            <p id="cpr_number-error" class="text-red-600 text-sm">{{ $message }}</p>
                        @enderror

                        <ul
                            x-show="suggestions.length > 0"
                            class="absolute z-10 bg-white border rounded w-full mt-1 shadow"
                        >
                            <template x-for="suggestion in suggestions" :key="suggestion.id">
                                <li
                                    class="px-3 py-2 hover:bg-gray-100 cursor-pointer"
                                    @click="selectSuggestion(suggestion)"
                                    x-text="suggestion.cpr_number + ' — ' + suggestion.name"
                                ></li>
                            </template>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <label for="name">{{ __('Visitor Name') }}</label>
                        <input id="name" name="name" type="text" class="border rounded ps-3 pe-3 py-2 block w-full" x-model="visitorName" required>
                    </div>

                    <div>
                        <label for="company_name">{{ __('Company Name') }}</label>
                        <input id="company_name" name="company_name" type="text" class="border rounded ps-3 pe-3 py-2 block w-full" x-model="companyName">
                    </div>
                </div>

                <div class="bg-amber-50 rounded p-4 mb-4">
                    <h2 class="font-semibold mb-2">{{ __('Visit Details') }}</h2>

                    <button type="button" @click="scanCard()" class="bg-primary text-on-primary rounded px-4 py-2 mb-3">
                        {{ __('Scan CPR') }}
                    </button>
                    <p x-show="scanMessage" x-text="scanMessage" class="text-sm text-gray-600 mb-3"></p>

                    <div class="mb-3">
                        <label for="mobile_number">{{ __('Mobile Number') }}</label>
                        <input id="mobile_number" name="mobile_number" type="text" class="border rounded ps-3 pe-3 py-2 block w-full" x-model="mobileNumber">
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
    </div>

    <script>
        function cprScan() {
            return {
                cprNumber: @js(old('cpr_number', '')),
                visitorName: @js(old('name', '')),
                companyName: @js(old('company_name', '')),
                mobileNumber: @js(old('mobile_number', '')),
                suggestions: [],
                scanMessage: '',
                autocompleteTimer: null,

                async scanCard() {
                    this.scanMessage = '{{ __('Scanning...') }}';

                    try {
                        const response = await fetch('http://localhost:5050/api/operation/ReadCard', {
                            method: 'POST',
                            headers: { 'Content-Type': 'text/plain' },
                            body: JSON.stringify({
                                ReadEmploymentInfo: true,
                                ReadImmigrationDetails: true,
                            }),
                        });

                        const data = await response.json();

                        if (!data || !data.CPRNumber) {
                            this.scanMessage = '{{ __('No card detected. Enter CPR manually.') }}';
                            return;
                        }

                        this.cprNumber = data.CPRNumber;
                        this.visitorName = data.NameEnglish || data.Name || '';
                        this.companyName = data.EmployerName || data.SponserNameEnglish || data.EmploymentNameEnglish || '';
                        this.scanMessage = '';

                        await this.lookupCpr();
                    } catch (error) {
                        this.scanMessage = '{{ __('Reader not available — enter CPR manually.') }}';
                    }
                },

                async lookupCpr() {
                    if (!this.cprNumber) {
                        return;
                    }

                    try {
                        const response = await fetch(`/visitors/lookup?cpr=${encodeURIComponent(this.cprNumber)}`);
                        const data = await response.json();

                        if (data && data.mobile_number) {
                            this.mobileNumber = data.mobile_number;
                        }
                    } catch (error) {
                        // Silent — never block the form on a lookup failure.
                    }
                },

                onCprInput() {
                    clearTimeout(this.autocompleteTimer);

                    if (this.cprNumber.length < 3) {
                        this.suggestions = [];
                        return;
                    }

                    this.autocompleteTimer = setTimeout(async () => {
                        try {
                            const response = await fetch(`/visitors/autocomplete?q=${encodeURIComponent(this.cprNumber)}`);
                            this.suggestions = await response.json();
                        } catch (error) {
                            this.suggestions = [];
                        }
                    }, 300);
                },

                selectSuggestion(suggestion) {
                    this.cprNumber = suggestion.cpr_number;
                    this.visitorName = suggestion.name;
                    this.suggestions = [];
                    this.lookupCpr();
                },

                stopAutocomplete() {
                    clearTimeout(this.autocompleteTimer);
                    this.suggestions = [];
                },
            };
        }
    </script>
</x-app-layout>

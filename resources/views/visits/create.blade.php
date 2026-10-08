<x-sidebar-layout>
    <x-page-header :title="__('Register Visitor')" :subtitle="__('Scan the visitor\'s CPR card or enter their details, then check them in.')" />

    @if ($errors->any())
        <div role="alert" tabindex="-1" class="alert alert-error mb-6">
            <x-icon name="error" class="mt-0.5 shrink-0" />
            <div>
                <h2 class="font-semibold">{{ __('There is a problem') }}</h2>
                <ul class="mt-1 list-disc ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div x-data="cprScan()">
        <form method="POST" action="{{ route('visits.store') }}" @submit="stopAutocomplete()" class="grid gap-6 lg:grid-cols-5">
            @csrf

            {{-- Card reader: the fastest path, so it sits first and largest. --}}
            <section class="card lg:col-span-2 lg:self-start lg:sticky lg:top-24" aria-labelledby="reader-heading">
                <div class="card-body">
                    <h2 id="reader-heading" class="card-title">{{ __('Scan CPR card') }}</h2>
                    <p class="mt-1 text-body-sm text-on-surface-variant">{{ __('Insert the card into the reader, then select Scan CPR.') }}</p>

                    <div class="mt-5 flex flex-col items-center rounded-xl border-2 border-dashed border-outline-variant bg-surface-container-low px-6 py-8 text-center">
                        <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-surface-container-lowest text-primary shadow-sm">
                            <x-icon name="id_card" class="text-[32px]" />
                        </span>
                        <button type="button" @click="scanCard()" class="btn btn-primary btn-lg mt-5 w-full sm:w-auto">
                            <x-icon name="contactless" />
                            {{ __('Scan CPR') }}
                        </button>
                        <p x-show="scanMessage" x-text="scanMessage" class="mt-4 text-body-sm font-medium text-on-surface-variant" role="status" aria-live="polite"></p>
                    </div>

                    <p class="mt-4 flex items-start gap-2 text-[13px] text-on-surface-variant">
                        <x-icon name="info" class="mt-px shrink-0 text-[18px]" />
                        {{ __('No reader? Type the CPR number below. Returning visitors fill in automatically.') }}
                    </p>
                </div>
            </section>

            <div class="space-y-6 lg:col-span-3">
                <section class="card" aria-labelledby="visitor-heading">
                    <div class="card-header">
                        <h2 id="visitor-heading" class="card-title">{{ __('Visitor Information') }}</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <div class="relative" @click.outside="suggestions = []">
                            <label for="cpr_number" class="field-label">{{ __('CPR Number') }}</label>
                            <input
                                id="cpr_number"
                                name="cpr_number"
                                type="text"
                                inputmode="numeric"
                                class="field tabular"
                                aria-describedby="cpr_number-error"
                                x-model="cprNumber"
                                @input="onCprInput()"
                                @blur="lookupCpr()"
                                @keydown.escape="suggestions = []"
                                autocomplete="off"
                                required
                            >
                            @error('cpr_number')
                                <p id="cpr_number-error" class="field-error">{{ $message }}</p>
                            @enderror

                            <ul
                                x-show="suggestions.length > 0"
                                x-transition.opacity.duration.150ms
                                class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-outline-variant bg-surface-container-lowest py-1 shadow-lg"
                            >
                                <template x-for="suggestion in suggestions" :key="suggestion.id">
                                    <li
                                        class="cursor-pointer px-3 py-2 text-body-sm text-on-surface transition-colors duration-150 hover:bg-surface-container"
                                        @click="selectSuggestion(suggestion)"
                                        x-text="suggestion.cpr_number + ' — ' + suggestion.name"
                                    ></li>
                                </template>
                            </ul>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="name" class="field-label">{{ __('Visitor Name') }}</label>
                                <input id="name" name="name" type="text" class="field" x-model="visitorName" required>
                            </div>

                            <div>
                                <label for="company_name" class="field-label">{{ __('Company Name') }}</label>
                                <input id="company_name" name="company_name" type="text" class="field" x-model="companyName">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card" aria-labelledby="visit-heading">
                    <div class="card-header">
                        <h2 id="visit-heading" class="card-title">{{ __('Visit Details') }}</h2>
                    </div>
                    <div class="card-body grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="mobile_number" class="field-label">{{ __('Mobile Number') }}</label>
                            <input id="mobile_number" name="mobile_number" type="text" inputmode="tel" class="field tabular" x-model="mobileNumber">
                        </div>

                        @if ($mode === 'company')
                            <div>
                                <label for="employee_id" class="field-label">{{ __('Person to Visit') }}</label>
                                <select id="employee_id" name="employee_id" class="field" aria-describedby="employee_id-error" required>
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}">{{ $employee->name }} — {{ $employee->department->name }}</option>
                                    @endforeach
                                </select>
                                @error('employee_id')
                                    <p id="employee_id-error" class="field-error">{{ $message }}</p>
                                @enderror
                            </div>
                        @else
                            <div>
                                <label for="company_id" class="field-label">{{ __('Company Visiting') }}</label>
                                <select id="company_id" name="company_id" class="field" aria-describedby="company_id-error" required>
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach ($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                                @error('company_id')
                                    <p id="company_id-error" class="field-error">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </div>
                    <div class="flex justify-end border-t border-outline-variant px-5 py-4">
                        <button type="submit" class="btn btn-primary btn-lg w-full sm:w-auto">
                            <x-icon name="how_to_reg" /> {{ __('Check In') }}
                        </button>
                    </div>
                </section>
            </div>
        </form>
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
                            // Deliberately text/plain, not application/json: this server has
                            // no CORS preflight handler (OPTIONS 404s), so the request must
                            // stay a CORS "simple request" or the browser blocks it before
                            // it's ever sent. The server ignores the declared content type
                            // and JSON-parses the raw body anyway.
                            headers: { 'Content-Type': 'text/plain' },
                            body: JSON.stringify({
                                ReadCardInfo: false,
                                ReadPersonalInfo: true,
                                ReadAddressDetails: false,
                                ReadBiometrics: false,
                                ReadEmploymentInfo: true,
                                ReadImmigrationDetails: true,
                                ReadTrafficDetails: false,
                                SilentReading: false,
                                ReaderIndex: -1,
                                ReaderName: '',
                                OutputFormat: 'JSON',
                                ValidateCard: false,
                            }),
                        });

                        if (!response.ok) {
                            throw new Error(`Reader server returned HTTP ${response.status}`);
                        }

                        const data = await response.json();

                        if (data.ErrorDescription) {
                            throw new Error(data.ErrorDescription);
                        }

                        const cprNumber = (data.IdNumber || data.MiscellaneousTextData?.CPRNO || '').trim();

                        if (!cprNumber) {
                            this.scanMessage = '{{ __('No card detected. Enter CPR manually.') }}';
                            return;
                        }

                        this.cprNumber = cprNumber;
                        this.visitorName = (data.EnglishFullName || [data.EnglishFirstName, data.EnglishLastName].filter(Boolean).join(' ') || '').trim();
                        this.companyName = (data.EmployerName || data.SponserNameEnglish || data.EmploymentNameEnglish || '').trim();
                        this.scanMessage = '';

                        await this.lookupCpr(true);
                    } catch (error) {
                        this.scanMessage = '{{ __('Reader not available — enter CPR manually.') }}';
                    }
                },

                // Fills the form from a returning visitor's saved record. After a card
                // scan the card's name/company win and saved values only fill gaps;
                // for a hand-typed or picked CPR the saved record wins.
                async lookupCpr(fromCard = false) {
                    if (!this.cprNumber) {
                        return;
                    }

                    try {
                        const response = await fetch(@js(route('visitors.lookup')) + '?cpr=' + encodeURIComponent(this.cprNumber));
                        const data = await response.json();

                        if (!data || !data.name) {
                            return;
                        }

                        this.mobileNumber = data.mobile_number || this.mobileNumber;

                        if (!fromCard || !this.visitorName) {
                            this.visitorName = data.name;
                        }

                        if (data.company_name && (!fromCard || !this.companyName)) {
                            this.companyName = data.company_name;
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
                            const response = await fetch(@js(route('visitors.autocomplete')) + '?q=' + encodeURIComponent(this.cprNumber));
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
</x-sidebar-layout>

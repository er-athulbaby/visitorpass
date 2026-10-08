<x-admin-page>
    <a href="{{ route('admin.companies.index') }}" class="btn btn-ghost btn-sm -ms-3 mb-3"><x-icon name="arrow_back" class="icon-directional" /> {{ __('Back') }}</a>

    <form method="POST" action="{{ route('admin.companies.update', $company) }}" class="card max-w-2xl">
        @csrf
        @method('PUT')
        <div class="card-header"><h2 class="card-title">{{ __('Edit company') }}</h2></div>
        <div class="card-body space-y-5">

        <div>
            <label for="name" class="field-label">{{ __('Company name') }}</label>
            <input id="name" name="name" type="text" value="{{ old('name', $company->name) }}" class="field" required>
            @error('name') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="contact_email" class="field-label">{{ __('Contact email (optional)') }}</label>
            <input id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', $company->contact_email) }}" class="field">
            @error('contact_email') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>

        </div>
        <div class="flex justify-end gap-2 border-t border-outline-variant px-5 py-4">
            <a href="{{ route('admin.companies.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary"><x-icon name="save" /> {{ __('Save') }}</button>
        </div>
    </form>
</x-admin-page>

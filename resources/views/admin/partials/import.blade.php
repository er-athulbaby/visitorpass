<section class="card" aria-labelledby="import-heading-{{ \Illuminate\Support\Str::slug($route) }}">
    <div class="card-header">
        <h2 id="import-heading-{{ \Illuminate\Support\Str::slug($route) }}" class="card-title">{{ __('Bulk import') }}</h2>
        <a href="{{ route($route.'.template') }}" class="btn btn-ghost btn-sm"><x-icon name="download" /> {{ __('Download template') }}</a>
    </div>

    <form method="POST" action="{{ route($route.'.import') }}" enctype="multipart/form-data" class="card-body space-y-3">
        @csrf
        <p class="text-body-sm text-on-surface-variant">{{ __('Upload a CSV file with the columns: :columns', ['columns' => $columns]) }}</p>
        <input type="file" name="file" accept=".csv,text/csv" required aria-label="{{ __('CSV file') }}" class="file-input">
        @error('file')
            <p class="field-error" role="alert"><x-icon name="error" class="text-[16px]" /> {{ $message }}</p>
        @enderror
        <button type="submit" class="btn btn-secondary w-full"><x-icon name="upload" /> {{ __('Import') }}</button>
    </form>

    @if ($import = session('import'))
        <div class="space-y-3 border-t border-outline-variant p-5 text-body-sm" role="status">
            <dl class="grid grid-cols-2 gap-2">
                <div class="rounded-lg bg-success-container px-3 py-2 text-[#166534]"><dt class="sr-only">{{ __('Created') }}</dt><dd class="font-semibold">{{ __(':count created', ['count' => $import['created']]) }}</dd></div>
                <div class="rounded-lg bg-info-container px-3 py-2 text-[#0369A1]"><dt class="sr-only">{{ __('Updated') }}</dt><dd class="font-semibold">{{ __(':count updated', ['count' => count($import['updated'])]) }}</dd></div>
                <div class="rounded-lg bg-surface-container px-3 py-2 text-on-surface-variant"><dt class="sr-only">{{ __('Skipped') }}</dt><dd class="font-semibold">{{ __(':count skipped', ['count' => count($import['skipped'])]) }}</dd></div>
                <div class="rounded-lg px-3 py-2 font-semibold {{ $import['errors'] ? 'bg-error-container text-on-error-container' : 'bg-surface-container text-on-surface-variant' }}"><dt class="sr-only">{{ __('Errors') }}</dt><dd>{{ __(':count errors', ['count' => count($import['errors'])]) }}</dd></div>
            </dl>
            @foreach ([__('Updated') => $import['updated'], __('Skipped (already exist)') => $import['skipped']] as $label => $names)
                @if ($names)
                    <p class="text-on-surface-variant"><span class="font-medium text-on-surface">{{ $label }}:</span> {{ implode(', ', array_slice($names, 0, 20)) }}@if (count($names) > 20) {{ __('and :count more', ['count' => count($names) - 20]) }}@endif</p>
                @endif
            @endforeach
            @if ($import['errors'])
                <ul class="list-disc space-y-1 ps-5 text-error">
                    @foreach ($import['errors'] as $error)
                        <li>{{ __('Row :row: :message', ['row' => $error['row'], 'message' => $error['message']]) }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</section>

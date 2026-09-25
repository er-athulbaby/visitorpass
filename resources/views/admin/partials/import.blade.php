<section class="mb-6 rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
    <h2 class="text-headline-sm text-on-surface">{{ __('Bulk import') }}</h2>
    <p class="mt-1 text-body-md text-on-surface-variant">{{ __('Upload a CSV file with the columns: :columns', ['columns' => $columns]) }}</p>

    <form method="POST" action="{{ route($route.'.import') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-3">
        @csrf
        <input type="file" name="file" accept=".csv,text/csv" required aria-label="{{ __('CSV file') }}" class="file-input min-w-0 flex-1 basis-64">
        <button type="submit" class="btn btn-primary"><x-icon name="upload" /> {{ __('Import') }}</button>
        <a href="{{ route($route.'.template') }}" class="btn btn-secondary"><x-icon name="download" /> {{ __('Download template') }}</a>
    </form>

    @error('file')
        <p class="mt-2 text-red-600" role="alert">{{ $message }}</p>
    @enderror

    @if ($import = session('import'))
        <div class="mt-4 space-y-2 text-body-md" role="status">
            <p class="text-on-surface">
                {{ __(':count created', ['count' => $import['created']]) }} ·
                {{ __(':count updated', ['count' => count($import['updated'])]) }} ·
                {{ __(':count skipped', ['count' => count($import['skipped'])]) }} ·
                {{ __(':count errors', ['count' => count($import['errors'])]) }}
            </p>
            @foreach ([__('Updated') => $import['updated'], __('Skipped (already exist)') => $import['skipped']] as $label => $names)
                @if ($names)
                    <p class="text-on-surface-variant">{{ $label }}: {{ implode(', ', array_slice($names, 0, 20)) }}@if (count($names) > 20) {{ __('and :count more', ['count' => count($names) - 20]) }}@endif</p>
                @endif
            @endforeach
            @if ($import['errors'])
                <ul class="list-disc ps-5 text-red-600">
                    @foreach ($import['errors'] as $error)
                        <li>{{ __('Row :row: :message', ['row' => $error['row'], 'message' => $error['message']]) }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</section>

<?php

use Illuminate\Support\Facades\Storage;

test('install is not reachable even without the marker if the database is already connected and migrated', function () {
    // Simulates losing storage/app/private/installed on an already-installed
    // site (backup restore, wiped storage dir, host migration): the DB is
    // still fully configured and migrated, so /install must not reopen.
    Storage::fake('local');

    $this->get('/install')->assertNotFound();
    expect(Storage::disk('local')->exists('installed'))->toBeTrue();
});

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

test('the install form still renders when APP_KEY is empty, as on a fresh unzip', function () {
    Storage::fake('local');
    Schema::dropIfExists('settings');

    // Artisan's key:generate command always targets the real base_path('.env')
    // regardless of any container bindings, so this test manipulates the
    // actual project .env directly — restored in `finally` no matter what.
    $envPath = base_path('.env');
    $original = file_get_contents($envPath);

    try {
        file_put_contents($envPath, preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=', $original));
        config(['app.key' => '']);

        $response = $this->get('/install');

        $response->assertOk();
    } finally {
        file_put_contents($envPath, $original);
    }
});

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

test('the install form renders even when the Vite build manifest is missing', function () {
    Storage::fake('local');
    Schema::dropIfExists('settings');

    $manifestPath = public_path('build/manifest.json');
    $backupPath = $manifestPath.'.bak';
    $manifestExisted = file_exists($manifestPath);

    if ($manifestExisted) {
        rename($manifestPath, $backupPath);
    }

    try {
        $response = $this->get('/install');

        $response->assertOk();
    } finally {
        if ($manifestExisted) {
            rename($backupPath, $manifestPath);
        }
    }
});

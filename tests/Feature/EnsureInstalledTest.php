<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('a request passes through untouched once the installed marker exists', function () {
    Storage::disk('local')->put('installed', now()->toString());
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);

    $response = $this->get('/nonexistent-route-for-passthrough-check');

    // Reaching Laravel's normal 404 (not a redirect to /install) proves the
    // middleware did not intervene.
    $response->assertNotFound();
});

test('visiting install after the marker exists returns 404', function () {
    Storage::disk('local')->put('installed', now()->toString());

    $this->get('/install')->assertNotFound();
    $this->post('/install', [])->assertNotFound();
});

test('when no marker exists but the settings table is already migrated, the marker is written and no redirect happens', function () {
    expect(Storage::disk('local')->exists('installed'))->toBeFalse();

    $response = $this->get('/setup');

    $response->assertOk();
    expect(Storage::disk('local')->exists('installed'))->toBeTrue();
});

test('when no marker exists and the database is not migrated, the request is redirected to install', function () {
    Schema::dropIfExists('settings');

    $response = $this->get('/setup');

    $response->assertRedirect('/install');
    expect(Storage::disk('local')->exists('installed'))->toBeFalse();
});

test('install form itself is reachable when nothing is installed yet', function () {
    Schema::dropIfExists('settings');

    $this->get('/install')->assertOk();
});

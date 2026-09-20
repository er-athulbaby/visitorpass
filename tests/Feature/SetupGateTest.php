<?php

use App\Models\Setting;

test('any route redirects to setup when no settings row exists', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/setup');
});

test('setup route is not redirected when settings do not exist yet', function () {
    $response = $this->get('/setup');

    $response->assertOk();
});

test('routes work normally once settings exist', function () {
    Setting::create([
        'id' => 1,
        'deployment_mode' => 'company',
    ]);

    $response = $this->get('/setup');

    $response->assertRedirect('/login');
});

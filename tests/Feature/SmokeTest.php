<?php

use App\Models\Setting;

test('the application boots and serves the login page', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);

    $response = $this->get('/login');

    $response->assertStatus(200);
});

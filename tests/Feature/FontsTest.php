<?php

use App\Models\Setting;
use App\Models\User;

test('the app layout loads Noto Sans and Noto Sans Arabic', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertSee('family=Noto+Sans', false);
    $response->assertSee('family=Noto+Sans+Arabic', false);
});

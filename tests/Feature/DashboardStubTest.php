<?php

use App\Models\Setting;
use App\Models\User;

test('the dashboard route still resolves to the dashboard view via a controller', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertViewIs('dashboard');
});

<?php

use App\Models\Setting;
use App\Models\User;

test('the history page renders for an authenticated user', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/history');

    $response->assertOk();
    $response->assertSee('Visitor History');
});

test('the history page requires authentication', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);

    $response = $this->get('/history');

    $response->assertRedirect('/login');
});

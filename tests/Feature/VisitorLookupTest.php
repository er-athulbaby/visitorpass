<?php

use App\Models\Setting;
use App\Models\User;
use App\Models\Visitor;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $this->receptionist = User::factory()->create();
    $this->receptionist->assignRole('receptionist');
});

test('looking up an existing cpr returns the saved visitor details', function () {
    Visitor::create([
        'cpr_number' => '990101123',
        'name' => 'Ali Visitor',
        'company_name' => 'Acme',
        'mobile_number' => '33445566',
    ]);

    $response = $this->actingAs($this->receptionist)->getJson('/visitors/lookup?cpr=990101123');

    $response->assertOk();
    $response->assertJson([
        'name' => 'Ali Visitor',
        'company_name' => 'Acme',
        'mobile_number' => '33445566',
    ]);
});

test('looking up a cpr with no match returns an empty object', function () {
    $response = $this->actingAs($this->receptionist)->getJson('/visitors/lookup?cpr=000000000');

    $response->assertOk();
    $response->assertExactJson([]);
});

test('lookup with a missing cpr parameter returns an empty object instead of an error', function () {
    $response = $this->actingAs($this->receptionist)->getJson('/visitors/lookup');

    $response->assertOk();
    $response->assertExactJson([]);
});

test('a guest cannot access the lookup endpoint', function () {
    $response = $this->getJson('/visitors/lookup?cpr=990101123');

    $response->assertUnauthorized();
});

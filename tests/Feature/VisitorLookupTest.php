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

test('autocomplete returns visitors matching a cpr prefix', function () {
    Visitor::create(['cpr_number' => '990101123', 'name' => 'Ali Visitor']);
    Visitor::create(['cpr_number' => '990101124', 'name' => 'Fatima Visitor']);
    Visitor::create(['cpr_number' => '880202111', 'name' => 'Not A Match']);

    $response = $this->actingAs($this->receptionist)->getJson('/visitors/autocomplete?q=990101');

    $response->assertOk();
    $response->assertJsonCount(2);
    $response->assertJsonFragment(['name' => 'Ali Visitor']);
    $response->assertJsonFragment(['name' => 'Fatima Visitor']);
});

test('autocomplete caps results at 8', function () {
    for ($i = 0; $i < 10; $i++) {
        Visitor::create([
            'cpr_number' => '9901011' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'name' => "Visitor {$i}",
        ]);
    }

    $response = $this->actingAs($this->receptionist)->getJson('/visitors/autocomplete?q=990101');

    $response->assertOk();
    $response->assertJsonCount(8);
});

test('autocomplete with no match returns an empty array', function () {
    $response = $this->actingAs($this->receptionist)->getJson('/visitors/autocomplete?q=000000');

    $response->assertOk();
    $response->assertExactJson([]);
});

test('autocomplete with a missing q parameter returns an empty array', function () {
    $response = $this->actingAs($this->receptionist)->getJson('/visitors/autocomplete');

    $response->assertOk();
    $response->assertExactJson([]);
});

test('a guest cannot access the autocomplete endpoint', function () {
    $response = $this->getJson('/visitors/autocomplete?q=990101');

    $response->assertUnauthorized();
});

test('autocomplete with an array q parameter returns an empty array instead of erroring', function () {
    $response = $this->actingAs($this->receptionist)->getJson('/visitors/autocomplete?q[]=1');

    $response->assertOk();
    $response->assertExactJson([]);
});

test('the registration page renders successfully', function () {
    $response = $this->actingAs($this->receptionist)->get('/visits/create');

    $response->assertOk();
    $response->assertSee('Scan CPR');
});

test('the registration page calls lookup and autocomplete through app URLs so a subfolder install works', function () {
    $response = $this->actingAs($this->receptionist)->get('/visits/create');

    $response->assertDontSee('fetch(`/visitors', false);
    $response->assertSee(str_replace('/', '\/', route('visitors.lookup')), false);
    $response->assertSee(str_replace('/', '\/', route('visitors.autocomplete')), false);
});

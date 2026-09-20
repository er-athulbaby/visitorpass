<?php

use App\Models\Company;
use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'building']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('admin can create a company', function () {
    $response = $this->actingAs($this->admin)->post('/admin/companies', [
        'name' => 'Acme Corp',
        'contact_email' => 'reception@acme.test',
    ]);

    $response->assertRedirect('/admin/companies');
    expect(Company::where('name', 'Acme Corp')->exists())->toBeTrue();
});

test('company contact email is optional', function () {
    $response = $this->actingAs($this->admin)->post('/admin/companies', [
        'name' => 'No Email Ltd',
    ]);

    $response->assertRedirect('/admin/companies');
    expect(Company::where('name', 'No Email Ltd')->first()->contact_email)->toBeNull();
});

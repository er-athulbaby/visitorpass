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

test('importing companies creates, updates contact emails, skips, and reports errors', function () {
    Company::create(['name' => 'Acme', 'contact_email' => null]);
    Company::create(['name' => 'Globex', 'contact_email' => 'g@x.test']);

    $csv = "Company Name,Contact Email\n"
        ."Initech,i@x.test\n"   // 2 created
        ."acme,a@x.test\n"      // 3 updated
        ."Globex,g@x.test\n"    // 4 skipped
        .",x@x.test\n"          // 5 error: name
        ."Umbrella,bad\n"       // 6 error: email
        ."initech,\n";          // 7 skipped (duplicate of row 2)

    $response = $this->actingAs($this->admin)->post('/admin/companies/import', ['file' => csvFile($csv)]);

    $response->assertRedirect('/admin/companies');
    $response->assertSessionHas('import', [
        'created' => 1,
        'updated' => ['acme'],
        'skipped' => ['Globex', 'initech'],
        'errors' => [
            ['row' => 5, 'message' => 'Missing or invalid company name'],
            ['row' => 6, 'message' => 'Invalid email "bad"'],
        ],
    ]);
    expect(Company::where('name', 'Acme')->first()->contact_email)->toBe('a@x.test');
    expect(Company::count())->toBe(3);
});

test('Name and Email columns are accepted as aliases', function () {
    $this->actingAs($this->admin)->post('/admin/companies/import', ['file' => csvFile("Name,Email\nHooli,h@x.test\n")]);

    expect(Company::where('name', 'Hooli')->first()->contact_email)->toBe('h@x.test');
});

test('company import is not available in company mode', function () {
    Setting::current()->update(['deployment_mode' => 'company']);

    $this->actingAs($this->admin)
        ->post('/admin/companies/import', ['file' => csvFile("Name\nHooli\n")])
        ->assertNotFound();
});

test('the template downloads with company headers', function () {
    $response = $this->actingAs($this->admin)->get('/admin/companies/template');

    $response->assertDownload('companies-template.csv');
    expect($response->streamedContent())->toContain('"Company Name","Contact Email"');
});

test('the companies page shows the import form', function () {
    $this->actingAs($this->admin)->get('/admin/companies')
        ->assertSee(route('admin.companies.import'), false);
});

test('the downloaded template, filled in with a text editor, imports cleanly', function () {
    $template = $this->actingAs($this->admin)->get('/admin/companies/template')->streamedContent();

    $this->actingAs($this->admin)
        ->post('/admin/companies/import', ['file' => csvFile($template."Hooli,h@x.test\n")])
        ->assertSessionHas('import', ['created' => 1, 'updated' => [], 'skipped' => [], 'errors' => []]);
});

test('a contact email differing only in letter case is not treated as a change', function () {
    Company::create(['name' => 'Acme', 'contact_email' => 'a@x.test']);

    $this->actingAs($this->admin)
        ->post('/admin/companies/import', ['file' => csvFile("Name,Email\nAcme,A@X.TEST\n")])
        ->assertSessionHas('import.skipped', ['Acme']);
});

test('a receptionist cannot import companies', function () {
    $receptionist = User::factory()->create();
    $receptionist->assignRole('receptionist');

    $this->actingAs($receptionist)
        ->post('/admin/companies/import', ['file' => csvFile("Name\nHooli\n")])
        ->assertForbidden();
});

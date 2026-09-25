<?php

use App\Models\Department;
use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('importing departments creates new ones, skips existing and in-file duplicates, and reports bad rows', function () {
    Department::create(['name' => 'Finance']);

    $response = $this->actingAs($this->admin)->post('/admin/departments/import', [
        'file' => csvFile("Department Name\nHR\nfinance\n\nhr\n".str_repeat('x', 256)."\n,\n"),
    ]);

    $response->assertRedirect('/admin/departments');
    $response->assertSessionHas('import', [
        'created' => 1,
        'updated' => [],
        'skipped' => ['finance', 'hr'],
        'errors' => [['row' => 6, 'message' => 'Missing or invalid department name']],
    ]);
    expect(Department::pluck('name')->sort()->values()->all())->toBe(['Finance', 'HR']);
});

test('the Name column is accepted as an alias', function () {
    $this->actingAs($this->admin)->post('/admin/departments/import', ['file' => csvFile("name\nLegal\n")]);

    expect(Department::where('name', 'Legal')->exists())->toBeTrue();
});

test('a file with no data rows is rejected with an error', function () {
    $this->actingAs($this->admin)
        ->post('/admin/departments/import', ['file' => csvFile("Department Name\n")])
        ->assertRedirect('/admin/departments')
        ->assertSessionHas('error', 'The CSV file has no data rows.');
});

test('the upload must be a csv file', function () {
    $this->actingAs($this->admin)
        ->post('/admin/departments/import', [])
        ->assertSessionHasErrors('file');
});

test('a receptionist cannot import departments', function () {
    $receptionist = User::factory()->create();
    $receptionist->assignRole('receptionist');

    $this->actingAs($receptionist)
        ->post('/admin/departments/import', ['file' => csvFile("Name\nHR\n")])
        ->assertForbidden();
    expect(Department::count())->toBe(0);
});

test('the template downloads as csv with the department header', function () {
    $response = $this->actingAs($this->admin)->get('/admin/departments/template');

    $response->assertOk();
    $response->assertDownload('departments-template.csv');
    expect($response->streamedContent())->toContain('"Department Name"');
});

test('the departments page shows the import form and the import summary', function () {
    $this->actingAs($this->admin)
        ->post('/admin/departments/import', ['file' => csvFile("Name\nHR\n".str_repeat('x', 256)."\n")]);

    $response = $this->actingAs($this->admin)->get('/admin/departments');

    $response->assertSee(route('admin.departments.import'), false);
    $response->assertSee(route('admin.departments.template'), false);
    $response->assertSee('enctype="multipart/form-data"', false);
    $response->assertSee('1 created');
    $response->assertSee('Row 3: Missing or invalid department name');
});

test('a file without a header row is rejected instead of silently dropping its first department', function () {
    $this->actingAs($this->admin)
        ->post('/admin/departments/import', ['file' => csvFile("HR\nIT\n")])
        ->assertSessionHasErrors('file');
    expect(Department::count())->toBe(0);
});

test('a non-UTF-8 file is rejected before anything is saved', function () {
    $this->actingAs($this->admin)
        ->post('/admin/departments/import', ['file' => csvFile("Name\nHR\nCaf\xE9\n")])
        ->assertSessionHasErrors('file');
    expect(Department::count())->toBe(0);
});

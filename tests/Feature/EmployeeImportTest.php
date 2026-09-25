<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->it = Department::create(['name' => 'IT']);
});

test('importing employees creates, updates emails, skips, and reports each error kind', function () {
    Employee::create(['department_id' => $this->it->id, 'name' => 'Sam', 'email' => null]);
    Employee::create(['department_id' => $this->it->id, 'name' => 'Pat', 'email' => 'pat@x.test']);

    $csv = "Employee Name,Department,Email\n"
        ."Alex,it,alex@x.test\n"   // 2 created (department matched case-insensitively)
        ."sam,IT,sam@x.test\n"     // 3 updated (email filled in)
        ."Pat,IT,pat@x.test\n"     // 4 skipped (same email)
        ."Pat,IT,\n"               // 5 skipped (no email)
        ."\n"                      // 6 blank, ignored
        .",IT,\n"                  // 7 error: name
        ."Jo,Sales,\n"             // 8 error: department
        ."Jo,,\n"                  // 9 error: blank department
        ."Jo,IT,not-an-email\n"    // 10 error: email
        ."alex,IT,\n";             // 11 skipped (duplicate of row 2)

    $response = $this->actingAs($this->admin)->post('/admin/employees/import', ['file' => csvFile($csv)]);

    $response->assertRedirect('/admin/employees');
    $response->assertSessionHas('import', [
        'created' => 1,
        'updated' => ['sam (IT)'],
        'skipped' => ['Pat (IT)', 'Pat (IT)', 'alex (IT)'],
        'errors' => [
            ['row' => 7, 'message' => 'Missing or invalid employee name'],
            ['row' => 8, 'message' => 'Department "Sales" was not found'],
            ['row' => 9, 'message' => 'Department "(blank)" was not found'],
            ['row' => 10, 'message' => 'Invalid email "not-an-email"'],
        ],
    ]);
    expect(Employee::where('name', 'Alex')->first()->email)->toBe('alex@x.test');
    expect(Employee::where('name', 'Sam')->first()->email)->toBe('sam@x.test');
    expect(Employee::count())->toBe(3);
    expect(Department::count())->toBe(1);
});

test('an employee created without an email stores null', function () {
    $this->actingAs($this->admin)->post('/admin/employees/import', ['file' => csvFile("Name,Department Name\nLee,IT\n")]);

    expect(Employee::where('name', 'Lee')->first()->email)->toBeNull();
});

test('a file with no data rows is rejected with an error', function () {
    $this->actingAs($this->admin)
        ->post('/admin/employees/import', ['file' => csvFile("Employee Name,Department,Email\n")])
        ->assertSessionHas('error', 'The CSV file has no data rows.');
});

test('the template downloads with employee headers', function () {
    $response = $this->actingAs($this->admin)->get('/admin/employees/template');

    $response->assertDownload('employees-template.csv');
    expect($response->streamedContent())->toContain('"Employee Name",Department,Email');
});

test('the employees page shows the import form', function () {
    $this->actingAs($this->admin)->get('/admin/employees')
        ->assertSee(route('admin.employees.import'), false)
        ->assertSee(route('admin.employees.template'), false);
});

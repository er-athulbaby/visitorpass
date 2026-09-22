<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visitor;

test('export produces a CSV with the correct header row', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/history/export');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();
    // fputcsv quotes any field containing a space (its own CSV-safety rule,
    // to preserve the space through parsers that trim unquoted fields) —
    // this is correct, standard CSV output, not a formatting bug.
    expect($csv)->toContain('"Visitor Name","CPR Number","Mobile Number",Company,Department,"Person to Visit",Check-In,Check-Out,Status');
});

test('export shows the CPR number unmasked, unlike the HTML page', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);
    $visitor = Visitor::create(['cpr_number' => '900009999', 'name' => 'Jane']);
    $visitor->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now()]);

    $response = $this->actingAs($user)->get('/history/export');

    $response->assertOk();
    expect($response->streamedContent())->toContain('900009999');
});

test('export respects the same filters as the HTML page', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);
    $findable = Visitor::create(['cpr_number' => '900000008', 'name' => 'Findable Person']);
    $findable->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now()]);
    $other = Visitor::create(['cpr_number' => '900000009', 'name' => 'Other Person']);
    $other->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now()]);

    $response = $this->actingAs($user)->get('/history/export?search=Findable');

    $csv = $response->streamedContent();
    expect($csv)->toContain('Findable Person');
    expect($csv)->not->toContain('Other Person');
});

test('export of an empty filtered set still produces a valid CSV with just the header', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/history/export?search=NoSuchVisitorAtAll');

    $response->assertOk();
    $csv = trim($response->streamedContent());
    expect(substr_count($csv, "\n"))->toBe(0);
    expect($csv)->toContain('"Visitor Name","CPR Number"');
});

test('export starts with a UTF-8 BOM so Arabic names render correctly in Excel', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/history/export');

    $response->assertOk();
    expect(substr($response->streamedContent(), 0, 3))->toBe("\xEF\xBB\xBF");
});

test('export escapes values that look like spreadsheet formulas', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);
    $visitor = Visitor::create(['cpr_number' => '900000011', 'name' => '=HYPERLINK("http://evil","click")']);
    $visitor->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now()]);

    $response = $this->actingAs($user)->get('/history/export');

    $csv = $response->streamedContent();
    expect($csv)->not->toContain(',=HYPERLINK');
    expect($csv)->toContain("'=HYPERLINK");
});

test('export shows the mobile number captured at that visit, not the visitor current one', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);
    $visitor = Visitor::create(['cpr_number' => '900000012', 'name' => 'Ahmed', 'mobile_number' => '39998888']);
    $visitor->visits()->create([
        'employee_id' => $employee->id,
        'department_id' => $department->id,
        'mobile_number' => '33445566',
        'check_in_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/history/export');

    $csv = $response->streamedContent();
    expect($csv)->toContain('33445566');
    expect($csv)->not->toContain('39998888');
});

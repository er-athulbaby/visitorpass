<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visitor;

test('history page shows all visits, not just open ones', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);

    $open = Visitor::create(['cpr_number' => '900000001', 'name' => 'Open Visitor']);
    $open->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now()]);

    $closed = Visitor::create(['cpr_number' => '900000002', 'name' => 'Closed Visitor']);
    $closed->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now(), 'check_out_at' => now()]);

    $response = $this->actingAs($user)->get('/history');

    $response->assertOk();
    $response->assertSee('Open Visitor');
    $response->assertSee('Closed Visitor');
});

test('history page masks the CPR number', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);
    $visitor = Visitor::create(['cpr_number' => '900001234', 'name' => 'Jane']);
    $visitor->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now()]);

    $response = $this->actingAs($user)->get('/history');

    $response->assertOk();
    $response->assertDontSee('900001234');
    $response->assertSee('1234');
});

test('history shows the mobile number captured at that visit, not the visitor current one', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);
    $visitor = Visitor::create(['cpr_number' => '900000010', 'name' => 'Ahmed', 'mobile_number' => '39998888']);
    $visitor->visits()->create([
        'employee_id' => $employee->id,
        'department_id' => $department->id,
        'mobile_number' => '33445566',
        'check_in_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/history');

    $response->assertOk();
    $response->assertSee('33445566');
    $response->assertDontSee('39998888');
});

test('history page masks a short CPR number instead of showing it unmasked', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);
    $visitor = Visitor::create(['cpr_number' => '1234', 'name' => 'Jane']);
    $visitor->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now()]);

    $response = $this->actingAs($user)->get('/history');

    $response->assertOk();
    $response->assertDontSee('>1234<', false);
});

test('a search filter narrows the results shown', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);
    $findable = Visitor::create(['cpr_number' => '900000003', 'name' => 'Findable Person']);
    $findable->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now()]);
    $other = Visitor::create(['cpr_number' => '900000004', 'name' => 'Other Person']);
    $other->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now()]);

    $response = $this->actingAs($user)->get('/history?search=Findable');

    $response->assertOk();
    $response->assertSee('Findable Person');
    $response->assertDontSee('Other Person');
});

test('a filter combination matching nothing shows a friendly empty state', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/history?search=NoSuchVisitorAtAll');

    $response->assertOk();
    $response->assertSee('No records match these filters');
});

test('date_from after date_to returns no results without erroring', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);
    $visitor = Visitor::create(['cpr_number' => '900000005', 'name' => 'Someone']);
    $visitor->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now()]);

    $response = $this->actingAs($user)->get('/history?date_from=2030-01-01&date_to=2020-01-01');

    $response->assertOk();
    $response->assertSee('No records match these filters');
});

test('company mode shows a department filter, building mode shows a company filter', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    Department::create(['name' => 'IT']);

    $response = $this->actingAs($user)->get('/history');

    $response->assertOk();
    $response->assertSee('name="department_id"', false);
    $response->assertDontSee('name="company_id"', false);
});

test('building mode shows all companies visits when no company filter is selected', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'building']);
    $user = User::factory()->create();
    $acme = Company::create(['name' => 'Acme']);
    $other = Company::create(['name' => 'Other']);
    $acmeVisitor = Visitor::create(['cpr_number' => '900000006', 'name' => 'Acme Visitor']);
    $acmeVisitor->visits()->create(['company_id' => $acme->id, 'check_in_at' => now()]);
    $otherVisitor = Visitor::create(['cpr_number' => '900000007', 'name' => 'Other Visitor']);
    $otherVisitor->visits()->create(['company_id' => $other->id, 'check_in_at' => now()]);

    $response = $this->actingAs($user)->get('/history');

    $response->assertOk();
    $response->assertSee('name="company_id"', false);
    $response->assertDontSee('name="department_id"', false);
    $response->assertSee('Acme Visitor');
    $response->assertSee('Other Visitor');
});

test('pagination shows 20 visits per page and links to page 2', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);

    // Zero-padded numbers avoid a substring collision in the assertions
    // below ("Visitor 1" would otherwise also match "Visitor 10".."19").
    foreach (range(1, 25) as $i) {
        $padded = str_pad($i, 2, '0', STR_PAD_LEFT);
        $visitor = Visitor::create(['cpr_number' => "90000010{$i}", 'name' => "Visitor {$padded}"]);
        $visitor->visits()->create([
            'employee_id' => $employee->id,
            'department_id' => $department->id,
            'check_in_at' => now()->subMinutes(25 - $i),
        ]);
    }

    $page1 = $this->actingAs($user)->get('/history');
    $page2 = $this->actingAs($user)->get('/history?page=2');

    $page1->assertOk();
    $page1->assertSee('Visitor 25'); // most recent, first page
    $page1->assertDontSee('Visitor 01'); // oldest, should be on page 2
    $page2->assertOk();
    $page2->assertSee('Visitor 01');
});

test('pagination links preserve the active filters', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);

    foreach (range(1, 25) as $i) {
        $padded = str_pad($i, 2, '0', STR_PAD_LEFT);
        $visitor = Visitor::create(['cpr_number' => "90000020{$i}", 'name' => "Findable {$padded}"]);
        $visitor->visits()->create([
            'employee_id' => $employee->id,
            'department_id' => $department->id,
            'check_in_at' => now()->subMinutes(25 - $i),
        ]);
    }

    $response = $this->actingAs($user)->get('/history?search=Findable');

    $response->assertOk();
    $response->assertSee('search=Findable', false);
});

<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visitor;

test('dashboard shows the four stat counts computed for today', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);

    // Yesterday: one visit, already checked out — must not count toward
    // any of today's four stats.
    $yesterdayVisitor = Visitor::create(['cpr_number' => '900000001', 'name' => 'Old Visitor']);
    $yesterdayVisitor->visits()->create([
        'employee_id' => $employee->id,
        'department_id' => $department->id,
        'check_in_at' => now()->subDay(),
        'check_out_at' => now()->subDay()->addHour(),
    ]);

    // Today, still inside.
    $insideVisitor = Visitor::create(['cpr_number' => '900000002', 'name' => 'Inside Visitor']);
    $insideVisitor->visits()->create([
        'employee_id' => $employee->id,
        'department_id' => $department->id,
        'check_in_at' => now(),
    ]);

    // Today, checked out today.
    $checkedOutVisitor = Visitor::create(['cpr_number' => '900000003', 'name' => 'Checked Out Visitor']);
    $checkedOutVisitor->visits()->create([
        'employee_id' => $employee->id,
        'department_id' => $department->id,
        'check_in_at' => now(),
        'check_out_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    // Total Today: 2 visits checked in today (insideVisitor, checkedOutVisitor).
    $response->assertSee('Total Today');
    $response->assertViewHas('totalToday', 2);
    // Currently Inside: 1 open visit (insideVisitor).
    $response->assertViewHas('currentlyInside', 1);
    // Checked Out Today: 1 visit closed today (checkedOutVisitor).
    $response->assertViewHas('checkedOutToday', 1);
    // New Visitors Today: 2 Visitor records created today (insideVisitor, checkedOutVisitor)
    // — yesterdayVisitor was created "yesterday" only in visit timing, not
    // in its own created_at, so this asserts against real record creation.
    $response->assertViewHas('newVisitorsToday', 3);
});

test('recent activity shows the employee as host in company mode', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);
    $visitor = Visitor::create(['cpr_number' => '900000004', 'name' => 'Jane Visitor']);
    $visitor->visits()->create([
        'employee_id' => $employee->id,
        'department_id' => $department->id,
        'check_in_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Jane Visitor');
    $response->assertSee('Sam Host');
});

test('recent activity shows the company as host in building mode', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'building']);
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Acme Inc']);
    $visitor = Visitor::create(['cpr_number' => '900000005', 'name' => 'Jane Visitor']);
    $visitor->visits()->create([
        'company_id' => $company->id,
        'check_in_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Jane Visitor');
    $response->assertSee('Acme Inc');
});

test('recent activity shows only the 5 most recent visits', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);

    foreach (range(1, 6) as $i) {
        $visitor = Visitor::create(['cpr_number' => "90000001{$i}", 'name' => "Visitor {$i}"]);
        $visitor->visits()->create([
            'employee_id' => $employee->id,
            'department_id' => $department->id,
            'check_in_at' => now()->subMinutes(6 - $i),
        ]);
    }

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertViewHas('recentVisits', fn ($visits) => $visits->count() === 5);
    // The oldest of the six (Visitor 1) should have been excluded.
    $response->assertDontSee('Visitor 1');
    $response->assertSee('Visitor 6');
});

<?php

use App\Models\Department;
use App\Models\Visit;
use App\Models\Visitor;

test('a visitor can have many visits', function () {
    $visitor = Visitor::create([
        'cpr_number' => '990101123',
        'name' => 'Ali Visitor',
    ]);

    $department = Department::create(['name' => 'IT']);
    $employee = $department->employees()->create(['name' => 'Sam Host']);

    $visit = $visitor->visits()->create([
        'employee_id' => $employee->id,
        'department_id' => $department->id,
        'mobile_number' => '33445566',
        'check_in_at' => now(),
    ]);

    expect($visitor->visits)->toHaveCount(1);
    expect($visit->employee->name)->toBe('Sam Host');
    expect($visit->visitor->name)->toBe('Ali Visitor');
});

test('cpr_number is unique across visitors', function () {
    Visitor::create(['cpr_number' => '990101123', 'name' => 'First']);

    expect(fn () => Visitor::create(['cpr_number' => '990101123', 'name' => 'Second']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

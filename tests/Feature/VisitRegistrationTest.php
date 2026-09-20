<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visitor;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->receptionist = User::factory()->create();
    $this->receptionist->assignRole('receptionist');
});

test('company mode registers a visit against an employee and department', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $department = Department::create(['name' => 'IT']);
    $employee = $department->employees()->create(['name' => 'Sam Host']);

    $response = $this->actingAs($this->receptionist)->post('/visits', [
        'cpr_number' => '990101123',
        'name' => 'Ali Visitor',
        'mobile_number' => '33445566',
        'employee_id' => $employee->id,
    ]);

    $response->assertRedirect('/visits');
    $visitor = Visitor::where('cpr_number', '990101123')->first();
    expect($visitor)->not->toBeNull();
    expect($visitor->visits()->first()->employee_id)->toBe($employee->id);
    expect($visitor->visits()->first()->check_out_at)->toBeNull();
});

test('company mode rejects a company_id field', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $company = Company::create(['name' => 'Should not be used here']);

    $response = $this->actingAs($this->receptionist)->post('/visits', [
        'cpr_number' => '990101123',
        'name' => 'Ali Visitor',
        'company_id' => $company->id,
    ]);

    $response->assertSessionHasErrors('employee_id');
});

test('building mode registers a visit against a company only', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'building']);
    $company = Company::create(['name' => 'Acme Corp']);

    $response = $this->actingAs($this->receptionist)->post('/visits', [
        'cpr_number' => '990101124',
        'name' => 'Fatima Visitor',
        'company_id' => $company->id,
    ]);

    $response->assertRedirect('/visits');
    $visitor = Visitor::where('cpr_number', '990101124')->first();
    expect($visitor->visits()->first()->company_id)->toBe($company->id);
    expect($visitor->visits()->first()->employee_id)->toBeNull();
});

test('registering a returning visitor by existing cpr_number reuses the visitor record', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $department = Department::create(['name' => 'IT']);
    $employee = $department->employees()->create(['name' => 'Sam Host']);

    Visitor::create(['cpr_number' => '990101123', 'name' => 'Ali Visitor', 'mobile_number' => '33445566']);

    $this->actingAs($this->receptionist)->post('/visits', [
        'cpr_number' => '990101123',
        'name' => 'Ali Visitor',
        'employee_id' => $employee->id,
    ]);

    expect(Visitor::where('cpr_number', '990101123')->count())->toBe(1);
});

test('registering a returning visitor without retyping mobile number keeps the stored one', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $department = Department::create(['name' => 'IT']);
    $employee = $department->employees()->create(['name' => 'Sam Host']);

    Visitor::create(['cpr_number' => '990101123', 'name' => 'Ali Visitor', 'mobile_number' => '33445566', 'company_name' => 'Acme']);

    $this->actingAs($this->receptionist)->post('/visits', [
        'cpr_number' => '990101123',
        'name' => 'Ali Visitor',
        'employee_id' => $employee->id,
    ]);

    $visitor = Visitor::where('cpr_number', '990101123')->first();
    expect($visitor->mobile_number)->toBe('33445566');
    expect($visitor->company_name)->toBe('Acme');
});

test('building mode rejects an employee_id field', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'building']);
    $department = Department::create(['name' => 'IT']);
    $employee = $department->employees()->create(['name' => 'Sam Host']);

    $response = $this->actingAs($this->receptionist)->post('/visits', [
        'cpr_number' => '990101124',
        'name' => 'Fatima Visitor',
        'employee_id' => $employee->id,
    ]);

    $response->assertSessionHasErrors('company_id');
});

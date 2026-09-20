<?php

use App\Models\Department;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visitor;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $this->receptionist = User::factory()->create();
    $this->receptionist->assignRole('receptionist');

    $department = Department::create(['name' => 'IT']);
    $this->employee = $department->employees()->create(['name' => 'Sam Host']);
});

test('receptionist can check out an open visit', function () {
    $visitor = Visitor::create(['cpr_number' => '990101123', 'name' => 'Ali Visitor']);
    $visit = $visitor->visits()->create([
        'employee_id' => $this->employee->id,
        'check_in_at' => now(),
    ]);

    $response = $this->actingAs($this->receptionist)->patch("/visits/{$visit->id}/check-out");

    $response->assertRedirect('/visits');
    expect($visit->fresh()->check_out_at)->not->toBeNull();
});

test('checking out an already-closed visit shows an error instead of duplicating it', function () {
    $visitor = Visitor::create(['cpr_number' => '990101123', 'name' => 'Ali Visitor']);
    $visit = $visitor->visits()->create([
        'employee_id' => $this->employee->id,
        'check_in_at' => now(),
        'check_out_at' => now(),
    ]);

    $response = $this->actingAs($this->receptionist)->patch("/visits/{$visit->id}/check-out");

    $response->assertSessionHas('error');
});

test('open visits list shows only visitors who have not checked out', function () {
    $visitor = Visitor::create(['cpr_number' => '990101123', 'name' => 'Open Visitor']);
    $visitor->visits()->create(['employee_id' => $this->employee->id, 'check_in_at' => now()]);

    $closedVisitor = Visitor::create(['cpr_number' => '990101124', 'name' => 'Closed Visitor']);
    $closedVisitor->visits()->create(['employee_id' => $this->employee->id, 'check_in_at' => now()->subHour(), 'check_out_at' => now()]);

    $response = $this->actingAs($this->receptionist)->get('/visits');

    $response->assertSee('Open Visitor');
    $response->assertDontSee('Closed Visitor');
});

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

test('admin can create a department', function () {
    $response = $this->actingAs($this->admin)->post('/admin/departments', [
        'name' => 'Human Resources',
    ]);

    $response->assertRedirect('/admin/departments');
    expect(Department::where('name', 'Human Resources')->exists())->toBeTrue();
});

test('admin can create an employee under a department', function () {
    $department = Department::create(['name' => 'IT']);

    $response = $this->actingAs($this->admin)->post('/admin/employees', [
        'department_id' => $department->id,
        'name' => 'Sam Employee',
        'email' => 'sam@example.com',
    ]);

    $response->assertRedirect('/admin/employees');
    expect($department->employees()->where('name', 'Sam Employee')->exists())->toBeTrue();
});

test('deleting a department that still has employees is blocked with a friendly error', function () {
    $department = Department::create(['name' => 'Finance']);
    $department->employees()->create(['name' => 'Pat Employee']);

    $response = $this->actingAs($this->admin)->delete("/admin/departments/{$department->id}");

    $response->assertSessionHas('error');
    expect(Department::find($department->id))->not->toBeNull();
});

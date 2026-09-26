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
    $this->hr = Department::create(['name' => 'HR']);
    $this->employee = Employee::create(['department_id' => $this->it->id, 'name' => 'Athul', 'email' => null]);
});

test('the employee list links to an edit form prefilled with the employee', function () {
    $this->actingAs($this->admin)->get('/admin/employees')
        ->assertSee(route('admin.employees.edit', $this->employee), false);

    $this->actingAs($this->admin)->get(route('admin.employees.edit', $this->employee))
        ->assertOk()
        ->assertSee('value="Athul"', false)
        ->assertSee('value="'.$this->it->id.'" selected', false);
});

test('an admin can change an employee name, department and email', function () {
    $this->actingAs($this->admin)->put(route('admin.employees.update', $this->employee), [
        'name' => 'Athul Baby',
        'department_id' => $this->hr->id,
        'email' => 'athul@example.test',
    ])->assertRedirect('/admin/employees')->assertSessionHas('status', 'Employee updated.');

    expect($this->employee->fresh()->only('name', 'department_id', 'email'))
        ->toBe(['name' => 'Athul Baby', 'department_id' => $this->hr->id, 'email' => 'athul@example.test']);
});

test('the edit form validates like the add form', function () {
    $this->actingAs($this->admin)->put(route('admin.employees.update', $this->employee), [
        'name' => '',
        'department_id' => 999,
        'email' => 'not-an-email',
    ])->assertSessionHasErrors(['name', 'department_id', 'email']);

    expect($this->employee->fresh()->name)->toBe('Athul');
});

test('a receptionist cannot edit employees', function () {
    $receptionist = User::factory()->create();
    $receptionist->assignRole('receptionist');

    $this->actingAs($receptionist)->get(route('admin.employees.edit', $this->employee))->assertForbidden();
    $this->actingAs($receptionist)->put(route('admin.employees.update', $this->employee), ['name' => 'X', 'department_id' => $this->it->id])->assertForbidden();
});

test('delete buttons ask for confirmation before deleting', function () {
    $this->actingAs($this->admin)->get('/admin/employees')
        ->assertSee('onsubmit="return confirm(', false);
});

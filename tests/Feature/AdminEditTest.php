<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->admin = User::factory()->create(['name' => 'Main Admin']);
    $this->admin->assignRole('admin');
});

test('an admin can rename a department from its edit page', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $department = Department::create(['name' => 'IT']);

    $this->actingAs($this->admin)->get('/admin/departments')
        ->assertSee(route('admin.departments.edit', $department), false);
    $this->actingAs($this->admin)->get(route('admin.departments.edit', $department))
        ->assertOk()->assertSee('value="IT"', false);

    $this->actingAs($this->admin)->put(route('admin.departments.update', $department), ['name' => 'Information Technology'])
        ->assertRedirect('/admin/departments')->assertSessionHas('status', 'Department updated.');
    expect($department->fresh()->name)->toBe('Information Technology');

    $this->actingAs($this->admin)->put(route('admin.departments.update', $department), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('an admin can change a company name and contact email', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'building']);
    $company = Company::create(['name' => 'Acme', 'contact_email' => null]);

    $this->actingAs($this->admin)->get('/admin/companies')
        ->assertSee(route('admin.companies.edit', $company), false);
    $this->actingAs($this->admin)->get(route('admin.companies.edit', $company))
        ->assertOk()->assertSee('value="Acme"', false);

    $this->actingAs($this->admin)->put(route('admin.companies.update', $company), ['name' => 'Acme Ltd', 'contact_email' => 'desk@acme.test'])
        ->assertRedirect('/admin/companies')->assertSessionHas('status', 'Company updated.');
    expect($company->fresh()->only('name', 'contact_email'))->toBe(['name' => 'Acme Ltd', 'contact_email' => 'desk@acme.test']);

    $this->actingAs($this->admin)->put(route('admin.companies.update', $company), ['name' => 'Acme', 'contact_email' => 'nope'])
        ->assertSessionHasErrors('contact_email');
});

test('an admin can change a user name, email and role, and the password only when one is typed', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create(['name' => 'Desk', 'email' => 'desk@example.test', 'password' => Hash::make('original-pass')]);
    $user->assignRole('receptionist');

    $this->actingAs($this->admin)->get('/admin/users')
        ->assertSee(route('admin.users.edit', $user), false);
    $this->actingAs($this->admin)->get(route('admin.users.edit', $user))
        ->assertOk()->assertSee('value="desk@example.test"', false);

    $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
        'name' => 'Front Desk', 'email' => 'front@example.test', 'role' => 'admin', 'password' => '',
    ])->assertRedirect('/admin/users')->assertSessionHas('status', 'User updated.');

    $user->refresh();
    expect($user->only('name', 'email'))->toBe(['name' => 'Front Desk', 'email' => 'front@example.test']);
    expect($user->hasRole('admin'))->toBeTrue();
    expect($user->hasRole('receptionist'))->toBeFalse();
    expect(Hash::check('original-pass', $user->password))->toBeTrue();

    $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
        'name' => 'Front Desk', 'email' => 'front@example.test', 'role' => 'admin', 'password' => 'brand-new-pass',
    ]);
    expect(Hash::check('brand-new-pass', $user->fresh()->password))->toBeTrue();
});

test('user email must stay unique but may keep its own address', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create(['email' => 'desk@example.test']);
    $user->assignRole('receptionist');

    $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
        'name' => 'Desk', 'email' => $this->admin->email, 'role' => 'receptionist',
    ])->assertSessionHasErrors('email');

    $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
        'name' => 'Desk renamed', 'email' => 'desk@example.test', 'role' => 'receptionist',
    ])->assertSessionHasNoErrors();
});

// Only admins reach this form, so the editor is always an admin: blocking a
// change to your own role is what guarantees at least one admin remains.
test('an admin cannot change their own role', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    User::factory()->create()->assignRole('admin');

    $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
        'name' => 'Main Admin', 'email' => $this->admin->email, 'role' => 'receptionist',
    ])->assertSessionHas('error', 'You cannot change your own role.');

    expect($this->admin->fresh()->hasRole('admin'))->toBeTrue();
});

test('a receptionist cannot open or submit any edit form', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $receptionist = User::factory()->create();
    $receptionist->assignRole('receptionist');
    $department = Department::create(['name' => 'IT']);

    $this->actingAs($receptionist)->get(route('admin.departments.edit', $department))->assertForbidden();
    $this->actingAs($receptionist)->put(route('admin.users.update', $receptionist), ['name' => 'X', 'email' => 'x@example.test', 'role' => 'admin'])->assertForbidden();
    expect($receptionist->fresh()->hasRole('admin'))->toBeFalse();
});

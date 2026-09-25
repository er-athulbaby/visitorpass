<?php

use App\Models\Setting;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('an admin in company mode sees all nav sections with a single admin link', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('Home');
    $response->assertSee('Scan');
    $response->assertSee('Visitors');
    $response->assertSee('History');
    $response->assertSee('Admin');
    $response->assertSee(route('admin.departments.index'), false);
    $response->assertDontSee('Employees');
    $response->assertDontSee('Companies');
});

test('an admin in building mode sees companies instead of departments and employees', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'building']);
    Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertOk();
    $response->assertSee(route('admin.companies.index'), false);
    $response->assertDontSee('Departments');
    $response->assertDontSee('Employees');
});

test('a receptionist sees no admin section at all', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    Role::firstOrCreate(['name' => 'receptionist']);
    $user = User::factory()->create();
    $user->assignRole('receptionist');

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertDontSee('Departments');
    $response->assertDontSee('Employees');
    $response->assertDontSee('Companies');
});

test('the header slot still renders on pages that pass one', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/profile');

    $response->assertOk();
    // Asserting on the header wrapper's own markup, not just the word
    // "Profile" — that word also appears in the page body's own heading
    // ("Profile Information"), so a plain assertSee('Profile') would
    // still pass even if the $header slot were deleted entirely.
    $response->assertSee('font-semibold text-xl text-gray-800 leading-tight', false);
});

test('the bottom nav is hidden on desktop widths via md:hidden', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('bottom-0 flex border-t border-outline-variant bg-surface-container-lowest md:hidden', false);
});

test('the sidebar divider uses a logical border side so it renders correctly in RTL', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('border-e', false);
    $response->assertDontSee('border-r ', false);
});

test('the profile page is reachable from the sidebar', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee(route('profile.edit'), false);
});

test('the locale toggle is still present and functional in the new sidebar', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create(['locale' => 'en']);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee(route('locale.update'), false);
    $response->assertSee('value="ar"', false);
});

test('the locale toggle has a language icon', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSeeInOrder([route('locale.update'), 'material-symbols-outlined', '>language<'], false);
});

test('the sidebar footer credits the developer with a version and a hyperlink', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('v1.0', false);
    $response->assertSee('href="https://deverra.me"', false);
    $response->assertSee('DeVerra Technologies', false);
});

test('every existing page that used the old layout still renders under the new one', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/dashboard')->assertOk();
    $this->actingAs($admin)->get('/profile')->assertOk();
    $this->actingAs($admin)->get('/visits')->assertOk();
    $this->actingAs($admin)->get('/visits/create')->assertOk();
    $this->actingAs($admin)->get('/history')->assertOk();
    $this->actingAs($admin)->get('/admin/departments')->assertOk();
    $this->actingAs($admin)->get('/admin/employees')->assertOk();
    $this->actingAs($admin)->get('/admin/users')->assertOk();
    $this->actingAs($admin)->get('/admin/settings')->assertOk();
});

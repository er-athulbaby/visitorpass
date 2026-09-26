<?php

use App\Mail\VisitorCheckedIn;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('company mode sends a check-in email to the selected employee', function () {
    Mail::fake();

    Setting::create([
        'id' => 1,
        'deployment_mode' => 'company',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'notify@example.com',
        'smtp_password_encrypted' => 'secret',
        'smtp_from_address' => 'notify@example.com',
    ]);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'Engineering']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Host Person', 'email' => 'host@example.com']);

    $this->actingAs($user)->post('/visits', [
        'cpr_number' => '900000000',
        'name' => 'Jane Visitor',
        'company_name' => 'Acme Inc',
        'employee_id' => $employee->id,
    ]);

    Mail::assertSent(VisitorCheckedIn::class, fn ($mail) => $mail->hasTo('host@example.com'));
});

test('building mode sends a check-in email to the selected company contact', function () {
    Mail::fake();

    Setting::create([
        'id' => 1,
        'deployment_mode' => 'building',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'notify@example.com',
        'smtp_password_encrypted' => 'secret',
        'smtp_from_address' => 'notify@example.com',
    ]);
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Acme Inc', 'contact_email' => 'contact@acme.test']);

    $this->actingAs($user)->post('/visits', [
        'cpr_number' => '900000001',
        'name' => 'Jane Visitor',
        'company_id' => $company->id,
    ]);

    Mail::assertSent(VisitorCheckedIn::class, fn ($mail) => $mail->hasTo('contact@acme.test'));
});

test('check-in succeeds and sends nothing when the employee has no email on file', function () {
    Mail::fake();

    Setting::create([
        'id' => 1,
        'deployment_mode' => 'company',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_from_address' => 'notify@example.com',
    ]);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'Engineering']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Host Person', 'email' => null]);

    $response = $this->actingAs($user)->post('/visits', [
        'cpr_number' => '900000002',
        'name' => 'Jane Visitor',
        'employee_id' => $employee->id,
    ]);

    $response->assertRedirect(route('visits.index'));
    Mail::assertNothingSent();
});

test('check-in succeeds even when the SMTP send throws', function () {
    Setting::create([
        'id' => 1,
        'deployment_mode' => 'company',
        'smtp_host' => 'smtp.invalid-host-does-not-exist.test',
        'smtp_port' => 587,
        'smtp_username' => 'notify@example.com',
        'smtp_password_encrypted' => 'secret',
        'smtp_from_address' => 'notify@example.com',
    ]);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'Engineering']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Host Person', 'email' => 'host@example.com']);

    $response = $this->actingAs($user)->post('/visits', [
        'cpr_number' => '900000003',
        'name' => 'Jane Visitor',
        'employee_id' => $employee->id,
    ]);

    $response->assertRedirect(route('visits.index'))->assertSessionHas('status');
});

test('the check-in email includes the visitor mobile number from this visit', function () {
    $visitor = \App\Models\Visitor::create(['cpr_number' => '900000077', 'name' => 'Renjith', 'mobile_number' => '33000000']);
    $visit = \App\Models\Visit::create(['visitor_id' => $visitor->id, 'mobile_number' => '36999999', 'check_in_at' => now()]);

    $html = (new VisitorCheckedIn($visit))->render();

    expect($html)->toContain('36999999');
});

test('the check-in email falls back to the saved mobile number and omits the line when there is none', function () {
    $visitor = \App\Models\Visitor::create(['cpr_number' => '900000078', 'name' => 'Sam', 'mobile_number' => '33000000']);
    $withSaved = \App\Models\Visit::create(['visitor_id' => $visitor->id, 'check_in_at' => now()]);
    expect((new VisitorCheckedIn($withSaved))->render())->toContain('33000000');

    $nobody = \App\Models\Visitor::create(['cpr_number' => '900000079', 'name' => 'Lee', 'mobile_number' => null]);
    $without = \App\Models\Visit::create(['visitor_id' => $nobody->id, 'check_in_at' => now()]);
    expect((new VisitorCheckedIn($without))->render())->not->toContain('Mobile');
});

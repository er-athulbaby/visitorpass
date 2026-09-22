<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Visit;
use App\Models\Visitor;

function makeVisit(array $visitorAttrs, array $visitAttrs): Visit
{
    $visitor = Visitor::create(array_merge(['cpr_number' => uniqid('cpr_'), 'name' => 'Someone'], $visitorAttrs));

    return $visitor->visits()->create($visitAttrs);
}

test('filtered search matches the visitor name', function () {
    $match = makeVisit(['name' => 'Jane Findable'], ['check_in_at' => now()]);
    $noMatch = makeVisit(['name' => 'Other Person'], ['check_in_at' => now()]);

    $results = Visit::filtered(['search' => 'Findable'])->get();

    expect($results->pluck('id'))->toContain($match->id)->not->toContain($noMatch->id);
});

test('filtered search matches the CPR number', function () {
    $match = makeVisit(['cpr_number' => '900011122'], ['check_in_at' => now()]);
    $noMatch = makeVisit(['cpr_number' => '900099999'], ['check_in_at' => now()]);

    $results = Visit::filtered(['search' => '1112'])->get();

    expect($results->pluck('id'))->toContain($match->id)->not->toContain($noMatch->id);
});

test('filtered search matches the mobile number', function () {
    $match = makeVisit(['name' => 'A', 'mobile_number' => '33445566'], ['check_in_at' => now()]);
    $noMatch = makeVisit(['name' => 'B', 'mobile_number' => '11223344'], ['check_in_at' => now()]);

    $results = Visit::filtered(['search' => '3344556'])->get();

    expect($results->pluck('id'))->toContain($match->id)->not->toContain($noMatch->id);
});

test('filtered search matches the visitor company_name free-text field, not the related Company model', function () {
    $match = makeVisit(['company_name' => 'Acme Traders'], ['check_in_at' => now()]);
    $noMatch = makeVisit(['company_name' => 'Other Co'], ['check_in_at' => now()]);

    $results = Visit::filtered(['search' => 'Acme'])->get();

    expect($results->pluck('id'))->toContain($match->id)->not->toContain($noMatch->id);
});

test('filtered date range is inclusive of the from date', function () {
    $inRange = makeVisit(['name' => 'A'], ['check_in_at' => '2026-01-10 09:00:00']);
    $before = makeVisit(['name' => 'B'], ['check_in_at' => '2026-01-09 23:59:59']);

    $results = Visit::filtered(['date_from' => '2026-01-10'])->get();

    expect($results->pluck('id'))->toContain($inRange->id)->not->toContain($before->id);
});

test('filtered date range includes the entire to date, not just midnight', function () {
    $lateSameDay = makeVisit(['name' => 'A'], ['check_in_at' => '2026-01-10 23:30:00']);
    $nextDay = makeVisit(['name' => 'B'], ['check_in_at' => '2026-01-11 00:00:01']);

    $results = Visit::filtered(['date_to' => '2026-01-10'])->get();

    expect($results->pluck('id'))->toContain($lateSameDay->id)->not->toContain($nextDay->id);
});

test('filtered status inside returns only open visits', function () {
    $open = makeVisit(['name' => 'A'], ['check_in_at' => now()]);
    $closed = makeVisit(['name' => 'B'], ['check_in_at' => now(), 'check_out_at' => now()]);

    $results = Visit::filtered(['status' => 'inside'])->get();

    expect($results->pluck('id'))->toContain($open->id)->not->toContain($closed->id);
});

test('filtered status checked_out returns only closed visits', function () {
    $open = makeVisit(['name' => 'A'], ['check_in_at' => now()]);
    $closed = makeVisit(['name' => 'B'], ['check_in_at' => now(), 'check_out_at' => now()]);

    $results = Visit::filtered(['status' => 'checked_out'])->get();

    expect($results->pluck('id'))->toContain($closed->id)->not->toContain($open->id);
});

test('filtered department_id matches only that department', function () {
    $it = Department::create(['name' => 'IT']);
    $hr = Department::create(['name' => 'HR']);
    $itEmployee = Employee::create(['department_id' => $it->id, 'name' => 'IT Person']);
    $hrEmployee = Employee::create(['department_id' => $hr->id, 'name' => 'HR Person']);

    $itVisit = makeVisit(['name' => 'A'], ['employee_id' => $itEmployee->id, 'department_id' => $it->id, 'check_in_at' => now()]);
    $hrVisit = makeVisit(['name' => 'B'], ['employee_id' => $hrEmployee->id, 'department_id' => $hr->id, 'check_in_at' => now()]);

    $results = Visit::filtered(['department_id' => $it->id])->get();

    expect($results->pluck('id'))->toContain($itVisit->id)->not->toContain($hrVisit->id);
});

test('filtered company_id matches only that company', function () {
    $acme = Company::create(['name' => 'Acme']);
    $other = Company::create(['name' => 'Other']);

    $acmeVisit = makeVisit(['name' => 'A'], ['company_id' => $acme->id, 'check_in_at' => now()]);
    $otherVisit = makeVisit(['name' => 'B'], ['company_id' => $other->id, 'check_in_at' => now()]);

    $results = Visit::filtered(['company_id' => $acme->id])->get();

    expect($results->pluck('id'))->toContain($acmeVisit->id)->not->toContain($otherVisit->id);
});

test('filtered with no filters returns everything', function () {
    $a = makeVisit(['name' => 'A'], ['check_in_at' => now()]);
    $b = makeVisit(['name' => 'B'], ['check_in_at' => now()->subDay()]);

    $results = Visit::filtered([])->get();

    expect($results->pluck('id'))->toContain($a->id)->toContain($b->id);
});

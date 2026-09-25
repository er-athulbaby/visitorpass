<?php

use App\Support\Csv;

test('records are keyed by spreadsheet line with lower-cased trimmed headers', function () {
    $records = Csv::records(csvFile("\xEF\xBB\xBF Department Name ,Email\r\nHR , hr@x.test\r\n\r\nIT\r\nOps,o@x.test,extra\r\n"));

    expect($records)->toBe([
        2 => ['department name' => 'HR', 'email' => 'hr@x.test'],
        4 => ['department name' => 'IT', 'email' => ''],
        5 => ['department name' => 'Ops', 'email' => 'o@x.test'],
    ]);
});

test('a header-only file has no records', function () {
    expect(Csv::records(csvFile("Name\n")))->toBe([]);
});

test('field returns the first matching column case-insensitively or blank', function () {
    $record = ['name' => 'Sam', 'email' => ''];

    expect(Csv::field($record, 'Employee Name', 'Name'))->toBe('Sam');
    expect(Csv::field($record, 'Department'))->toBe('');
});

test('template streams a BOM and the header row as csv', function () {
    $response = Csv::template('t.csv', ['Company Name', 'Contact Email']);

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($body)->toBe("\xEF\xBB\xBF\"Company Name\",\"Contact Email\"\n");
});

test('a BOM before a quoted first header is stripped, so the downloaded template round-trips', function () {
    $records = Csv::records(csvFile("\xEF\xBB\xBF\"Company Name\",\"Contact Email\"\nAcme,a@x.test\n"));

    expect($records)->toBe([2 => ['company name' => 'Acme', 'contact email' => 'a@x.test']]);
});

test('a file that is not UTF-8 is rejected as a validation error on the file field', function () {
    Csv::records(csvFile("Name\nCaf\xE9\n"));
})->throws(\Illuminate\Validation\ValidationException::class, 'CSV UTF-8');

test('a missing required column is rejected naming the column and what was found', function () {
    Csv::records(csvFile("employee name;department\nSam;IT\n"), [['Employee Name', 'Name'], ['Department']]);
})->throws(\Illuminate\Validation\ValidationException::class, 'Missing column: Employee Name (found: employee name;department)');

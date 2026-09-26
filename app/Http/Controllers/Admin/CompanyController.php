<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyController extends Controller
{
    public function index(): View
    {
        return view('admin.companies.index', [
            'companies' => Company::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
        ]);

        Company::create($validated);

        return redirect()->route('admin.companies.index')
            ->with('status', __('Company created.'));
    }

    public function edit(Company $company): View
    {
        return view('admin.companies.edit', ['company' => $company]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $company->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
        ]));

        return redirect()->route('admin.companies.index')
            ->with('status', __('Company updated.'));
    }

    public function template(): StreamedResponse
    {
        return Csv::template('companies-template.csv', ['Company Name', 'Contact Email']);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $records = Csv::records($request->file('file'), [['Company Name', 'Name', 'Company']]);

        if ($records === []) {
            return redirect()->route('admin.companies.index')
                ->with('error', __('The CSV file has no data rows.'));
        }

        $companies = Company::all()->keyBy(fn ($company) => mb_strtolower($company->name));
        $created = 0;
        $updated = [];
        $skipped = [];
        $errors = [];

        foreach ($records as $row => $record) {
            $name = Csv::field($record, 'Company Name', 'Name', 'Company');
            $email = Csv::field($record, 'Contact Email', 'Email');

            if ($name === '' || mb_strlen($name) > 255) {
                $errors[] = ['row' => $row, 'message' => __('Missing or invalid company name')];

                continue;
            }

            if ($email !== '' && (mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
                $errors[] = ['row' => $row, 'message' => __('Invalid email ":email"', ['email' => $email])];

                continue;
            }

            if ($existing = $companies->get(mb_strtolower($name))) {
                if ($email !== '' && strcasecmp($email, (string) $existing->contact_email) !== 0) {
                    $existing->update(['contact_email' => $email]);
                    $updated[] = $name;
                } else {
                    $skipped[] = $name;
                }

                continue;
            }

            $companies->put(mb_strtolower($name), Company::create([
                'name' => $name,
                'contact_email' => $email !== '' ? $email : null,
            ]));
            $created++;
        }

        return redirect()->route('admin.companies.index')
            ->with('import', ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors]);
    }

    public function destroy(Company $company): RedirectResponse
    {
        $company->delete();

        return redirect()->route('admin.companies.index')
            ->with('status', __('Company deleted.'));
    }
}

<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\VisitHistoryController;
use App\Http\Controllers\VisitorLookupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::get('/install', [InstallController::class, 'index'])->name('install.index')->middleware(\App\Http\Middleware\RequireInstallToken::class);
Route::post('/install', [InstallController::class, 'store'])->name('install.store')->middleware([\App\Http\Middleware\RequireInstallToken::class, 'throttle:guest-forms']);

Route::get('/setup', [SetupController::class, 'index'])->name('setup.index')->middleware(\App\Http\Middleware\RequireInstallToken::class);
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store')->middleware([\App\Http\Middleware\RequireInstallToken::class, 'throttle:guest-forms']);

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/visits', [VisitController::class, 'index'])->name('visits.index');
    Route::get('/visits/create', [VisitController::class, 'create'])->name('visits.create');
    Route::post('/visits', [VisitController::class, 'store'])->name('visits.store');
    Route::patch('/visits/{visit}/check-out', [VisitController::class, 'checkOut'])->name('visits.check-out');

    Route::get('/history', [VisitHistoryController::class, 'index'])->name('history.index');
    Route::get('/history/export', [VisitHistoryController::class, 'export'])->name('history.export')->middleware('throttle:heavy');

    Route::get('/visitors/lookup', [VisitorLookupController::class, 'lookup'])->name('visitors.lookup')->middleware('throttle:lookup');
    Route::get('/visitors/autocomplete', [VisitorLookupController::class, 'autocomplete'])->name('visitors.autocomplete')->middleware('throttle:lookup');

    Route::patch('/locale', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate(['locale' => 'required|in:en,ar']);
        $request->user()->update(['locale' => $validated['locale']]);

        return back();
    })->name('locale.update');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('mode:company')->group(function () {
        Route::get('departments/template', [DepartmentController::class, 'template'])->name('departments.template');
        Route::post('departments/import', [DepartmentController::class, 'import'])->name('departments.import')->middleware('throttle:heavy');
        Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'destroy']);
        Route::get('employees/template', [EmployeeController::class, 'template'])->name('employees.template');
        Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import')->middleware('throttle:heavy');
        Route::resource('employees', EmployeeController::class)->only(['index', 'store', 'destroy']);
    });

    Route::middleware('mode:building')->group(function () {
        Route::get('companies/template', [CompanyController::class, 'template'])->name('companies.template');
        Route::post('companies/import', [CompanyController::class, 'import'])->name('companies.import')->middleware('throttle:heavy');
        Route::resource('companies', CompanyController::class)->only(['index', 'store', 'destroy']);
    });

    Route::resource('users', UserController::class)->only(['index', 'store', 'destroy']);

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/test-email', [SettingsController::class, 'testEmail'])->name('settings.test-email')->middleware('throttle:heavy');
});

require __DIR__.'/auth.php';

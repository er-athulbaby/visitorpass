<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\VisitorLookupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::get('/setup', [SetupController::class, 'index'])->name('setup.index');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/visits', [VisitController::class, 'index'])->name('visits.index');
    Route::get('/visits/create', [VisitController::class, 'create'])->name('visits.create');
    Route::post('/visits', [VisitController::class, 'store'])->name('visits.store');
    Route::patch('/visits/{visit}/check-out', [VisitController::class, 'checkOut'])->name('visits.check-out');

    Route::get('/visitors/lookup', [VisitorLookupController::class, 'lookup'])->name('visitors.lookup');
    Route::get('/visitors/autocomplete', [VisitorLookupController::class, 'autocomplete'])->name('visitors.autocomplete');

    Route::patch('/locale', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate(['locale' => 'required|in:en,ar']);
        $request->user()->update(['locale' => $validated['locale']]);

        return back();
    })->name('locale.update');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('mode:company')->group(function () {
        Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'destroy']);
        Route::resource('employees', EmployeeController::class)->only(['index', 'store', 'destroy']);
    });

    Route::middleware('mode:building')->group(function () {
        Route::resource('companies', CompanyController::class)->only(['index', 'store', 'destroy']);
    });

    Route::resource('users', UserController::class)->only(['index', 'store', 'destroy']);

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
});

require __DIR__.'/auth.php';

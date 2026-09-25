<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInstallRequest;
use App\Services\EnvFileWriter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InstallController extends Controller
{
    public function index()
    {
        return view('install.form', [
            'values' => [
                'db_host' => config('database.connections.mysql.host'),
                'db_port' => config('database.connections.mysql.port'),
                'db_database' => config('database.connections.mysql.database'),
                'db_username' => config('database.connections.mysql.username'),
                'app_url' => config('app.url'),
            ],
        ]);
    }

    public function store(StoreInstallRequest $request, EnvFileWriter $envFileWriter)
    {
        $validated = $request->validated();

        try {
            $envFileWriter->update([
                'DB_HOST' => $validated['db_host'],
                'DB_PORT' => $validated['db_port'],
                'DB_DATABASE' => $validated['db_database'],
                'DB_USERNAME' => $validated['db_username'],
                'DB_PASSWORD' => $validated['db_password'] ?? '',
                'APP_URL' => $validated['app_url'],
            ]);
        } catch (Throwable $e) {
            return redirect()->route('install.index', array_filter(['token' => $request->input('token')]))->with('install_error', 'Could not write .env — check file permissions: '.$e->getMessage());
        }

        Config::set([
            'database.connections.mysql.host' => $validated['db_host'],
            'database.connections.mysql.port' => $validated['db_port'],
            'database.connections.mysql.database' => $validated['db_database'],
            'database.connections.mysql.username' => $validated['db_username'],
            'database.connections.mysql.password' => $validated['db_password'] ?? '',
            'app.url' => $validated['app_url'],
        ]);

        try {
            DB::purge('mysql');
            DB::connection()->getPdo();

            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            return redirect()->route('install.index', array_filter(['token' => $request->input('token')]))->with('install_error', $e->getMessage());
        }

        Storage::disk('local')->put('installed', now()->toString());

        return redirect()->route('setup.index', array_filter(['token' => $request->input('token')]));
    }
}

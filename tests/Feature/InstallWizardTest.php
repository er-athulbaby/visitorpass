<?php

use App\Services\EnvFileWriter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    // Roll back fully (not just drop the table) so the migrations-tracking
    // table forgets it ran too — otherwise a later `artisan migrate` call
    // sees nothing pending and won't recreate anything.
    Artisan::call('migrate:rollback', ['--force' => true]);
    $this->envPath = sys_get_temp_dir().'/install_test_'.uniqid().'.env';
    file_put_contents($this->envPath, "APP_KEY=\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_DATABASE=old\nDB_USERNAME=old\nDB_PASSWORD=\nAPP_URL=http://localhost\n");
    $this->app->instance(EnvFileWriter::class, new EnvFileWriter($this->envPath));
});

afterEach(function () {
    if (file_exists($this->envPath)) {
        unlink($this->envPath);
    }
});

test('the install form renders when nothing is installed yet', function () {
    $this->get('/install')
        ->assertOk()
        ->assertSee('DB Host', false)
        ->assertSee('Database Name', false);
});

test('submitting valid database details writes the env file, migrates, and redirects to setup', function () {
    $response = $this->post('/install', [
        'db_host' => '127.0.0.1',
        'db_port' => '3306',
        'db_database' => 'testing',
        'db_username' => 'tester',
        'db_password' => 'secret',
        'app_url' => 'https://example.com',
    ]);

    $response->assertRedirect('/setup');
    expect(Storage::disk('local')->exists('installed'))->toBeTrue();
    expect(Schema::hasTable('settings'))->toBeTrue();
    expect(file_get_contents($this->envPath))->toContain('DB_DATABASE=testing');
    expect(file_get_contents($this->envPath))->toContain('APP_URL=https://example.com');
});

test('validation rejects a missing database name', function () {
    $response = $this->post('/install', [
        'db_host' => '127.0.0.1',
        'db_port' => '3306',
        'db_database' => '',
        'db_username' => 'tester',
        'db_password' => '',
        'app_url' => 'https://example.com',
    ]);

    $response->assertSessionHasErrors('db_database');
    expect(Storage::disk('local')->exists('installed'))->toBeFalse();
});

test('a connection failure redisplays the form with an error and does not write the marker', function () {
    DB::shouldReceive('purge')->once()->with('mysql');
    DB::shouldReceive('connection')->andThrow(new \RuntimeException('SQLSTATE[HY000] Connection refused'));

    $response = $this->post('/install', [
        'db_host' => '127.0.0.1',
        'db_port' => '3306',
        'db_database' => 'testing',
        'db_username' => 'tester',
        'db_password' => 'secret',
        'app_url' => 'https://example.com',
    ]);

    $response->assertRedirect('/install');
    $response->assertSessionHas('install_error');
    expect(Storage::disk('local')->exists('installed'))->toBeFalse();
});

test('a migration failure redisplays the form with an error and does not write the marker', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('migrate', ['--force' => true])
        ->andThrow(new \RuntimeException('SQLSTATE[42S01]: Base table or view already exists'));

    $response = $this->post('/install', [
        'db_host' => '127.0.0.1',
        'db_port' => '3306',
        'db_database' => 'testing',
        'db_username' => 'tester',
        'db_password' => 'secret',
        'app_url' => 'https://example.com',
    ]);

    $response->assertRedirect('/install');
    $response->assertSessionHas('install_error');
    expect(Storage::disk('local')->exists('installed'))->toBeFalse();
});

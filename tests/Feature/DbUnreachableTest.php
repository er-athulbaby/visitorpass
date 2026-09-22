<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

test('a database connection failure shows a plain outage page instead of crashing', function () {
    // Simulate an already-installed site: the marker must exist, otherwise
    // EnsureInstalled intercepts the request first (it also probes the DB
    // when the marker is absent) and redirects to /install before
    // EnsureSetupComplete ever runs.
    Storage::fake('local');
    Storage::disk('local')->put('installed', now()->toString());

    // Mocking DB::shouldReceive('connection') does not work here: Eloquent
    // resolves connections through its own ConnectionResolver, not the DB
    // facade, so the mock is never consulted. Repointing the connection
    // itself at an unreachable path also isn't viable: it breaks the same
    // SQLite connection RefreshDatabase needs for its own teardown rollback.
    // Dropping the table forces the identical QueryException path inside
    // EnsureSetupComplete's try/catch without disturbing the connection.
    Schema::dropIfExists('settings');

    // Route through /dashboard as a stand-in for "any normal route" — it
    // still resolves through EnsureSetupComplete before hitting auth.
    $response = $this->get('/dashboard');

    $response->assertStatus(503);
    $response->assertSee('unable to reach the database', false);
});

<?php

use App\Models\AuditLog;
use App\Models\User;

test('an audit log entry stores a json details payload', function () {
    $user = User::factory()->create();

    $log = AuditLog::create([
        'user_id' => $user->id,
        'action' => 'department.deleted',
        'details' => ['department_name' => 'Finance'],
    ]);

    expect($log->fresh()->details)->toBe(['department_name' => 'Finance']);
    expect($log->user->id)->toBe($user->id);
});

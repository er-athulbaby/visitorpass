<?php

use App\Models\Setting;

test('every page sends anti-clickjacking, no-sniff, referrer and no-index headers', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);

    $this->get('/login')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

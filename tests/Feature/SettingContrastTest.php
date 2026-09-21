<?php

use App\Models\Setting;

test('a dark primary color gets white on-primary text', function () {
    $setting = new Setting(['primary_color' => '#0F172A']);

    expect($setting->onPrimaryColor())->toBe('#FFFFFF');
});

test('a light primary color gets black on-primary text', function () {
    $setting = new Setting(['primary_color' => '#FFFFFF']);

    expect($setting->onPrimaryColor())->toBe('#000000');
});

test('a mid-tone red gets a computed contrast color', function () {
    $setting = new Setting(['primary_color' => '#EF4135']);

    expect($setting->onPrimaryColor())->toBe('#000000');
});

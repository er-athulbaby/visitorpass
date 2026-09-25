<?php

use App\Models\Setting;
use App\Models\User;
use App\Models\Visit;
use App\Models\Visitor;

test('every translatable UI string has an Arabic translation', function () {
    $arabic = json_decode(file_get_contents(lang_path('ar.json')), true, flags: JSON_THROW_ON_ERROR);
    $missing = [];

    $files = collect(['app', 'resources/views'])
        ->flatMap(fn ($dir) => iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir)))))
        ->filter(fn ($file) => str_ends_with($file, '.php'));

    foreach ($files as $file) {
        $source = file_get_contents($file);
        preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/", $source, $single);
        preg_match_all('/__\(\s*"((?:[^"\\\\]|\\\\.)*)"/', $source, $double);

        foreach ([...array_map('stripslashes', $single[1]), ...array_map('stripcslashes', $double[1])] as $key) {
            // Dotted keys like auth.password live in lang/ar/*.php, not the JSON file.
            if (! preg_match('/^[a-z]+\.[a-z_]+$/', $key) && ! array_key_exists($key, $arabic)) {
                $missing[$key] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
            }
        }
    }

    expect($missing)->toBe([]);
});

test('an Arabic-locale user gets Arabic navigation, RTL, and Arabic dates', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $admin = User::factory()->create(['locale' => 'ar']);
    $admin->assignRole('admin');
    $visitor = Visitor::create(['cpr_number' => '900000001', 'name' => 'Sam', 'mobile_number' => '33000000']);
    Visit::create(['visitor_id' => $visitor->id, 'check_in_at' => '2026-01-15 09:30:00']);

    $response = $this->actingAs($admin)->get('/history');

    $response->assertSee('dir="rtl"', false);
    $response->assertSee('الرئيسية');
    $response->assertSee('سجل الزوار');
    $response->assertSee('مسؤول النظام');
    $response->assertSee('يناير');
    $response->assertDontSee('Visitor History');
});

test('Arabic validation messages use Arabic field names', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $admin = User::factory()->create(['locale' => 'ar']);
    $admin->assignRole('admin');

    $this->actingAs($admin)->post('/admin/departments', ['name' => ''])
        ->assertSessionHasErrors(['name' => 'حقل الاسم مطلوب.']);
});

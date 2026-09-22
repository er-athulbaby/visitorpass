<?php

use Illuminate\Support\Facades\Blade;

test('the icon component renders a material symbols span with the given name', function () {
    $html = Blade::render('<x-icon name="home" class="text-xl" />');

    expect($html)->toContain('material-symbols-outlined');
    expect($html)->toContain('home');
    expect($html)->toContain('text-xl');
});

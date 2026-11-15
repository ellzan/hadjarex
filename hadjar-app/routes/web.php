<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/brand/hsc-logo.png', function () {
    $logoPath = resource_path('images/hsc-logo.png');

    if (file_exists($logoPath)) {
        return response()->file($logoPath, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 40" width="120" height="40">
    <rect width="120" height="40" rx="6" fill="#14532D" stroke="#D4AF37" stroke-width="2"/>
    <text x="60" y="26" font-family="Arial, sans-serif" font-size="18" font-weight="bold" fill="#D4AF37" text-anchor="middle">HSC</text>
</svg>
SVG;

    return response($svg, 200, [
        'Content-Type' => 'image/svg+xml',
        'Cache-Control' => 'no-cache',
    ]);
})->name('brand.logo');

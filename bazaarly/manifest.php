<?php
define('ALLOW_IN_MAINTENANCE', true);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$name = setting('site_name');
echo json_encode([
    'name' => $name,
    'short_name' => setting('pwa_short_name') ?: mb_substr($name, 0, 12),
    'description' => setting('site_description'),
    'start_url' => url() . '?source=pwa',
    'scope' => url(),
    'display' => 'standalone',
    'background_color' => '#ffffff',
    'theme_color' => valid_color(setting('primary_color'), '#0d9488'),
    'icons' => [
        ['src' => url('icon.php?s=192'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => url('icon.php?s=512'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => url('icon.php?s=512&maskable=1'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    'shortcuts' => [
        ['name' => 'Post an ad', 'url' => url('dashboard/edit.php')],
        ['name' => 'Messages', 'url' => url('dashboard/messages.php')],
        ['name' => 'Browse ads', 'url' => search_url([])],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

<?php
/**
 * App icon for the installable web app. Uses the uploaded favicon when there is one,
 * otherwise the bundled default icon.
 */
define('ALLOW_IN_MAINTENANCE', true);
require __DIR__ . '/includes/bootstrap.php';

$size = (int) input('s') === 512 ? 512 : 192;
$maskable = input('maskable') === '1';
$default = __DIR__ . '/assets/img/icon-' . $size . ($maskable ? '-maskable' : '') . '.png';
$favicon = setting('favicon') ? APP_ROOT . '/' . setting('favicon') : '';

header('Cache-Control: public, max-age=86400');
if (!$favicon || !is_file($favicon) || !function_exists('imagecreatetruecolor')) {
    header('Content-Type: image/png');
    readfile($default);
    exit;
}
$cache = APP_ROOT . '/uploads/site/icon-' . $size . ($maskable ? 'm' : '') . '-' . substr(md5(setting('favicon') . filemtime($favicon)), 0, 10) . '.png';
if (!is_file($cache)) {
    $src = @imagecreatefromstring((string) file_get_contents($favicon));
    if (!$src) {
        header('Content-Type: image/png');
        readfile($default);
        exit;
    }
    $dst = imagecreatetruecolor($size, $size);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    if ($maskable) {
        // maskable icons need a solid background and a safe zone
        [$r, $g, $b] = sscanf(valid_color(setting('primary_color'), '#0d9488'), '#%02x%02x%02x');
        imagefill($dst, 0, 0, imagecolorallocate($dst, $r, $g, $b));
        imagealphablending($dst, true);
        $pad = (int) round($size * 0.18);
    } else {
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        $pad = 0;
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $side = min($w, $h);
    imagecopyresampled($dst, $src, $pad, $pad, (int) (($w - $side) / 2), (int) (($h - $side) / 2), $size - 2 * $pad, $size - 2 * $pad, $side, $side);
    foreach (glob(APP_ROOT . '/uploads/site/icon-' . $size . ($maskable ? 'm' : '') . '-*.png') ?: [] as $old) {
        @unlink($old);
    }
    imagepng($dst, $cache);
}
header('Content-Type: image/png');
readfile($cache);

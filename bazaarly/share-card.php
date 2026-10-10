<?php
/**
 * 1200×630 preview image for an ad, used by WhatsApp, Facebook, X, Telegram… (og:image).
 * Generated once and cached in uploads/cards/.
 */
require __DIR__ . '/includes/bootstrap.php';

$id = (int) input('id');
$l = q_one(listing_select() . " WHERE l.id = ? AND l.status IN ('active', 'sold')", [$id]);
if (!$l) {
    http_response_code(404);
    exit;
}
$photo = q_one('SELECT path FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id LIMIT 1', [$id]);
$photoFile = $photo ? APP_ROOT . '/' . $photo['path'] : '';
$fontRegular = APP_ROOT . '/assets/fonts/DejaVuSans.ttf';
$fontBold = APP_ROOT . '/assets/fonts/DejaVuSans-Bold.ttf';

$canDraw = function_exists('imagettftext') && function_exists('imagecreatetruecolor') && is_file($fontBold);
if (!$canDraw) {
    // no FreeType on this server: fall back to the ad photo or the site icon
    header('Location: ' . ($photo ? url($photo['path']) : url('icon.php?s=512')));
    exit;
}

$hash = substr(md5(implode('|', [$l['title'], listing_price($l), $l['location'], $l['status'], $photo['path'] ?? '', setting('site_name'), setting('primary_color'), APP_VERSION])), 0, 12);
$dir = APP_ROOT . '/uploads/cards';
$file = "$dir/$id-$hash.jpg";
if (!is_file($file)) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    foreach (glob("$dir/$id-*.jpg") ?: [] as $old) {
        @unlink($old);
    }
    $W = 1200;
    $H = 630;
    $img = imagecreatetruecolor($W, $H);
    $hex = fn(string $c) => sscanf(valid_color($c, '#0d9488'), '#%02x%02x%02x');
    [$br, $bg, $bb] = $hex(setting('primary_color'));
    [$ar, $ag, $ab] = $hex(setting('accent_color'));
    $white = imagecolorallocate($img, 255, 255, 255);
    $ink = imagecolorallocate($img, 15, 23, 42);
    $muted = imagecolorallocate($img, 100, 116, 139);
    $brand = imagecolorallocate($img, $br, $bg, $bb);
    $accent = imagecolorallocate($img, $ar, $ag, $ab);
    imagefilledrectangle($img, 0, 0, $W, $H, $white);

    // left: the photo (cover crop) or a branded gradient
    $pw = 600;
    $src = $photoFile && is_file($photoFile) ? @imagecreatefromstring((string) file_get_contents($photoFile)) : false;
    if ($src) {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = max($pw / $sw, $H / $sh);
        $cw = (int) ($pw / $scale);
        $ch = (int) ($H / $scale);
        imagecopyresampled($img, $src, 0, 0, (int) (($sw - $cw) / 2), (int) (($sh - $ch) / 2), $pw, $H, $cw, $ch);
        imagedestroy($src);
    } else {
        for ($x = 0; $x < $pw; $x++) {
            $t = $x / $pw;
            $c = imagecolorallocate($img, (int) ($br + ($ar - $br) * $t * .6), (int) ($bg + ($ag - $bg) * $t * .6), (int) ($bb + ($ab - $bb) * $t * .6));
            imageline($img, $x, 0, $x, $H, $c);
        }
        $letter = mb_strtoupper(mb_substr($l['category_name'] ?: $l['title'], 0, 1));
        $box = imagettfbbox(220, 0, $fontBold, $letter);
        imagettftext($img, 220, 0, (int) (($pw - ($box[2] - $box[0])) / 2), 400, imagecolorallocatealpha($img, 255, 255, 255, 60), $fontBold, $letter);
    }

    // right: text
    $x = $pw + 56;
    $maxW = $W - $x - 56;
    imagefilledrectangle($img, $pw, $H - 14, $W, $H, $brand);
    $site = mb_strtoupper(setting('site_name'));
    imagettftext($img, 22, 0, $x, 92, $brand, $fontBold, $site);

    $wrap = function (string $text, int $size, string $font, int $maxWidth, int $maxLines): array {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $w) {
            $try = $line === '' ? $w : "$line $w";
            $box = imagettfbbox($size, 0, $font, $try);
            if ($box[2] - $box[0] > $maxWidth && $line !== '') {
                $lines[] = $line;
                $line = $w;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = rtrim(mb_substr($lines[$maxLines - 1], 0, max(1, mb_strlen($lines[$maxLines - 1]) - 2))) . '…';
        }
        return $lines;
    };
    $y = 170;
    foreach ($wrap($l['title'], 40, $fontBold, $maxW, 3) as $line) {
        imagettftext($img, 40, 0, $x, $y, $ink, $fontBold, $line);
        $y += 58;
    }
    $y += 34;
    $price = listing_price($l);
    $priceSize = mb_strlen($price) > 14 ? 40 : 54;
    imagettftext($img, $priceSize, 0, $x, $y + 20, $brand, $fontBold, $price);
    if ($l['price_type'] === 'negotiable') {
        imagettftext($img, 20, 0, $x, $y + 62, $muted, $fontRegular, 'Negotiable');
    }
    if ($l['status'] === 'sold') {
        imagefilledrectangle($img, $x, $y + 44, $x + 120, $y + 84, $accent);
        imagettftext($img, 20, 0, $x + 22, $y + 74, $white, $fontBold, 'SOLD');
    }
    if ($l['location'] !== '') {
        imagettftext($img, 22, 0, $x, $H - 100, $muted, $fontRegular, excerpt($l['location'], 34));
    }
    imagettftext($img, 18, 0, $x, $H - 56, $muted, $fontRegular, excerpt(preg_replace('~^https?://~', '', full_url()), 40));
    imagejpeg($img, $file, 84);
    imagedestroy($img);
}
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=86400');
readfile($file);

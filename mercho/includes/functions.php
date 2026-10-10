<?php
/**
 * Shared helper functions.
 */

/* ---------- Output & URLs ---------- */

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

function full_url(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . url($path);
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/** Image paths may be stored as relative paths (uploads/..., assets/...) or absolute URLs. */
function img_url(?string $path, string $fallback = 'assets/img/demo/placeholder.svg'): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return url($fallback);
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return url($path);
}

function redirect(string $path): void
{
    // Paths starting with "/" already include the store folder (e.g. from url() or product_url()).
    if (!preg_match('#^(https?://|/)#i', $path)) {
        $path = url($path);
    }
    header('Location: ' . $path);
    exit;
}

/* ---------- Settings ---------- */

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        foreach (q_all('SELECT skey, svalue FROM settings') as $row) {
            $cache[$row['skey']] = $row['svalue'];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_all();
    return array_key_exists($key, $all) && $all[$key] !== null ? (string) $all[$key] : $default;
}

function set_setting(string $key, string $value): void
{
    $exists = q_val('SELECT COUNT(*) FROM settings WHERE skey = ?', [$key]);
    if ((int) $exists > 0) {
        q('UPDATE settings SET svalue = ? WHERE skey = ?', [$value, $key]);
    } else {
        q('INSERT INTO settings (skey, svalue) VALUES (?, ?)', [$key, $value]);
    }
}

function setting_on(string $key): bool
{
    return setting($key, '0') === '1';
}

/* ---------- Money ---------- */

function money($amount): string
{
    $symbol = setting('currency_symbol', '$');
    $formatted = number_format((float) $amount, (int) setting('currency_decimals', '2'));
    // Hide ".00" for whole amounts to match the store design ($120 instead of $120.00)
    if (setting('hide_zero_decimals', '1') === '1') {
        $formatted = preg_replace('/\.0+$/', '', $formatted);
    }
    return setting('currency_position', 'before') === 'after' ? $formatted . ' ' . $symbol : $symbol . $formatted;
}

function discount_pct(array $p): int
{
    $old = (float) ($p['old_price'] ?? 0);
    $price = (float) $p['price'];
    if ($old <= 0 || $old <= $price) {
        return 0;
    }
    return (int) round(($old - $price) / $old * 100);
}

/* ---------- Security ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    // A form with a file bigger than the server's post_max_size arrives completely empty.
    if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        http_response_code(413);
        exit('The file you sent is too large for this server (maximum ' . ini_get('post_max_size') . 'B). Please go back and choose a smaller image or file.');
    }
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/* ---------- Flash messages ---------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function render_flashes(): string
{
    $html = '';
    foreach (flashes() as $f) {
        $html .= '<div class="alert alert-' . e($f['type']) . '">' . e($f['message']) . '</div>';
    }
    return $html;
}

/* ---------- Strings ---------- */

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string) $text, '-');
    return $text !== '' ? $text : 'item-' . substr(bin2hex(random_bytes(3)), 0, 6);
}

function unique_slug(string $table, string $base, int $ignoreId = 0): string
{
    $slug = slugify($base);
    $candidate = $slug;
    $i = 2;
    while ((int) q_val("SELECT COUNT(*) FROM $table WHERE slug = ? AND id <> ?", [$candidate, $ignoreId]) > 0) {
        $candidate = $slug . '-' . $i++;
    }
    return $candidate;
}

function str_list(?string $csv): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string) $csv)), 'strlen'));
}

function excerpt(string $text, int $len = 140): string
{
    $text = trim(strip_tags($text));
    if (mb_strlen($text) <= $len) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $len)) . '…';
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/* ---------- Uploads ---------- */

/**
 * Validate and store an uploaded image. Returns the relative path (uploads/...) or null when no file was sent.
 * Throws RuntimeException on an invalid file.
 */
function upload_image(?array $file): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error code ' . (int) $file['error'] . '). The file may be too large.');
    }
    if ($file['size'] > 8 * 1024 * 1024) {
        throw new RuntimeException('Image is too large (max 8 MB).');
    }
    $info = @getimagesize($file['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($allowed[$info[2]])) {
        throw new RuntimeException('Only JPG, PNG, GIF or WEBP images are allowed.');
    }
    $dir = 'uploads/' . date('Y/m');
    if (!is_dir(APP_ROOT . '/' . $dir) && !mkdir(APP_ROOT . '/' . $dir, 0755, true)) {
        throw new RuntimeException('Cannot create the uploads folder. Check folder permissions.');
    }
    $name = $dir . '/' . bin2hex(random_bytes(8)) . '.' . $allowed[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], APP_ROOT . '/' . $name)) {
        throw new RuntimeException('Could not save the uploaded image.');
    }
    optimize_image(APP_ROOT . '/' . $name, $info[2]);
    return $name;
}

/**
 * Speed: shrink big photos (Settings > General > Images & speed) and fix phone photo rotation.
 * Never throws - if GD is missing or the image is unusual, the original file is kept.
 */
function optimize_image(string $path, int $type): void
{
    $maxW = (int) setting('image_max_width', '1600');
    $quality = max(40, min(95, (int) setting('image_quality', '82')));
    if ($maxW <= 0 || !function_exists('imagecreatetruecolor') || $type === IMAGETYPE_GIF) {
        return;
    }
    try {
        $img = null;
        if ($type === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) {
            $img = @imagecreatefromjpeg($path);
        } elseif ($type === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) {
            $img = @imagecreatefrompng($path);
        } elseif ($type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
            $img = @imagecreatefromwebp($path);
        }
        if (!$img) {
            return;
        }
        $rotated = false;
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $angle = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
            if ($angle !== 0 && ($r = imagerotate($img, $angle, 0))) {
                imagedestroy($img);
                $img = $r;
                $rotated = true;
            }
        }
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w > $maxW) {
            $nh = (int) round($h * $maxW / $w);
            $out = imagecreatetruecolor($maxW, $nh);
            if ($type !== IMAGETYPE_JPEG) {
                imagealphablending($out, false);
                imagesavealpha($out, true);
            }
            imagecopyresampled($out, $img, 0, 0, 0, 0, $maxW, $nh, $w, $h);
            imagedestroy($img);
            $img = $out;
        } elseif (!$rotated && filesize($path) < 300 * 1024) {
            imagedestroy($img);
            return; // already small: keep the original
        }
        $tmp = $path . '.tmp';
        $ok = false;
        if ($type === IMAGETYPE_JPEG) {
            imageinterlace($img, true);
            $ok = imagejpeg($img, $tmp, $quality);
        } elseif ($type === IMAGETYPE_PNG) {
            $ok = imagepng($img, $tmp, 8);
        } elseif ($type === IMAGETYPE_WEBP) {
            $ok = imagewebp($img, $tmp, $quality);
        }
        imagedestroy($img);
        // Keep whichever file is smaller (unless the photo had to be rotated).
        if ($ok && is_file($tmp) && ($rotated || filesize($tmp) < filesize($path))) {
            rename($tmp, $path);
        } elseif (is_file($tmp)) {
            unlink($tmp);
        }
    } catch (Throwable $ex) {
        // keep the original image
    }
}

/** Normalise $_FILES['x'][] (multiple) into a list of single-file arrays. */
function files_list(?array $files): array
{
    if (!$files || !is_array($files['name'] ?? null)) {
        return $files ? [$files] : [];
    }
    $out = [];
    foreach ($files['name'] as $i => $name) {
        $out[] = [
            'name' => $name,
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i],
        ];
    }
    return $out;
}

/* ---------- Catalog ---------- */

/**
 * All categories (parents first, each followed by its subcategories).
 * product_count of a parent category includes the products of its subcategories.
 */
function categories(): array
{
    static $cats = null;
    if ($cats === null) {
        $rows = q_all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.active = 1) AS product_count
                       FROM categories c ORDER BY c.sort_order, c.name');
        $byParent = [];
        foreach ($rows as $r) {
            $byParent[(int) ($r['parent_id'] ?? 0)][] = $r;
        }
        $cats = [];
        foreach ($byParent[0] ?? [] as $parent) {
            $children = $byParent[(int) $parent['id']] ?? [];
            $parent['product_count'] = (int) $parent['product_count'] + array_sum(array_column($children, 'product_count'));
            $parent['children'] = $children;
            $parent['depth'] = 0;
            $cats[] = $parent;
            foreach ($children as $child) {
                $child['children'] = [];
                $child['depth'] = 1;
                $cats[] = $child;
            }
        }
        // Subcategories whose parent was deleted are shown as main categories.
        $ids = array_column($cats, 'id');
        foreach ($rows as $r) {
            if (!in_array($r['id'], $ids, true)) {
                $r['children'] = [];
                $r['depth'] = 0;
                $cats[] = $r;
            }
        }
    }
    return $cats;
}

/** Main categories only, each with a 'children' list. */
function category_tree(): array
{
    return array_values(array_filter(categories(), fn($c) => $c['depth'] === 0));
}

/** A category id plus the ids of its subcategories. */
function category_with_children(int $id): array
{
    $ids = [$id];
    foreach (categories() as $c) {
        if ((int) ($c['parent_id'] ?? 0) === $id) {
            $ids[] = (int) $c['id'];
        }
    }
    return $ids;
}

function find_category(int $id): ?array
{
    foreach (categories() as $c) {
        if ((int) $c['id'] === $id) {
            return $c;
        }
    }
    return null;
}

function brands(): array
{
    return array_column(q_all("SELECT DISTINCT brand FROM products WHERE active = 1 AND brand <> '' ORDER BY brand"), 'brand');
}

function product_images(array $p): array
{
    $images = [];
    if (!empty($p['image'])) {
        $images[] = $p['image'];
    }
    $gallery = json_decode((string) ($p['gallery'] ?? ''), true);
    if (is_array($gallery)) {
        foreach ($gallery as $g) {
            if (is_string($g) && $g !== '') {
                $images[] = $g;
            }
        }
    }
    return $images;
}

/**
 * Fetch products with simple filters.
 * $o keys: flag (is_new|is_trending|is_flash), category_id, brand, search, sale, min, max, sort, limit, offset
 */
function find_products(array $o = [], bool $countOnly = false)
{
    $where = ['p.active = 1'];
    $params = [];
    if (!empty($o['flag']) && in_array($o['flag'], ['is_new', 'is_trending', 'is_flash'], true)) {
        $where[] = 'p.' . $o['flag'] . ' = 1';
    }
    if (!empty($o['category_id'])) {
        $ids = category_with_children((int) $o['category_id']);
        $where[] = 'p.category_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        array_push($params, ...$ids);
    }
    if (!empty($o['brand'])) {
        $where[] = 'p.brand = ?';
        $params[] = $o['brand'];
    }
    if (!empty($o['search'])) {
        $where[] = '(p.name LIKE ? OR p.brand LIKE ? OR p.description LIKE ?)';
        $like = '%' . $o['search'] . '%';
        array_push($params, $like, $like, $like);
    }
    if (!empty($o['sale'])) {
        $where[] = 'p.old_price > p.price';
    }
    if (isset($o['min']) && $o['min'] !== '') {
        $where[] = 'p.price >= ?';
        $params[] = (float) $o['min'];
    }
    if (isset($o['max']) && $o['max'] !== '') {
        $where[] = 'p.price <= ?';
        $params[] = (float) $o['max'];
    }
    if (!empty($o['exclude'])) {
        $where[] = 'p.id <> ?';
        $params[] = (int) $o['exclude'];
    }
    $sqlWhere = ' WHERE ' . implode(' AND ', $where);

    if ($countOnly) {
        return (int) q_val('SELECT COUNT(*) FROM products p' . $sqlWhere, $params);
    }

    $sorts = [
        'new' => 'p.id DESC',
        'price_asc' => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'rating' => 'p.rating DESC',
        'popular' => 'p.reviews_count DESC',
        'manual' => 'p.sort_order ASC, p.id DESC',
    ];
    $order = $sorts[$o['sort'] ?? 'manual'] ?? $sorts['manual'];
    $limit = max(1, (int) ($o['limit'] ?? 12));
    $offset = max(0, (int) ($o['offset'] ?? 0));

    return q_all('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id'
        . $sqlWhere . ' ORDER BY ' . $order . ' LIMIT ' . $limit . ' OFFSET ' . $offset, $params);
}

function find_product(int $id): ?array
{
    return q_one('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ? AND p.active = 1', [$id]);
}

/** Clean links like /product/black-hoodie (Admin > Settings > SEO; needs Apache mod_rewrite). */
function pretty_urls(): bool
{
    return setting('pretty_urls', '0') === '1';
}

function product_url(array $p): string
{
    $slug = (string) ($p['slug'] ?? '');
    if (pretty_urls() && $slug !== '') {
        return url('product/' . rawurlencode($slug));
    }
    return url('product.php?id=' . (int) $p['id'] . '&' . 'n=' . rawurlencode($slug));
}

/** Make an image/link address absolute (https://shop.com/...), for share previews and Google. */
function abs_url(string $u): string
{
    if (preg_match('#^https?://#i', $u)) {
        return $u;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $u;
}

function find_product_by_slug(string $slug): ?array
{
    return q_one('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.slug = ? AND p.active = 1', [$slug]);
}

function category_url(array $c): string
{
    if (pretty_urls() && ($c['slug'] ?? '') !== '') {
        return url('category/' . rawurlencode($c['slug']));
    }
    return url('shop.php?category=' . (int) $c['id']);
}

function page_url(string $slug, string $type = 'page'): string
{
    if (pretty_urls()) {
        return url(($type === 'post' ? 'blog/' : 'page/') . rawurlencode($slug));
    }
    return url('page.php?slug=' . rawurlencode($slug));
}

function stars(float $rating): string
{
    $html = '<span class="stars" aria-label="' . e(number_format($rating, 1)) . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $cls = 's-full';
        } elseif ($rating >= $i - 0.5) {
            $cls = 's-half';
        } else {
            $cls = 's-empty';
        }
        $html .= '<i class="star ' . $cls . '">★</i>';
    }
    return $html . '</span>';
}

/* ---------- Wishlist (session based) ---------- */

function wishlist_ids(): array
{
    return array_map('intval', $_SESSION['wishlist'] ?? []);
}

function in_wishlist(int $id): bool
{
    return in_array($id, wishlist_ids(), true);
}

/* ---------- Orders ---------- */

function order_status_labels(): array
{
    return [
        'unconfirmed' => 'Awaiting confirmation',
        'pending' => 'Pending',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];
}

function payment_method_label(string $m): string
{
    return [
        'cod' => 'Cash on Delivery',
        'paypal' => 'PayPal',
        'stripe' => 'Credit / Debit Card (Stripe)',
    ][$m] ?? ucfirst($m);
}

function enabled_payment_methods(): array
{
    $methods = [];
    if (setting_on('pay_cod_enabled')) {
        $methods['cod'] = setting('pay_cod_title', 'Cash on Delivery');
    }
    if (setting_on('pay_paypal_enabled') && setting('paypal_client_id') !== '' && setting('paypal_secret') !== '') {
        $methods['paypal'] = setting('pay_paypal_title', 'PayPal');
    }
    if (setting_on('pay_stripe_enabled') && setting('stripe_secret_key') !== '') {
        $methods['stripe'] = setting('pay_stripe_title', 'Credit / Debit Card');
    }
    return $methods;
}

function order_items(int $orderId): array
{
    return q_all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
}

/** Reduce stock (and count the coupon use) once an order is confirmed (COD placed, or online payment captured). */
function order_reduce_stock(int $orderId): void
{
    $order = q_one('SELECT stock_reduced FROM orders WHERE id = ?', [$orderId]);
    if (!$order || (int) $order['stock_reduced'] === 1) {
        return;
    }
    foreach (order_items($orderId) as $item) {
        if (!empty($item['variant_id'])) {
            // Stock per size/color
            q('UPDATE product_variants SET stock = CASE WHEN stock - ? < 0 THEN 0 ELSE stock - ? END WHERE id = ? AND stock >= 0',
                [(int) $item['qty'], (int) $item['qty'], (int) $item['variant_id']]);
        } elseif ($item['product_id']) {
            q('UPDATE products SET stock = CASE WHEN stock - ? < 0 THEN 0 ELSE stock - ? END WHERE id = ? AND stock >= 0',
                [(int) $item['qty'], (int) $item['qty'], (int) $item['product_id']]);
        }
    }
    q('UPDATE orders SET stock_reduced = 1 WHERE id = ?', [$orderId]);
    // Count the coupon use at the same moment (this function runs once per confirmed order).
    $code = (string) q_val('SELECT coupon_code FROM orders WHERE id = ?', [$orderId]);
    if ($code !== '') {
        q('UPDATE coupons SET used_count = used_count + 1 WHERE UPPER(code) = ?', [strtoupper($code)]);
    }
}

/** Plain-text order summary used for WhatsApp messages. */
function order_text(array $order): string
{
    $lines = [];
    $lines[] = '🛍️ *New Order ' . $order['order_number'] . '* - ' . setting('store_name', 'Store');
    $lines[] = '';
    $lines[] = '👤 *Customer:* ' . $order['customer_name'];
    $lines[] = '📞 *Phone:* ' . $order['phone'];
    if ($order['email'] !== '') {
        $lines[] = '✉️ *Email:* ' . $order['email'];
    }
    $lines[] = '📍 *Address:* ' . implode(', ', array_filter([$order['address'], $order['city'], $order['state'] ?? '', $order['zip'] ?? '', $order['country']], 'strlen'));
    $lines[] = '';
    $lines[] = '📦 *Items:*';
    foreach (order_items((int) $order['id']) as $it) {
        $variant = trim(implode(' / ', array_filter([$it['size'], $it['color'], $it['options'] ?? ''])));
        $lines[] = '• ' . $it['qty'] . ' x ' . $it['name'] . ($variant !== '' ? ' (' . $variant . ')' : '') . ' = ' . money($it['price'] * $it['qty']);
    }
    $lines[] = '';
    $lines[] = 'Subtotal: ' . money($order['subtotal']);
    if ((float) ($order['discount'] ?? 0) > 0) {
        $lines[] = 'Discount (' . $order['coupon_code'] . '): -' . money($order['discount']);
    }
    $lines[] = 'Shipping' . (($order['shipping_method'] ?? '') !== '' ? ' (' . $order['shipping_method'] . ')' : '') . ': ' . ((float) $order['shipping'] > 0 ? money($order['shipping']) : 'Free');
    $lines[] = '💰 *Total: ' . money($order['total']) . '*';
    $lines[] = '💳 Payment: ' . payment_method_label($order['payment_method'])
        . ($order['payment_status'] === 'paid' ? ' (PAID)' : '');
    if (trim((string) $order['notes']) !== '') {
        $lines[] = '📝 Notes: ' . $order['notes'];
    }
    return implode("\n", $lines);
}

function generate_order_number(): string
{
    $prefix = setting('order_prefix', 'VL');
    do {
        $num = $prefix . '-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    } while ((int) q_val('SELECT COUNT(*) FROM orders WHERE order_number = ?', [$num]) > 0);
    return $num;
}

/* ---------- Pagination ---------- */

function paginate_links(int $total, int $perPage, int $page, array $query): string
{
    $pages = (int) ceil($total / max(1, $perPage));
    if ($pages <= 1) {
        return '';
    }
    $html = '<nav class="pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        $query['page'] = $i;
        $html .= '<a class="' . ($i === $page ? 'active' : '') . '" href="?' . e(http_build_query($query)) . '">' . $i . '</a>';
    }
    return $html . '</nav>';
}

/* ---------- HTTP (used by payments & WhatsApp API) ---------- */

/**
 * Minimal cURL wrapper. Returns ['status' => int, 'body' => string, 'json' => array|null, 'error' => string].
 */
function http_request(string $method, string $url, array $opts = []): array
{
    if (!function_exists('curl_init')) {
        return ['status' => 0, 'body' => '', 'json' => null, 'error' => 'The PHP cURL extension is not enabled on this server.'];
    }
    $ch = curl_init($url);
    $headers = $opts['headers'] ?? [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    if (isset($opts['json'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opts['json']));
        $headers[] = 'Content-Type: application/json';
    } elseif (isset($opts['form'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($opts['form']) ? http_build_query($opts['form']) : $opts['form']);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    if (isset($opts['basic'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $opts['basic']);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $body = curl_exec($ch);
    $error = $body === false ? curl_error($ch) : '';
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $body = $body === false ? '' : (string) $body;
    $json = json_decode($body, true);
    return ['status' => $status, 'body' => $body, 'json' => is_array($json) ? $json : null, 'error' => $error];
}

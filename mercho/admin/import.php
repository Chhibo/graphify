<?php
require __DIR__ . '/includes/auth.php';
require_admin('products');
set_time_limit(600);

$columns = ['slug', 'name', 'price', 'old_price', 'category', 'brand', 'sizes', 'colors', 'stock', 'image', 'gallery',
    'short_description', 'description', 'active', 'new', 'trending', 'deal'];

/* ---------- Export / sample ---------- */
if (isset($_GET['export']) || isset($_GET['sample'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . (isset($_GET['sample']) ? 'products-sample' : 'products-' . date('Y-m-d')) . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $columns);
    if (isset($_GET['sample'])) {
        fputcsv($out, ['', 'Classic White T-Shirt', '19.99', '24.99', 'Casual > T-Shirts', 'Mercho', 'S|M|L|XL', 'White|Black', '50',
            'https://example.com/images/white-tee.jpg', 'https://example.com/images/white-tee-2.jpg|https://example.com/images/white-tee-3.jpg',
            'Soft cotton tee for every day.', '<p>100% cotton, regular fit.</p>', '1', '1', '0', '0']);
    } else {
        $cats = [];
        foreach (categories() as $c) {
            $cats[(int) $c['id']] = $c;
        }
        foreach (q_all('SELECT * FROM products ORDER BY id') as $p) {
            $cat = $cats[(int) $p['category_id']] ?? null;
            $catPath = $cat ? (!empty($cat['parent_id']) && isset($cats[(int) $cat['parent_id']]) ? $cats[(int) $cat['parent_id']]['name'] . ' > ' : '') . $cat['name'] : '';
            $abs = fn($u) => $u === '' ? '' : abs_url(img_url($u));
            fputcsv($out, [$p['slug'], $p['name'], $p['price'], (float) $p['old_price'] > 0 ? $p['old_price'] : '', $catPath, $p['brand'],
                str_replace(',', '|', $p['sizes']), str_replace(',', '|', $p['colors']), (int) $p['stock'] < 0 ? '' : $p['stock'],
                $abs($p['image']), implode('|', array_map($abs, (array) json_decode((string) $p['gallery'], true))),
                $p['short_description'], $p['description'], $p['active'], $p['is_new'], $p['is_trending'], $p['is_flash']]);
        }
    }
    exit;
}

/** Find or create a category from "Parent > Child" (or just "Name"). */
function import_category(string $path): ?int
{
    $parts = array_values(array_filter(array_map('trim', explode('>', $path)), 'strlen'));
    if (!$parts) {
        return null;
    }
    $parentId = null;
    foreach (array_slice($parts, 0, 2) as $name) {
        $row = q_one('SELECT id FROM categories WHERE LOWER(name) = ? AND ' . ($parentId ? 'parent_id = ?' : '(parent_id IS NULL OR parent_id = 0)'),
            $parentId ? [mb_strtolower($name), $parentId] : [mb_strtolower($name)]);
        $parentId = $row ? (int) $row['id'] : db_insert('categories', ['name' => mb_substr($name, 0, 120), 'slug' => unique_slug('categories', $name), 'image' => '', 'sort_order' => 50, 'parent_id' => $parentId]);
    }
    return $parentId;
}

/** Download a remote image into uploads/ (returns the local path, or the URL itself if it fails). */
function import_image(string $url, bool $download): string
{
    $url = trim($url);
    if ($url === '' || !preg_match('#^https?://#i', $url) || !$download) {
        return $url;
    }
    $res = http_request('GET', $url);
    $info = $res['status'] === 200 && strlen($res['body']) < 8 * 1024 * 1024 ? @getimagesizefromstring($res['body']) : false;
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$ext) {
        return $url;
    }
    $dir = 'uploads/' . date('Y/m');
    if (!is_dir(APP_ROOT . '/' . $dir)) {
        @mkdir(APP_ROOT . '/' . $dir, 0755, true);
    }
    $name = $dir . '/' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (@file_put_contents(APP_ROOT . '/' . $name, $res['body']) === false) {
        return $url;
    }
    optimize_image(APP_ROOT . '/' . $name, $info[2]);
    return $name;
}

$report = null;
if (is_post()) {
    verify_csrf();
    $file = $_FILES['csv'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Please choose a CSV file.');
        redirect('admin/import.php');
    }
    $content = (string) file_get_contents($file['tmp_name']);
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252'); // CSV saved by old Excel versions
    }
    $firstLine = strtok($content, "\n");
    $delim = substr_count((string) $firstLine, ';') > substr_count((string) $firstLine, ',') ? ';' : ','; // Excel in French/Arabic uses ";"
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $content);
    rewind($fh);
    $head = array_map(fn($h) => strtolower(trim((string) $h)), fgetcsv($fh, 0, $delim) ?: []);
    $report = ['created' => 0, 'updated' => 0, 'errors' => []];
    if (!in_array('name', $head, true) || !in_array('price', $head, true)) {
        $report['errors'][] = 'The first row must contain the column names, at least "name" and "price". Download the sample file to see the format.';
    } else {
        $download = !empty($_POST['download_images']);
        $rowNo = 1;
        while (($cells = fgetcsv($fh, 0, $delim)) !== false) {
            $rowNo++;
            if (count(array_filter($cells, fn($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $r = [];
            foreach ($head as $i => $h) {
                $r[$h] = trim((string) ($cells[$i] ?? ''));
            }
            $name = $r['name'] ?? '';
            $price = (float) str_replace(',', '.', $r['price'] ?? '');
            if ($name === '' || $price <= 0) {
                $report['errors'][] = "Row $rowNo: name and a price above 0 are required.";
                continue;
            }
            $existing = ($r['slug'] ?? '') !== '' ? q_one('SELECT * FROM products WHERE slug = ?', [$r['slug']]) : q_one('SELECT * FROM products WHERE name = ?', [$name]);
            $list = fn($v) => implode(',', array_filter(array_map('trim', preg_split('/[|,]/', (string) $v)), 'strlen'));
            $flag = fn($k, $def) => isset($r[$k]) && $r[$k] !== '' ? (in_array(strtolower($r[$k]), ['1', 'yes', 'true', 'oui', 'y'], true) ? 1 : 0) : $def;
            // When updating, an empty cell keeps the current value.
            $cell = fn($k) => ($r[$k] ?? '') !== '';
            $data = [
                'name' => mb_substr($name, 0, 200),
                'price' => round($price, 2),
                'old_price' => $cell('old_price') ? round((float) str_replace(',', '.', $r['old_price']), 2) : (float) ($existing['old_price'] ?? 0),
                'brand' => mb_substr($cell('brand') ? $r['brand'] : ($existing['brand'] ?? ''), 0, 120),
                'sizes' => $cell('sizes') ? $list($r['sizes']) : ($existing['sizes'] ?? ''),
                'colors' => $cell('colors') ? $list($r['colors']) : ($existing['colors'] ?? ''),
                'stock' => $cell('stock') ? max(0, (int) $r['stock']) : (int) ($existing['stock'] ?? -1),
                'active' => $flag('active', (int) ($existing['active'] ?? 1)),
                'is_new' => $flag('new', (int) ($existing['is_new'] ?? 0)),
                'is_trending' => $flag('trending', (int) ($existing['is_trending'] ?? 0)),
                'is_flash' => $flag('deal', (int) ($existing['is_flash'] ?? 0)),
            ];
            foreach (['short_description', 'description'] as $k) {
                if (($r[$k] ?? '') !== '' || !$existing) {
                    $data[$k] = $r[$k] ?? '';
                }
            }
            if (($r['category'] ?? '') !== '') {
                $data['category_id'] = import_category($r['category']);
            }
            if (($r['image'] ?? '') !== '') {
                $data['image'] = mb_substr(import_image($r['image'], $download), 0, 500);
            }
            if (($r['gallery'] ?? '') !== '') {
                $data['gallery'] = json_encode(array_slice(array_map(fn($u) => import_image($u, $download), array_filter(array_map('trim', explode('|', $r['gallery'])), 'strlen')), 0, 8));
            }
            try {
                if ($existing) {
                    db_update('products', (int) $existing['id'], $data);
                    $report['updated']++;
                } else {
                    $data += ['slug' => unique_slug('products', ($r['slug'] ?? '') !== '' ? $r['slug'] : $name), 'gallery' => '[]', 'image' => '',
                        'rating' => 5, 'reviews_count' => 0, 'sort_order' => 0, 'created_at' => now()];
                    db_insert('products', $data);
                    $report['created']++;
                }
            } catch (Throwable $ex) {
                $report['errors'][] = "Row $rowNo: " . $ex->getMessage();
            }
        }
    }
}

$adminTitle = 'Import / Export products';
include __DIR__ . '/includes/header.php';
?>
<?php if ($report): ?>
  <div class="alert <?= $report['errors'] ? 'alert-warn' : 'alert-success' ?>">Import finished: <b><?= $report['created'] ?></b> new, <b><?= $report['updated'] ?></b> updated<?= $report['errors'] ? ', <b>' . count($report['errors']) . '</b> problem(s):' : '.' ?>
    <?php if ($report['errors']): ?><ul><?php foreach (array_slice($report['errors'], 0, 30) as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul><?php endif; ?></div>
<?php endif; ?>
<div class="grid-main">
  <form method="post" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?>
    <div class="card-head"><h2>Import products from a CSV file</h2></div>
    <ol class="steps-list">
      <li>Download the <a href="?sample=1">sample file</a> (or <a href="?export=1">export your products</a>) and open it in Excel or Google Sheets.</li>
      <li>Add one product per row. Save as <b>CSV</b> (Excel: File → Save as → “CSV UTF-8”).</li>
      <li>Upload it here. Products with the same <i>slug</i> (or same name) are updated, others are created.</li>
    </ol>
    <label>CSV file<input type="file" name="csv" accept=".csv,text/csv" required></label>
    <label class="inline"><input type="checkbox" name="download_images" value="1" checked> Download the images to my server (recommended - otherwise the image links are used as they are)</label>
    <button class="btn btn-primary" type="submit">Import</button>
  </form>
  <div class="card">
    <div class="card-head"><h2>Columns</h2></div>
    <div class="kv"><span>name, price</span><b>required</b></div>
    <div class="kv"><span>slug</span><b>page address, used to update</b></div>
    <div class="kv"><span>category</span><b>“Casual” or “Casual &gt; T-Shirts”</b></div>
    <div class="kv"><span>sizes, colors</span><b>S|M|L</b></div>
    <div class="kv"><span>stock</span><b>empty = unlimited</b></div>
    <div class="kv"><span>image, gallery</span><b>image links (gallery: a|b|c)</b></div>
    <div class="kv"><span>active, new, trending, deal</span><b>1 or 0</b></div>
    <div class="kv"><span>old_price, brand, short_description, description</span><b>optional</b></div>
    <a class="btn btn-light btn-block" href="?export=1">Export all products (.csv)</a>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

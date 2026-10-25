<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_admin();

$columns = [
    'title' => 'Ad title (required)',
    'description' => 'Description (required)',
    'category' => 'Category name, slug or ID (required)',
    'price' => 'Number, e.g. 1499.99',
    'price_type' => 'fixed, negotiable, free or contact (default: fixed)',
    'condition' => 'new, like_new, used, refurbished or empty',
    'location' => 'City / area (matched against your Locations list when it is on)',
    'phone' => 'Contact phone',
    'tags' => 'Comma separated tags',
    'user' => 'Owner email or username (default: chosen below)',
    'status' => 'active or pending (default: chosen below)',
    'featured' => '1 to feature the ad',
    'images' => 'Photo URLs separated by | (downloaded if enabled below)',
];

if (input('sample') === '1') {
    // prefer categories that have custom fields so the sample shows how to fill them
    $withFields = array_unique(array_map(fn($f) => (int) $f['category_id'], fields_all()));
    $pick = [];
    foreach (categories_all() as $cid => $c) {
        if (in_array($cid, $withFields, true) || in_array((int) $c['parent_id'], $withFields, true)) {
            $pick[$cid] = $c;
        }
    }
    $cats = array_slice($pick ?: categories_all(), 0, 2, true);
    $fieldCols = [];
    $sampleVal = fn(array $f) => $f['type'] === 'select' ? (field_options($f)[0] ?? '') : ($f['type'] === 'number' ? '100' : ($f['type'] === 'checkbox' ? 'yes' : 'Example'));
    foreach ($cats as $cid => $c) {
        foreach (fields_for_category($cid) as $f) {
            $fieldCols[$f['label']] = true;
        }
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bazaarly-import-sample.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_merge(array_keys($columns), array_keys($fieldCols)));
    $i = 0;
    foreach ($cats as $cid => $c) {
        fputcsv($out, array_merge([
            'Sample ad ' . (++$i) . ' in ' . $c['name'], 'Describe the item here. Multi-line text is fine inside quotes.', $c['name'],
            '250', 'negotiable', 'used', 'Downtown', '+1 555 0100', 'sample, import', '', 'active', '0', '',
        ], array_map(function ($label) use ($cid, $sampleVal) {
            foreach (fields_for_category($cid) as $f) {
                if ($f['label'] === $label) {
                    return $sampleVal($f);
                }
            }
            return '';
        }, array_keys($fieldCols))));
    }
    fclose($out);
    exit;
}

$report = null;
if (is_post()) {
    @set_time_limit(600);
    $file = normalize_files($_FILES['csv'] ?? null)[0] ?? null;
    $defaultUser = (int) input('default_user') ?: (int) $me['id'];
    $defaultStatus = input('default_status') === 'pending' ? 'pending' : 'active';
    $downloadImages = !empty($_POST['download_images']);
    $skipDupes = !empty($_POST['skip_duplicates']);
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Choose a CSV file to upload.');
        redirect('admin/import.php');
    }
    $fh = fopen($file['tmp_name'], 'r');
    $first = (string) fgets($fh);
    $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
    $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');
    $header = array_map(fn($h) => strtolower(trim((string) $h)), str_getcsv($first, $delim));
    if (!in_array('title', $header, true)) {
        flash('error', 'The first row must contain column names, including “title”. Download the sample file to see the format.');
        redirect('admin/import.php');
    }
    $catLookup = [];
    foreach (categories_all() as $cid => $c) {
        $catLookup[mb_strtolower($c['name'])] ??= $cid;
        $catLookup[mb_strtolower($c['slug'])] ??= $cid;
        $catLookup[(string) $cid] = $cid;
    }
    $locLookup = [];
    foreach (locations_all() as $lid => $l) {
        $locLookup[mb_strtolower($l['name'])] ??= $lid;
        $locLookup[mb_strtolower(location_label($lid))] = $lid;
    }
    $report = ['ok' => 0, 'skipped' => 0, 'errors' => [], 'images' => 0, 'image_errors' => 0];
    $row = 1;
    db()->beginTransaction();
    while (($cells = fgetcsv($fh, 0, $delim)) !== false) {
        $row++;
        if ($row > 2001) {
            $report['errors'][] = [$row, 'Stopped: a single import is limited to 2,000 rows.'];
            break;
        }
        if (count(array_filter($cells, fn($c) => trim((string) $c) !== '')) === 0) {
            continue;
        }
        $r = [];
        foreach ($header as $i => $h) {
            $r[$h] = trim((string) ($cells[$i] ?? ''));
        }
        $title = mb_substr($r['title'] ?? '', 0, 150);
        $catId = $catLookup[mb_strtolower($r['category'] ?? '')] ?? 0;
        if (mb_strlen($title) < 3) {
            $report['errors'][] = [$row, 'Missing title.'];
            continue;
        }
        if (!$catId) {
            $report['errors'][] = [$row, 'Unknown category “' . ($r['category'] ?? '') . '”.'];
            continue;
        }
        $owner = $defaultUser;
        if (($r['user'] ?? '') !== '') {
            $owner = (int) q_val('SELECT id FROM users WHERE email = ? OR username = ?', [strtolower($r['user']), $r['user']]);
            if (!$owner) {
                $report['errors'][] = [$row, 'Unknown user “' . $r['user'] . '”.'];
                continue;
            }
        }
        if ($skipDupes && q_val('SELECT id FROM listings WHERE user_id = ? AND title = ?', [$owner, $title])) {
            $report['skipped']++;
            continue;
        }
        $ptype = in_array($r['price_type'] ?? '', ['fixed', 'negotiable', 'free', 'contact'], true) ? $r['price_type'] : 'fixed';
        $price = (float) str_replace([',', ' '], '', $r['price'] ?? '0');
        $locText = mb_substr($r['location'] ?? '', 0, 150);
        $locId = $locLookup[mb_strtolower($locText)] ?? 0;
        $status = in_array($r['status'] ?? '', ['active', 'pending'], true) ? $r['status'] : $defaultStatus;
        // custom fields: columns named like the field label (or "field:Label")
        $fieldInput = [];
        foreach (fields_for_category($catId) as $fid => $f) {
            $label = mb_strtolower($f['label']);
            $val = $r[$label] ?? $r['field:' . $label] ?? '';
            if ($val !== '') {
                if ($f['type'] === 'select') {
                    foreach (field_options($f) as $o) {
                        if (mb_strtolower($o) === mb_strtolower($val)) {
                            $val = $o;
                        }
                    }
                }
                $fieldInput[$fid] = $f['type'] === 'checkbox' ? (in_array(mb_strtolower($val), ['1', 'yes', 'true', 'y'], true) ? '1' : '') : $val;
            }
        }
        [$fieldValues] = clean_field_input($catId, $fieldInput);
        $created = now();
        $id = db_insert('listings', [
            'user_id' => $owner, 'category_id' => $catId, 'title' => $title, 'description' => (string) ($r['description'] ?? ''),
            'price' => in_array($ptype, ['fixed', 'negotiable'], true) ? $price : 0, 'price_type' => $ptype,
            'item_condition' => in_array($r['condition'] ?? '', ['new', 'like_new', 'used', 'refurbished'], true) ? $r['condition'] : '',
            'location' => $locId ? location_label($locId) : $locText, 'location_id' => $locId, 'phone' => mb_substr($r['phone'] ?? '', 0, 40), 'show_phone' => ($r['phone'] ?? '') !== '' ? 1 : 0,
            'tags' => mb_substr(mb_strtolower($r['tags'] ?? ''), 0, 255), 'status' => $status, 'reject_reason' => '',
            'featured' => ($r['featured'] ?? '') === '1' ? 1 : 0, 'views' => 0, 'created_at' => $created, 'updated_at' => $created, 'expires_at' => new_expiry(),
        ]);
        save_listing_fields($id, $fieldValues);
        if ($downloadImages && ($r['images'] ?? '') !== '') {
            $order = 1;
            foreach (array_slice(array_filter(array_map('trim', explode('|', $r['images']))), 0, (int) setting('max_images', '8')) as $imgUrl) {
                $res = http_request($imgUrl, 'GET', null, [], 20);
                if ($res['status'] !== 200 || $res['body'] === '' || strlen($res['body']) > (int) setting('max_image_mb', '5') * 1048576) {
                    $report['image_errors']++;
                    continue;
                }
                $tmp = tempnam(sys_get_temp_dir(), 'bzimg');
                file_put_contents($tmp, $res['body']);
                $stored = store_image(['name' => basename(parse_url($imgUrl, PHP_URL_PATH) ?: 'photo'), 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($res['body'])], 'listings', 1600, 520, true, true);
                @unlink($tmp);
                if (is_string($stored)) {
                    $report['image_errors']++;
                    continue;
                }
                db_insert('listing_images', ['listing_id' => $id, 'path' => $stored['path'], 'thumb' => $stored['thumb'], 'phash' => $stored['phash'] ?? '', 'sort_order' => $order++]);
                $report['images']++;
            }
        }
        $report['ok']++;
    }
    db()->commit();
    fclose($fh);
    log_activity('import.csv', "{$report['ok']} ads imported, {$report['skipped']} skipped, " . count($report['errors']) . ' errors, ' . $report['images'] . ' photos');
}

$users = q_all("SELECT id, name, email FROM users WHERE status = 'active' ORDER BY role = 'admin' DESC, name LIMIT 500");
$adminActive = 'import';
$pageTitle = 'Import ads (CSV)';
require APP_ROOT . '/includes/admin-top.php';
?>
<?php if ($report): ?>
  <section class="card form-section">
    <h2><?= icon('check-circle') ?>Import finished</h2>
    <div class="stat-grid">
      <div class="stat"><span class="stat-icon"><?= icon('package') ?></span><div><strong><?= $report['ok'] ?></strong><span>Ads imported</span></div></div>
      <div class="stat"><span class="stat-icon info"><?= icon('image') ?></span><div><strong><?= $report['images'] ?></strong><span>Photos downloaded</span></div></div>
      <div class="stat"><span class="stat-icon warn"><?= icon('refresh') ?></span><div><strong><?= $report['skipped'] ?></strong><span>Duplicates skipped</span></div></div>
      <div class="stat"><span class="stat-icon pink"><?= icon('alert') ?></span><div><strong><?= count($report['errors']) + $report['image_errors'] ?></strong><span>Problems</span></div></div>
    </div>
    <?php if ($report['errors']): ?>
      <div class="table-wrap"><table class="table"><thead><tr><th>Row</th><th>Problem</th></tr></thead><tbody>
        <?php foreach (array_slice($report['errors'], 0, 100) as [$rn, $msg]): ?><tr><td><?= (int) $rn ?></td><td><?= e($msg) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
    <?php if ($report['image_errors']): ?><p class="muted small"><?= $report['image_errors'] ?> photo(s) could not be downloaded (broken link, too large or not an image).</p><?php endif; ?>
    <div class="btn-row"><a class="btn btn-primary" href="<?= e(url('admin/listings.php')) ?>">View ads</a></div>
  </section>
<?php endif; ?>

<div class="admin-grid wide-left">
  <form method="post" enctype="multipart/form-data" class="card form-section">
    <?= csrf_field() ?>
    <h2><?= icon('upload') ?>Upload a CSV file</h2>
    <label class="dropzone" data-dropzone>
      <input type="file" name="csv" accept=".csv,text/csv,text/plain" required data-max="-1">
      <span class="dz-icon"><?= icon('file') ?></span>
      <strong>Choose a .csv file</strong>
      <small>Excel / Google Sheets: File → Download → CSV. Up to 2,000 rows per import.</small>
    </label>
    <div class="form-grid">
      <div class="field"><label for="i-user">Owner for rows without a “user”</label>
        <select id="i-user" name="default_user"><?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>" <?= (int) $u['id'] === (int) $me['id'] ? 'selected' : '' ?>><?= e($u['name'] . ' (' . $u['email'] . ')') ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="i-status">Status for rows without a “status”</label>
        <select id="i-status" name="default_status"><option value="active">Active (live)</option><option value="pending">Pending (review first)</option></select></div>
    </div>
    <label class="switch"><input type="checkbox" name="download_images" value="1" checked><span class="switch-ui"></span><span>Download photos from the “images” column</span></label>
    <label class="switch"><input type="checkbox" name="skip_duplicates" value="1" checked><span class="switch-ui"></span><span>Skip rows when the same owner already has an ad with the same title</span></label>
    <button class="btn btn-primary btn-lg" type="submit" data-loading="Importing… please wait"><?= icon('upload') ?>Import ads</button>
  </form>

  <section class="card side-card">
    <h3><?= icon('file') ?>File format</h3>
    <p class="muted small">The first row must contain column names. Only <strong>title</strong>, <strong>description</strong> and <strong>category</strong> are required.</p>
    <ul class="kv-list compact">
      <?php foreach ($columns as $c => $help): ?><li><code><?= e($c) ?></code><span class="small"><?= e($help) ?></span></li><?php endforeach; ?>
      <li><code>Brand, Year…</code><span class="small">Custom category fields: use the field name as the column name</span></li>
    </ul>
    <a class="btn btn-ghost btn-block" href="?sample=1"><?= icon('download') ?>Download sample CSV</a>
  </section>
</div>
<?php require APP_ROOT . '/includes/admin-bottom.php';

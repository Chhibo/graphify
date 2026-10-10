<?php
require __DIR__ . '/includes/auth.php';
require_admin('owner');
set_time_limit(300);

$tables = ['settings', 'admins', 'categories', 'products', 'product_variants', 'customers', 'orders', 'order_items', 'coupons', 'reviews',
    'messages', 'shipping_methods', 'testimonials', 'pages', 'subscribers', 'blocklist', 'carts'];

$action = $_GET['download'] ?? '';
if ($action !== '' && hash_equals(csrf_token(), (string) ($_GET['t'] ?? ''))) {
    $stamp = date('Y-m-d-His');
    if ($action === 'sql') {
        // Full backup: table structure + all rows. Import it in phpMyAdmin (or any SQL tool) to restore.
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="store-backup-' . $stamp . '.sql"');
        $pdo = db();
        echo "-- Mercho backup " . date('Y-m-d H:i') . " (" . DB_DRIVER . ")\n-- Restore: import this file in phpMyAdmin (Import tab) into an empty database.\n\n";
        if (DB_DRIVER !== 'sqlite') {
            echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
        }
        foreach (schema_statements(DB_DRIVER) as $sql) {
            echo preg_replace('/\s+/', ' ', trim($sql)) . ";\n";
        }
        echo "\n";
        foreach ($tables as $t) {
            echo "DELETE FROM $t;\n";
            $stmt = $pdo->query("SELECT * FROM $t");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row));
                echo 'INSERT INTO ' . $t . ' (' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', $vals) . ");\n";
            }
            echo "\n";
        }
        if (DB_DRIVER !== 'sqlite') {
            echo "SET FOREIGN_KEY_CHECKS = 1;\n";
        }
        exit;
    }
    if ($action === 'sqlite' && DB_DRIVER === 'sqlite') {
        $file = APP_ROOT . '/data/' . DB_NAME;
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="store-' . $stamp . '.sqlite"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
    if ($action === 'uploads' && class_exists('ZipArchive')) {
        $zipPath = tempnam(sys_get_temp_dir(), 'bk');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT . '/uploads', FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && !in_array($f->getFilename(), ['.htaccess', 'index.html'], true)) {
                $zip->addFile($f->getPathname(), 'uploads/' . substr($f->getPathname(), strlen(APP_ROOT . '/uploads/')));
            }
        }
        $files = $zip->numFiles;
        $zip->close();
        if ($files === 0 || !is_file($zipPath) || filesize($zipPath) === 0) {
            @unlink($zipPath);
            flash('success', 'There are no uploaded images yet - nothing to download.');
            redirect('admin/backup.php');
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="store-images-' . $stamp . '.zip"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        unlink($zipPath);
        exit;
    }
}

$rowCounts = [];
foreach (['products', 'orders', 'customers'] as $t) {
    $rowCounts[$t] = (int) q_val("SELECT COUNT(*) FROM $t");
}
$uploadsSize = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT . '/uploads', FilesystemIterator::SKIP_DOTS)) as $f) {
    $uploadsSize += $f->getSize();
}
$tok = rawurlencode(csrf_token());
$adminTitle = 'Backup';
include __DIR__ . '/includes/header.php';
?>
<div class="grid-main">
  <div>
    <div class="card">
      <div class="card-head"><h2>1. Database (products, orders, customers, settings…)</h2></div>
      <p class="muted"><?= $rowCounts['products'] ?> products · <?= $rowCounts['orders'] ?> orders · <?= $rowCounts['customers'] ?> customers</p>
      <a class="btn btn-primary" href="?download=sql&t=<?= $tok ?>">Download database backup (.sql)</a>
      <?php if (DB_DRIVER === 'sqlite'): ?> <a class="btn btn-light" href="?download=sqlite&t=<?= $tok ?>">Download SQLite file</a><?php endif; ?>
    </div>
    <div class="card">
      <div class="card-head"><h2>2. Uploaded images</h2></div>
      <p class="muted"><?= number_format($uploadsSize / 1048576, 1) ?> MB in the uploads folder.</p>
      <?php if (class_exists('ZipArchive')): ?>
        <a class="btn btn-light" href="?download=uploads&t=<?= $tok ?>">Download images (.zip)</a>
      <?php else: ?>
        <p class="help">Your server cannot create zip files. Download the <code>uploads</code> folder with FTP / File Manager instead.</p>
      <?php endif; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h2>How to restore</h2></div>
    <ol class="steps-list">
      <li>Upload the store files again (from your store zip) if needed.</li>
      <li><b>MySQL:</b> in cPanel → phpMyAdmin, select your database → <b>Import</b> → choose the <code>.sql</code> file → Go.<br>
          <b>SQLite:</b> upload the <code>.sqlite</code> file into the <code>data</code> folder with the same name as in <code>config.php</code> (DB_NAME).</li>
      <li>Unzip the images into the <code>uploads</code> folder.</li>
    </ol>
    <p class="help">Tip: download a backup every week and before big changes. Many hosts also offer automatic backups in cPanel → Backup.</p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

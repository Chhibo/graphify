<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/schema.php';
require_admin();

$dir = APP_ROOT . '/data/backups';
if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
}
if (is_dir($dir) && !is_file("$dir/.htaccess")) {
    @file_put_contents("$dir/.htaccess", "Require all denied\n");
    @file_put_contents("$dir/index.html", '');
}
$zipOk = class_exists('ZipArchive');
$validName = fn(string $n) => (bool) preg_match('/^backup-\d{8}-\d{6}-[a-f0-9]{8}\.zip$/', $n);

/** Write an SQL dump of Bazaarly's MySQL tables. */
function mysql_dump(string $file): void
{
    $pdo = db();
    $fh = fopen($file, 'w');
    fwrite($fh, "-- Bazaarly database backup " . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
    foreach (array_keys(schema_tables()) as $table) {
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1] ?? null;
        if (!$create) {
            continue;
        }
        fwrite($fh, "DROP TABLE IF EXISTS `$table`;\n$create;\n");
        $st = $pdo->query("SELECT * FROM `$table`", PDO::FETCH_ASSOC);
        $batch = [];
        $cols = null;
        while ($row = $st->fetch()) {
            $cols ??= '`' . implode('`, `', array_keys($row)) . '`';
            $batch[] = '(' . implode(', ', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)) . ')';
            if (count($batch) >= 200) {
                fwrite($fh, "INSERT INTO `$table` ($cols) VALUES\n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        if ($batch) {
            fwrite($fh, "INSERT INTO `$table` ($cols) VALUES\n" . implode(",\n", $batch) . ";\n");
        }
        fwrite($fh, "\n");
    }
    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);
}

if (input('download') !== '') {
    $name = basename(input('download'));
    if (!$validName($name) || !is_file("$dir/$name")) {
        flash('error', 'Backup not found.');
        redirect('admin/backup.php');
    }
    log_activity('backup.download', $name);
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize("$dir/$name"));
    readfile("$dir/$name");
    exit;
}

if (is_post()) {
    if (input('action') === 'delete') {
        $name = basename(input('name'));
        if ($validName($name) && is_file("$dir/$name")) {
            unlink("$dir/$name");
            log_activity('backup.delete', $name);
            flash('success', 'Backup deleted.');
        }
        redirect('admin/backup.php');
    }
    if (input('action') === 'create' && $zipOk) {
        @set_time_limit(600);
        $withUploads = !empty($_POST['uploads']);
        $name = 'backup-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new ZipArchive();
        $tmp = [];
        try {
            if ($zip->open("$dir/$name", ZipArchive::CREATE) !== true) {
                throw new RuntimeException('Could not create the zip file. Is the data/ folder writable?');
            }
            $driver = $GLOBALS['config']['db']['driver'];
            if ($driver === 'sqlite') {
                $copy = "$dir/tmp-" . bin2hex(random_bytes(6)) . '.sqlite';
                try {
                    db()->exec('VACUUM INTO ' . db()->quote($copy));
                } catch (Throwable) {
                    $path = $GLOBALS['config']['db']['path'];
                    copy(preg_match('~^(/|[A-Za-z]:)~', $path) ? $path : APP_ROOT . '/' . $path, $copy);
                }
                $tmp[] = $copy;
                $zip->addFile($copy, 'database/' . basename($GLOBALS['config']['db']['path']));
            } else {
                $sql = "$dir/tmp-" . bin2hex(random_bytes(6)) . '.sql';
                mysql_dump($sql);
                $tmp[] = $sql;
                $zip->addFile($sql, 'database/database.sql');
            }
            $zip->addFile(APP_ROOT . '/config.php', 'config.php');
            $files = 0;
            if ($withUploads) {
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT . '/uploads', FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) {
                    $rel = 'uploads/' . str_replace('\\', '/', substr($f->getPathname(), strlen(APP_ROOT . '/uploads/')));
                    if ($f->isFile() && !str_starts_with($rel, 'uploads/cards/')) {
                        $zip->addFile($f->getPathname(), $rel);
                        $files++;
                    }
                }
            }
            $zip->addFromString('HOW-TO-RESTORE.txt', "Bazaarly backup created " . date('Y-m-d H:i') . " (" . ($driver === 'sqlite' ? 'SQLite' : 'MySQL') . ")\n\n"
                . "1. Upload a fresh copy of the Bazaarly files (without running the installer).\n"
                . "2. Put config.php from this zip in the main folder.\n"
                . ($driver === 'sqlite'
                    ? "3. Copy the file from database/ into the data/ folder (keep the same file name).\n"
                    : "3. Import database/database.sql into your MySQL database (phpMyAdmin → Import). Update config.php if the database name, user or password changed.\n")
                . "4. Copy the uploads/ folder from this zip over the site's uploads/ folder (if included).\n"
                . "5. Delete the install/ folder.\n");
            $zip->close();
            log_activity('backup.create', $name . ($withUploads ? " (database + $files files)" : ' (database only)'));
            flash('success', 'Backup created. Download it and keep it somewhere safe.');
        } catch (Throwable $ex) {
            @unlink("$dir/$name");
            flash('error', 'Backup failed: ' . $ex->getMessage());
        } finally {
            foreach ($tmp as $t) {
                @unlink($t);
            }
        }
        redirect('admin/backup.php');
    }
}

$backups = [];
foreach (glob("$dir/backup-*.zip") ?: [] as $f) {
    $backups[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)];
}
usort($backups, fn($a, $b) => $b['time'] <=> $a['time']);
$uploadsSize = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT . '/uploads', FilesystemIterator::SKIP_DOTS)) as $f) {
    $uploadsSize += $f->isFile() ? $f->getSize() : 0;
}
$fmt = fn(int $b) => $b > 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB';

$adminActive = 'backup';
$pageTitle = 'Backup';
require APP_ROOT . '/includes/admin-top.php';
?>
<div class="admin-grid">
  <form method="post" class="card form-section">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <h2><?= icon('database') ?>Create a backup</h2>
    <?php if (!$zipOk): ?>
      <div class="alert alert-error"><?= icon('alert') ?><span>The PHP <code>zip</code> extension is not enabled on this server. Enable it in your hosting panel (Select PHP version → Extensions) to use backups.</span></div>
    <?php else: ?>
      <p class="muted">Creates a single .zip with your database, your <code>config.php</code> and (optionally) all uploaded photos. Restore steps are included inside.</p>
      <label class="switch"><input type="checkbox" name="uploads" value="1" checked><span class="switch-ui"></span><span>Include photos and uploads (<?= $fmt($uploadsSize) ?>)</span></label>
      <button class="btn btn-primary btn-lg" type="submit" data-loading="Creating backup…"><?= icon('download') ?>Create backup now</button>
    <?php endif; ?>
  </form>
  <section class="card side-card">
    <h3><?= icon('shield') ?>Good to know</h3>
    <ul class="check-list">
      <li><?= icon('check') ?>Backups are stored privately in <code>data/backups/</code> and can't be opened from the web.</li>
      <li><?= icon('check') ?>Download each backup to your computer or cloud storage — a backup on the same server won't help if the server fails.</li>
      <li><?= icon('check') ?>The zip contains your database password (inside config.php). Keep it private.</li>
      <li><?= icon('check') ?>Delete old backups here to save disk space.</li>
    </ul>
  </section>
</div>

<div class="card table-card">
  <div class="card-head"><h2>Saved backups</h2><span class="muted small"><?= count($backups) ?></span></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>File</th><th>Created</th><th>Size</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($backups as $b): ?>
        <tr>
          <td><span class="cat-cell"><span class="cat-icon sm"><?= icon('database') ?></span><?= e($b['name']) ?></span></td>
          <td class="nowrap"><?= e(format_date(date('Y-m-d H:i:s', $b['time']))) ?> <span class="muted small"><?= date('H:i', $b['time']) ?></span></td>
          <td class="nowrap"><?= $fmt($b['size']) ?></td>
          <td class="row-actions nowrap">
            <a class="btn btn-ghost btn-sm" href="?download=<?= e($b['name']) ?>"><?= icon('download') ?>Download</a>
            <form method="post" style="display:inline" data-confirm="Delete this backup file?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="name" value="<?= e($b['name']) ?>"><button class="icon-btn sm danger" type="submit" aria-label="Delete"><?= icon('trash') ?></button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$backups): ?><tr><td colspan="4" class="center muted">No backups yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require APP_ROOT . '/includes/admin-bottom.php';

<?php
/**
 * One-click backups: database (.sql / .sql.gz), uploaded images (.zip) or both.
 * Files are streamed to the browser and never stored on the server.
 */
require __DIR__ . '/includes/auth.php';
require_permission('backup');

@set_time_limit(300);
$prefix = (string) ($config['db_prefix'] ?? '');
$siteSlug = slugify(setting('site_name', 'travel-cms'));

/** Tables of this installation (all tables with the configured prefix). */
function backup_tables(string $prefix): array
{
    $like = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $prefix) . '%';
    return db()->query('SHOW TABLES LIKE ' . db()->quote($like))->fetchAll(PDO::FETCH_COLUMN);
}

/** Write a complete SQL dump (structure + data) to a stream. */
function write_sql_dump($out, string $prefix): void
{
    $pdo = db();
    fwrite($out, "-- Travel CMS database backup\n-- Site: " . setting('site_name') . "\n-- Created: " . date('Y-m-d H:i:s') . "\n-- Version: " . APP_VERSION . "\n\n");
    fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
    foreach (backup_tables($prefix) as $table) {
        $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
        fwrite($out, "DROP TABLE IF EXISTS `$table`;\n$create;\n\n");
        $offset = 0;
        do {
            $rows = $pdo->query('SELECT * FROM `' . $table . '` LIMIT 500 OFFSET ' . $offset)->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
                $values = [];
                foreach ($rows as $row) {
                    $values[] = '(' . implode(', ', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)) . ')';
                }
                fwrite($out, "INSERT INTO `$table` ($cols) VALUES\n" . implode(",\n", $values) . ";\n");
            }
            $offset += 500;
        } while (count($rows) === 500);
        fwrite($out, "\n");
    }
    fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");
}

function uploads_stats(): array
{
    $count = 0;
    $size = 0;
    foreach (glob(APP_ROOT . '/uploads/*') ?: [] as $f) {
        if (is_file($f) && !in_array(basename($f), ['index.html', '.htaccess'], true)) {
            $count++;
            $size += filesize($f);
        }
    }
    return [$count, $size];
}

function human_size(float $bytes): string
{
    foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
        if ($bytes < 1024 || $unit === 'GB') {
            return round($bytes, $unit === 'B' ? 0 : 1) . ' ' . $unit;
        }
        $bytes /= 1024;
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('admin/backup.php');
    $type = post('type');
    $stamp = date('Y-m-d-His');
    save_setting('last_backup_at', now());
    save_setting('last_backup_by', $admin['name']);

    if ($type === 'database') {
        $gzip = post('gzip') === '1' && function_exists('gzencode');
        $name = $siteSlug . '-database-' . $stamp . '.sql' . ($gzip ? '.gz' : '');
        header('Content-Type: ' . ($gzip ? 'application/gzip' : 'application/sql'));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: no-store');
        if ($gzip) {
            $tmp = fopen('php://temp', 'w+');
            write_sql_dump($tmp, $prefix);
            rewind($tmp);
            echo gzencode(stream_get_contents($tmp), 6);
            fclose($tmp);
        } else {
            $out = fopen('php://output', 'w');
            write_sql_dump($out, $prefix);
            fclose($out);
        }
        exit;
    }

    if (in_array($type, ['uploads', 'full'], true)) {
        if (!class_exists('ZipArchive')) {
            flash('error', 'Your server does not have the PHP "zip" extension. Download the database here, and the /uploads folder with FTP or your hosting file manager.');
            redirect('admin/backup.php');
        }
        $zipPath = tempnam(sys_get_temp_dir(), 'tcms');
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            flash('error', 'Could not create the backup file (temporary folder not writable).');
            redirect('admin/backup.php');
        }
        if ($type === 'full') {
            $sql = fopen('php://temp', 'w+');
            write_sql_dump($sql, $prefix);
            rewind($sql);
            $zip->addFromString('database.sql', stream_get_contents($sql));
            fclose($sql);
            if (post('include_config') === '1' && is_file(APP_ROOT . '/config.php')) {
                $zip->addFile(APP_ROOT . '/config.php', 'config.php');
            }
            $zip->addFromString('README.txt', "Travel CMS backup of " . setting('site_name') . " (" . date('Y-m-d H:i') . ")\r\n\r\n"
                . "To restore:\r\n1. Import database.sql into your MySQL database (phpMyAdmin > Import).\r\n"
                . "2. Upload the files of the uploads/ folder to the uploads/ folder of the website.\r\n"
                . "3. If config.php is included, it contains your database password: keep this file private.\r\n");
        }
        foreach (glob(APP_ROOT . '/uploads/*') ?: [] as $f) {
            if (is_file($f) && !in_array(basename($f), ['index.html', '.htaccess'], true)) {
                $zip->addFile($f, 'uploads/' . basename($f));
            }
        }
        if ($zip->numFiles === 0) {
            $zip->addFromString('uploads/empty.txt', 'No uploaded files yet.');
        }
        $zip->close();
        $name = $siteSlug . '-' . ($type === 'full' ? 'full-backup' : 'uploads') . '-' . $stamp . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Cache-Control: no-store');
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }
    redirect('admin/backup.php');
}

$tables = backup_tables($prefix);
$rows = 0;
$dbSize = 0;
foreach (db()->query('SHOW TABLE STATUS')->fetchAll(PDO::FETCH_ASSOC) as $t) {
    if (in_array($t['Name'], $tables, true)) {
        $dbSize += (int) $t['Data_length'] + (int) $t['Index_length'];
        $rows += (int) db_value('SELECT COUNT(*) FROM `' . $t['Name'] . '`');
    }
}
[$fileCount, $fileSize] = uploads_stats();
$zipOk = class_exists('ZipArchive');

admin_header('Backup', 'backup');
?>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="card p-5"><p class="text-xs text-slate-500 font-semibold">Database</p><p class="text-2xl font-extrabold text-slate-900"><?= e(human_size($dbSize)) ?></p><p class="text-[11px] text-slate-400"><?= count($tables) ?> tables · <?= number_format($rows) ?> rows</p></div>
    <div class="card p-5"><p class="text-xs text-slate-500 font-semibold">Uploaded images</p><p class="text-2xl font-extrabold text-slate-900"><?= e(human_size($fileSize)) ?></p><p class="text-[11px] text-slate-400"><?= $fileCount ?> files in /uploads</p></div>
    <div class="card p-5"><p class="text-xs text-slate-500 font-semibold">Last backup</p><p class="text-2xl font-extrabold text-slate-900"><?= setting('last_backup_at') ? e(time_ago(setting('last_backup_at'))) : 'Never' ?></p><p class="text-[11px] text-slate-400"><?= setting('last_backup_at') ? 'by ' . e(setting('last_backup_by')) . ' · ' . e(format_date(setting('last_backup_at'), 'M j, Y H:i')) : 'Download one now' ?></p></div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <form method="post" class="card p-6 space-y-4">
        <?= csrf_field() ?><input type="hidden" name="type" value="full">
        <div class="w-12 h-12 rounded-xl bg-brand-100 text-brand-600 flex items-center justify-center text-xl"><i class="fa-solid fa-box-archive"></i></div>
        <h2 class="font-bold text-lg">Full backup <span class="text-xs font-semibold text-brand-600">recommended</span></h2>
        <p class="text-sm text-slate-600">One .zip file with your database and all uploaded images, plus restore instructions.</p>
        <?php f_check('Include config.php', 'include_config', false, 'Contains your database password. Only tick this if you store the backup safely.'); ?>
        <button class="btn btn-primary w-full justify-center" <?= $zipOk ? '' : 'disabled' ?>><i class="fa-solid fa-download"></i> Download full backup</button>
        <?php if (!$zipOk): ?><p class="help text-rose-600">Needs the PHP "zip" extension (ask your host).</p><?php endif; ?>
    </form>
    <form method="post" class="card p-6 space-y-4">
        <?= csrf_field() ?><input type="hidden" name="type" value="database">
        <div class="w-12 h-12 rounded-xl bg-sky-100 text-sky-600 flex items-center justify-center text-xl"><i class="fa-solid fa-database"></i></div>
        <h2 class="font-bold text-lg">Database only</h2>
        <p class="text-sm text-slate-600">Bookings, trips, settings, translations, subscribers, everything except images. Restore it with phpMyAdmin → Import.</p>
        <?php f_check('Compress (.sql.gz)', 'gzip', function_exists('gzencode'), 'Smaller file. phpMyAdmin imports both formats.'); ?>
        <button class="btn btn-light w-full justify-center"><i class="fa-solid fa-download"></i> Download database</button>
    </form>
    <form method="post" class="card p-6 space-y-4">
        <?= csrf_field() ?><input type="hidden" name="type" value="uploads">
        <div class="w-12 h-12 rounded-xl bg-accent-100 text-accent-600 flex items-center justify-center text-xl"><i class="fa-solid fa-images"></i></div>
        <h2 class="font-bold text-lg">Images only</h2>
        <p class="text-sm text-slate-600">All files uploaded through the admin panel (trip photos, galleries, logos, review photos).</p>
        <button class="btn btn-light w-full justify-center" <?= $zipOk ? '' : 'disabled' ?>><i class="fa-solid fa-download"></i> Download images (.zip)</button>
    </form>
</div>

<div class="card p-6 mt-6 text-sm text-slate-600 space-y-2">
    <h2 class="font-bold text-slate-900">Good backup habits</h2>
    <ul class="list-disc ml-5 space-y-1">
        <li>Download a full backup before updating the script and at least once a week.</li>
        <li>Keep copies somewhere else (your computer, Google Drive, Dropbox). Backups are never stored on the server.</li>
        <li>Many hosts also offer automatic backups in cPanel. Use both.</li>
    </ul>
</div>
<?php admin_footer(); ?>

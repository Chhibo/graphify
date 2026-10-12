<?php
/**
 * One-click updates (Admin > Updates, owner only).
 *
 * An update is the normal store zip (the "mercho" folder, or its contents). It can be uploaded in the admin,
 * or downloaded from an update address that returns JSON like:
 *   {"version": "1.7.0", "url": "https://example.com/mercho-1.7.0.zip", "sha256": "…", "notes": "What is new"}
 *
 * Your config.php, data/ (database, backups) and uploads/ (images) are never touched, and the install folder
 * is not re-created. The files that are replaced are first saved in data/backups/.
 */

/**
 * Seller of the script: put the address of your update.json here before you share the zip,
 * so every store can check for updates. Store owners can also set it in Admin > Updates.
 */
const MERCHO_UPDATE_URL = '';

function update_source_url(): string
{
    return setting('update_url') !== '' ? setting('update_url') : MERCHO_UPDATE_URL;
}

/** Latest version from the update address: ['version', 'url', 'sha256', 'notes'] or ['error' => ...]. */
function update_check(): array
{
    $src = update_source_url();
    if ($src === '') {
        return ['error' => 'No update address is set.'];
    }
    $res = http_request('GET', $src);
    $m = $res['json'];
    if ($res['status'] !== 200 || !is_array($m) || empty($m['version']) || empty($m['url'])) {
        return ['error' => 'Could not read the update information at ' . $src . ($res['error'] !== '' ? ' (' . $res['error'] . ')' : ' (HTTP ' . $res['status'] . ')')];
    }
    return ['version' => (string) $m['version'], 'url' => (string) $m['url'], 'sha256' => strtolower((string) ($m['sha256'] ?? '')), 'notes' => (string) ($m['notes'] ?? '')];
}

/** Download the update zip. Returns the local path, or throws RuntimeException. */
function update_download(array $info): string
{
    $res = http_request('GET', $info['url']);
    if ($res['status'] !== 200 || strlen($res['body']) < 1000) {
        throw new RuntimeException('The update file could not be downloaded' . ($res['error'] !== '' ? ': ' . $res['error'] : ' (HTTP ' . $res['status'] . ')') . '.');
    }
    if ($info['sha256'] !== '' && !hash_equals($info['sha256'], hash('sha256', $res['body']))) {
        throw new RuntimeException('The downloaded file is damaged or not the official one (checksum does not match). Nothing was changed.');
    }
    $dir = update_work_dir();
    $path = $dir . '/update-' . date('YmdHis') . '.zip';
    if (file_put_contents($path, $res['body']) === false) {
        throw new RuntimeException('Could not save the update file in data/updates/.');
    }
    return $path;
}

function update_work_dir(): string
{
    $dir = APP_ROOT . '/data/updates';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        throw new RuntimeException('The data/ folder is not writable.');
    }
    return $dir;
}

/** Files of the store that an update must never replace. */
function update_is_protected(string $path): bool
{
    return $path === 'config.php' || preg_match('#^(data|uploads|install)/#', $path) === 1;
}

/**
 * Install an update zip. Returns ['version' => new version, 'files' => count, 'backup' => backup path].
 * Throws RuntimeException with a clear message; nothing is written when a check fails.
 */
function update_install(string $zipPath, bool $allowSameVersion = false): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('The PHP Zip extension is not enabled on your server. Ask your hosting to enable "zip", or update by uploading the files with FTP.');
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('This file is not a valid zip.');
    }
    // The zip can contain the "mercho" folder or directly its files.
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[$i] = str_replace('\\', '/', (string) $zip->getNameIndex($i));
    }
    $prefix = '';
    if (!in_array('includes/bootstrap.php', $names, true)) {
        foreach ($names as $n) {
            if (preg_match('#^([^/]+/)includes/bootstrap\.php$#', $n, $m)) {
                $prefix = $m[1];
                break;
            }
        }
        if ($prefix === '') {
            $zip->close();
            throw new RuntimeException('This zip is not a Mercho update (includes/bootstrap.php is missing).');
        }
    }
    $bootstrap = (string) $zip->getFromName($prefix . 'includes/bootstrap.php');
    if (!preg_match("/define\\('APP_VERSION', '([0-9.]+)'\\)/", $bootstrap, $vm)) {
        $zip->close();
        throw new RuntimeException('The version of this update could not be read.');
    }
    $newVersion = $vm[1];
    if (version_compare($newVersion, APP_VERSION, '<') || (!$allowSameVersion && version_compare($newVersion, APP_VERSION, '=='))) {
        $zip->close();
        throw new RuntimeException('This update is version ' . $newVersion . ' and your store already has version ' . APP_VERSION . '.');
    }

    // Files to write (folders, protected paths and unsafe names are skipped).
    $files = [];
    foreach ($names as $i => $n) {
        if ($prefix !== '' && strpos($n, $prefix) !== 0) {
            continue;
        }
        $rel = substr($n, strlen($prefix));
        if ($rel === '' || substr($rel, -1) === '/' || update_is_protected($rel)) {
            continue;
        }
        if (strpos($rel, '..') !== false || $rel[0] === '/' || strpos($rel, ':') !== false || strpos($rel, "\0") !== false) {
            $zip->close();
            throw new RuntimeException('The zip contains an unsafe file name (' . $rel . '). Nothing was changed.');
        }
        $files[$i] = $rel;
    }

    // Check that every file can be written before changing anything.
    $blocked = [];
    foreach ($files as $rel) {
        $target = APP_ROOT . '/' . $rel;
        if (is_file($target)) {
            if (!is_writable($target)) {
                $blocked[] = $rel;
            }
        } else {
            $dir = dirname($target);
            while (!is_dir($dir) && $dir !== APP_ROOT && strlen($dir) > strlen(APP_ROOT)) {
                $dir = dirname($dir);
            }
            if (!is_writable($dir)) {
                $blocked[] = $rel;
            }
        }
    }
    if ($blocked) {
        $zip->close();
        throw new RuntimeException('These files cannot be written (file permissions): ' . implode(', ', array_slice($blocked, 0, 8))
            . (count($blocked) > 8 ? ' and ' . (count($blocked) - 8) . ' more' : '') . '. Nothing was changed. In cPanel File Manager, set folders to 755 and files to 644.');
    }

    // Backup of the files that will be replaced.
    $backupDir = APP_ROOT . '/data/backups';
    if (!is_dir($backupDir) && !@mkdir($backupDir, 0755, true)) {
        $zip->close();
        throw new RuntimeException('Could not create data/backups/ for the backup. Nothing was changed.');
    }
    $backupPath = $backupDir . '/before-update-' . APP_VERSION . '-to-' . $newVersion . '-' . date('Ymd-His') . '.zip';
    $backup = new ZipArchive();
    if ($backup->open($backupPath, ZipArchive::CREATE) !== true) {
        $zip->close();
        throw new RuntimeException('Could not create the backup file. Nothing was changed.');
    }
    foreach ($files as $rel) {
        if (is_file(APP_ROOT . '/' . $rel)) {
            $backup->addFile(APP_ROOT . '/' . $rel, $rel);
        }
    }
    $backup->close();

    // Write the new files.
    $written = 0;
    foreach ($files as $i => $rel) {
        $content = $zip->getFromIndex($i);
        if ($content === false) {
            continue;
        }
        $target = APP_ROOT . '/' . $rel;
        if ($rel === '.htaccess' && is_file($target)) {
            $content = update_merge_htaccess((string) file_get_contents($target), $content);
        }
        if (!is_dir(dirname($target))) {
            @mkdir(dirname($target), 0755, true);
        }
        // Write to a temporary file first, then swap it in, so a visitor never gets a half-written file.
        $tmp = $target . '.new-' . bin2hex(random_bytes(3));
        if (file_put_contents($tmp, $content) === false || !@rename($tmp, $target)) {
            @unlink($tmp);
            $zip->close();
            throw new RuntimeException('Writing ' . $rel . ' failed after ' . $written . ' files. Restore with the backup ' . basename($backupPath) . ' (in data/backups) if the store does not work.');
        }
        $written++;
    }
    $zip->close();
    if (function_exists('opcache_reset')) {
        @opcache_reset(); // load the new code on the next page
    }
    return ['version' => $newVersion, 'files' => $written, 'backup' => basename($backupPath)];
}

/** Keep the lines cPanel adds to .htaccess (PHP version selector) when .htaccess is updated. */
function update_merge_htaccess(string $old, string $new): string
{
    if (preg_match_all('/# php -- BEGIN cPanel-generated handler.*?# php -- END cPanel-generated handler[^\n]*\n?/s', $old, $m)) {
        $new = rtrim($new) . "\n\n" . implode("\n", $m[0]);
    }
    return $new;
}

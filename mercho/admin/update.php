<?php
require __DIR__ . '/includes/auth.php';
require APP_ROOT . '/includes/updater.php';
require_admin('owner');
set_time_limit(300);

$check = null;
if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save_url') {
        $u = trim((string) ($_POST['update_url'] ?? ''));
        set_setting('update_url', preg_match('#^https://#i', $u) ? $u : '');
        flash('success', 'Update address saved.');
        redirect('admin/update.php');
    }
    if ($action === 'check') {
        $GLOBALS['ACTIVITY_SKIP'] = true; // only checking: nothing changed
        $check = update_check();
        $_SESSION['update_check'] = $check;
        redirect('admin/update.php?checked=1');
    }
    try {
        if ($action === 'install_online') {
            $info = update_check();
            if (isset($info['error'])) {
                throw new RuntimeException($info['error']);
            }
            $zipPath = update_download($info);
        } elseif ($action === 'install_upload') {
            $f = $_FILES['zip'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException($f && in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                    ? 'The zip is larger than your server allows for uploads (' . ini_get('upload_max_filesize') . '). Use the online update or FTP.'
                    : 'Please choose the update zip file.');
            }
            $zipPath = update_work_dir() . '/upload-' . date('YmdHis') . '.zip';
            if (!move_uploaded_file($f['tmp_name'], $zipPath)) {
                throw new RuntimeException('Could not save the uploaded file.');
            }
        } else {
            redirect('admin/update.php');
        }
        $from = APP_VERSION;
        $result = update_install($zipPath, !empty($_POST['reinstall']));
        @unlink($zipPath);
        log_activity('Updated the store from ' . $from . ' to ' . $result['version'], $result['files'] . ' files replaced · backup: data/backups/' . $result['backup']);
        $GLOBALS['ACTIVITY_SKIP'] = true;
        unset($_SESSION['update_check']);
        // The next page loads the new code, which updates the database automatically.
        flash('success', 'Mercho was updated to version ' . $result['version'] . ' (' . $result['files'] . ' files). A backup of the old files is in data/backups/' . $result['backup'] . '.');
        redirect('admin/update.php?done=1');
    } catch (RuntimeException $ex) {
        if (isset($zipPath)) {
            @unlink($zipPath);
        }
        flash('error', $ex->getMessage());
        redirect('admin/update.php');
    }
}

$check = isset($_GET['checked']) ? ($_SESSION['update_check'] ?? null) : null;
$adminTitle = 'Updates';
include __DIR__ . '/includes/header.php';
?>
<div class="grid-main">
  <div>
    <div class="card">
      <div class="card-head"><h2>Your version: <?= e(APP_VERSION) ?></h2></div>
      <?php if (update_source_url() !== ''): ?>
        <form method="post"><?= csrf_field() ?><button class="btn btn-light" name="action" value="check"><?= icon('refresh', 16) ?> Check for updates</button></form>
        <?php if ($check && isset($check['error'])): ?>
          <div class="alert alert-error"><?= e($check['error']) ?></div>
        <?php elseif ($check && version_compare($check['version'], APP_VERSION, '>')): ?>
          <div class="alert alert-info">
            <b>Version <?= e($check['version']) ?> is available.</b>
            <?php if ($check['notes'] !== ''): ?><p style="white-space:pre-line;margin:8px 0"><?= e($check['notes']) ?></p><?php endif; ?>
            <form method="post" data-confirm="Update to version <?= e($check['version']) ?> now? Your products, orders, settings and images are kept, and the old files are backed up."><?= csrf_field() ?>
              <button class="btn btn-primary" name="action" value="install_online">Update now</button></form>
          </div>
        <?php elseif ($check): ?>
          <div class="alert alert-success">You have the latest version. 🎉</div>
        <?php endif; ?>
      <?php else: ?>
        <p class="muted">Upload the update zip below, or set an update address (bottom of this page) to update in one click.</p>
      <?php endif; ?>
    </div>

    <form method="post" enctype="multipart/form-data" class="card" data-confirm="Install this update? Your products, orders, settings and images are kept, and the old files are backed up.">
      <?= csrf_field() ?>
      <div class="card-head"><h2>Install from a zip file</h2></div>
      <p class="help">Upload the new Mercho zip (the same zip you download - no need to unzip it).</p>
      <label>Update zip<input type="file" name="zip" accept=".zip,application/zip" required></label>
      <label class="inline small"><input type="checkbox" name="reinstall" value="1"> Allow installing the same version again (repair)</label>
      <button class="btn btn-primary" name="action" value="install_upload" type="submit">Install update</button>
    </form>
  </div>

  <div>
    <div class="card">
      <div class="card-head"><h2>What an update does</h2></div>
      <ul class="steps-list">
        <li>Replaces the store files with the new version.</li>
        <li><b>Keeps</b> config.php, your database, your images (uploads/) and your settings.</li>
        <li>Saves the replaced files in <code>data/backups/</code> first.</li>
        <li>Updates the database automatically on the next page.</li>
      </ul>
      <p class="help">Tip: download a <a href="backup.php">database backup</a> before big updates.</p>
    </div>
    <form method="post" class="card">
      <?= csrf_field() ?>
      <div class="card-head"><h2>Update address</h2></div>
      <p class="help">The link to the update information (update.json) given by the seller of the script. Leave empty to use the built-in address<?= MERCHO_UPDATE_URL !== '' ? ' (' . e(MERCHO_UPDATE_URL) . ')' : '' ?>.</p>
      <input name="update_url" type="url" value="<?= e(setting('update_url')) ?>" placeholder="https://example.com/mercho/update.json">
      <button class="btn btn-light" name="action" value="save_url" type="submit">Save</button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

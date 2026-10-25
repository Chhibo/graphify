<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$langs = languages_all(true);
$edit = input('edit');
if ($edit !== '' && !isset($langs[$edit])) {
    $edit = '';
}

/** Translations that ship in includes/lang/{code}.php (without database edits). */
$fileDict = function (string $code): array {
    $file = APP_ROOT . '/includes/lang/' . preg_replace('/[^a-z_-]/', '', $code) . '.php';
    return is_file($file) ? (array) require $file : [];
};
$dbDict = function (string $code): array {
    $out = [];
    foreach (q_all('SELECT msgid, msgstr FROM translations WHERE lang = ?', [$code]) as $r) {
        $out[$r['msgid']] = $r['msgstr'];
    }
    return $out;
};
/** Saves one text; an empty value or one equal to the shipped file removes the database override. */
$saveText = function (string $code, string $msgid, string $msgstr, array $file): void {
    $hash = sha1($msgid);
    $msgstr = trim(str_replace("\r\n", "\n", $msgstr));
    if ($msgstr === '' || $msgstr === ($file[$msgid] ?? null)) {
        q('DELETE FROM translations WHERE lang = ? AND hash = ?', [$code, $hash]);
        return;
    }
    if (q_val('SELECT id FROM translations WHERE lang = ? AND hash = ?', [$code, $hash])) {
        q('UPDATE translations SET msgstr = ? WHERE lang = ? AND hash = ?', [$msgstr, $code, $hash]);
    } else {
        db_insert('translations', ['lang' => $code, 'hash' => $hash, 'msgid' => $msgid, 'msgstr' => $msgstr]);
    }
};

if (input('export') !== '' && isset($langs[input('export')])) {
    $code = input('export');
    $dict = array_filter(array_merge($fileDict($code), $dbDict($code)), fn($v) => $v !== '');
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="bazaarly-' . $code . '.json"');
    echo json_encode(['language' => $code, 'name' => $langs[$code]['name'], 'direction' => $langs[$code]['direction'], 'texts' => $dict],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (is_post()) {
    $action = input('action');
    if ($action === 'add') {
        $code = strtolower(input('code'));
        $name = mb_substr(input('name'), 0, 60);
        if (!preg_match('/^[a-z]{2,3}([_-][a-z0-9]{2,4})?$/', $code)) {
            flash('error', 'Use a short language code such as fr, es, hi or pt-br.');
        } elseif (isset($langs[$code])) {
            flash('error', 'That language already exists.');
        } elseif ($name === '') {
            flash('error', 'Enter the language name, written in that language (e.g. Français).');
        } else {
            db_insert('languages', ['code' => $code, 'name' => $name, 'direction' => input('direction') === 'rtl' ? 'rtl' : 'ltr',
                'enabled' => 0, 'sort_order' => count($langs)]);
            log_activity('language.add', "$name ($code)");
            flash('success', "$name added. Translate it, then switch it on.");
            redirect('admin/languages.php?edit=' . urlencode($code));
        }
        redirect('admin/languages.php');
    }
    if ($action === 'settings') {
        $default = isset($langs[input('default_language')]) ? input('default_language') : 'en';
        save_settings(['default_language' => $default, 'detect_browser_language' => empty($_POST['detect_browser_language']) ? '0' : '1']);
        q('UPDATE languages SET enabled = 1 WHERE code = ?', [$default]);
        log_activity('settings.languages', 'Default language: ' . $default);
        flash('success', 'Language settings saved.');
        redirect('admin/languages.php');
    }
    $code = input('code');
    if (!isset($langs[$code])) {
        redirect('admin/languages.php');
    }
    $isDefault = $code === setting('default_language', 'en');
    if ($action === 'toggle') {
        if ($isDefault && $langs[$code]['enabled']) {
            flash('error', 'The default language cannot be switched off. Choose another default first.');
        } else {
            q('UPDATE languages SET enabled = ? WHERE code = ?', [$langs[$code]['enabled'] ? 0 : 1, $code]);
            flash('success', $langs[$code]['name'] . ($langs[$code]['enabled'] ? ' switched off.' : ' is now available to visitors.'));
        }
        redirect('admin/languages.php');
    }
    if ($action === 'update') {
        q('UPDATE languages SET name = ?, direction = ? WHERE code = ?', [mb_substr(input('name'), 0, 60) ?: $langs[$code]['name'], input('direction') === 'rtl' ? 'rtl' : 'ltr', $code]);
        flash('success', 'Language updated.');
        redirect('admin/languages.php?edit=' . urlencode($code));
    }
    if ($action === 'delete') {
        if ($code === 'en' || $isDefault) {
            flash('error', 'English and the default language cannot be deleted.');
        } else {
            q('DELETE FROM translations WHERE lang = ?', [$code]);
            q('DELETE FROM languages WHERE code = ?', [$code]);
            q('UPDATE users SET lang = NULL WHERE lang = ?', [$code]);
            log_activity('language.delete', $langs[$code]['name'] . " ($code)");
            flash('success', 'Language deleted.');
        }
        redirect('admin/languages.php');
    }
    if ($action === 'save') {
        $file = $fileDict($code);
        $ids = (array) ($_POST['id'] ?? []);
        $n = 0;
        foreach ((array) ($_POST['t'] ?? []) as $hash => $value) {
            $msgid = (string) ($ids[$hash] ?? '');
            if ($msgid === '' || sha1($msgid) !== $hash || !is_string($value)) {
                continue;
            }
            $saveText($code, $msgid, mb_substr($value, 0, 5000), $file);
            $n++;
        }
        log_activity('language.translate', $langs[$code]['name'] . ": $n texts saved");
        flash('success', 'Translations saved.');
        back('admin/languages.php?edit=' . urlencode($code));
    }
    if ($action === 'import') {
        $up = $_FILES['file'] ?? null;
        $json = $up && ($up['error'] ?? 1) === UPLOAD_ERR_OK && $up['size'] < 5 * 1024 * 1024 ? json_decode((string) file_get_contents($up['tmp_name']), true) : null;
        $texts = is_array($json) ? ($json['texts'] ?? $json) : null;
        if (!is_array($texts)) {
            flash('error', 'That file is not a valid translation file (JSON exported from this page).');
        } else {
            $file = $fileDict($code);
            $n = 0;
            foreach ($texts as $msgid => $msgstr) {
                if (is_string($msgid) && is_string($msgstr) && $msgid !== '' && $msgstr !== '') {
                    $saveText($code, $msgid, mb_substr($msgstr, 0, 5000), $file);
                    $n++;
                }
            }
            log_activity('language.import', $langs[$code]['name'] . ": $n texts");
            flash('success', "$n texts imported.");
        }
        redirect('admin/languages.php?edit=' . urlencode($code));
    }
    redirect('admin/languages.php');
}

$adminActive = 'languages';
$strings = translatable_strings();

if ($edit === '') {
    $pageTitle = 'Languages';
    require APP_ROOT . '/includes/admin-top.php';
    ?>
<p class="muted">Visitors pick a language from the <?= icon('languages') ?> menu in the header. Members can also choose one in their account settings. The admin panel always stays in English.</p>

<div class="card table-card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Language</th><th>Code</th><th>Translated</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($langs as $code => $l):
          $dict = array_filter(array_merge($fileDict($code), $dbDict($code)), fn($v) => $v !== '');
          $done = $code === 'en' ? count($strings) : count(array_intersect_key($dict, array_flip($strings)));
          $pct = $strings ? (int) floor($done / count($strings) * 100) : 0;
          $isDefault = $code === setting('default_language', 'en'); ?>
        <tr>
          <td><strong lang="<?= e($code) ?>"><?= e($l['name']) ?></strong><?php if ($isDefault): ?> <span class="badge badge-brand">Default</span><?php endif; ?><?php if ($l['direction'] === 'rtl'): ?> <span class="badge badge-muted">Right-to-left</span><?php endif; ?></td>
          <td><code><?= e($code) ?></code></td>
          <td style="min-width:160px"><?php if ($code === 'en'): ?><span class="muted small">Original texts</span><?php else: ?><div class="progress sm"><span style="width:<?= $pct ?>%"></span></div><small class="muted"><?= $done ?> of <?= count($strings) ?> (<?= $pct ?>%)</small><?php endif; ?></td>
          <td><?= $l['enabled'] ? '<span class="badge badge-success">On</span>' : '<span class="badge badge-muted">Off</span>' ?></td>
          <td class="row-actions">
            <a class="btn btn-ghost btn-sm" href="?edit=<?= e(urlencode($code)) ?>"><?= icon('edit') ?><?= $code === 'en' ? 'Customise texts' : 'Translate' ?></a>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="code" value="<?= e($code) ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= $l['enabled'] ? 'Switch off' : 'Switch on' ?></button></form>
            <?php if ($code !== 'en' && !$isDefault): ?><form method="post" data-confirm="Delete <?= e($l['name']) ?> and all its translations?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="code" value="<?= e($code) ?>"><button class="btn btn-ghost btn-sm danger" type="submit" aria-label="Delete"><?= icon('trash') ?></button></form><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="admin-grid">
  <form method="post" class="card form-section">
    <?= csrf_field() ?><input type="hidden" name="action" value="settings">
    <h2>Language settings</h2>
    <div class="field"><label for="l-def">Default language</label><select id="l-def" name="default_language">
      <?php foreach ($langs as $code => $l): ?><option value="<?= e($code) ?>" <?= setting('default_language', 'en') === $code ? 'selected' : '' ?>><?= e($l['name']) ?></option><?php endforeach; ?>
    </select><small class="muted">Used for new visitors and search engines.</small></div>
    <label class="switch"><input type="checkbox" name="detect_browser_language" value="1" <?= setting('detect_browser_language', '1') === '1' ? 'checked' : '' ?>><span class="switch-ui"></span><span>Use the visitor's browser language when it is available</span></label>
    <p><button class="btn btn-primary" type="submit">Save settings</button></p>
  </form>

  <form method="post" class="card form-section">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <h2>Add a language</h2>
    <div class="form-grid">
      <div class="field"><label for="l-code">Code</label><input id="l-code" name="code" placeholder="fr" maxlength="10" required><small class="muted">2–3 letters, e.g. fr, hi, ur</small></div>
      <div class="field"><label for="l-name">Name</label><input id="l-name" name="name" placeholder="Français" maxlength="60" required><small class="muted">As it is written in that language</small></div>
    </div>
    <label class="switch"><input type="checkbox" name="direction" value="rtl"><span class="switch-ui"></span><span>Written right-to-left (Arabic, Urdu, Hebrew…)</span></label>
    <p><button class="btn btn-primary" type="submit"><?= icon('plus') ?>Add language</button></p>
  </form>
</div>
<?php
    require APP_ROOT . '/includes/admin-bottom.php';
    exit;
}

// ---- translation editor
$l = $langs[$edit];
$file = $fileDict($edit);
$db = $dbDict($edit);
$search = trim(input('q'));
$filter = in_array(input('show'), ['missing', 'changed'], true) ? input('show') : 'all';
$rows = [];
foreach ($strings as $s) {
    $value = $db[$s] ?? $file[$s] ?? '';
    if ($filter === 'missing' && $value !== '') continue;
    if ($filter === 'changed' && !isset($db[$s])) continue;
    if ($search !== '' && mb_stripos($s, $search) === false && mb_stripos($value, $search) === false) continue;
    $rows[] = [$s, $value];
}
$missing = count(array_filter($strings, fn($s) => ($db[$s] ?? $file[$s] ?? '') === ''));
[$page, $pages, $offset] = paginate(count($rows), 50, (int) input('page', '1'));
$rows = array_slice($rows, $offset, 50);

$pageTitle = ($edit === 'en' ? 'Customise texts: ' : 'Translate: ') . $l['name'];
require APP_ROOT . '/includes/admin-top.php';
?>
<p><a class="small-link" href="<?= e(url('admin/languages.php')) ?>"><?= icon('chevron-left') ?>All languages</a></p>
<?php if ($edit === 'en'): ?>
  <div class="alert alert-info"><?= icon('info') ?><span>Change any English text on the public site, for example rename “Post ad” to “Sell now”. Leave a box empty to use the original text.</span></div>
<?php endif; ?>

<div class="admin-grid">
  <form method="post" class="card form-section">
    <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="code" value="<?= e($edit) ?>">
    <h2>Language details</h2>
    <div class="field"><label for="ld-name">Name</label><input id="ld-name" name="name" value="<?= e($l['name']) ?>" maxlength="60"></div>
    <label class="switch"><input type="checkbox" name="direction" value="rtl" <?= $l['direction'] === 'rtl' ? 'checked' : '' ?>><span class="switch-ui"></span><span>Written right-to-left</span></label>
    <p><button class="btn btn-ghost" type="submit">Save details</button></p>
  </form>
  <div class="card form-section">
    <h2>Import / export</h2>
    <p class="muted small">Download the texts as a JSON file to translate them offline (or send them to a translator), then upload the file again.</p>
    <p><a class="btn btn-ghost" href="?export=<?= e(urlencode($edit)) ?>"><?= icon('download') ?>Export JSON</a></p>
    <form method="post" enctype="multipart/form-data" class="inline-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="import"><input type="hidden" name="code" value="<?= e($edit) ?>">
      <input type="file" name="file" accept=".json,application/json" required>
      <button class="btn btn-ghost" type="submit"><?= icon('upload') ?>Import</button>
    </form>
  </div>
</div>

<form method="get" class="toolbar">
  <input type="hidden" name="edit" value="<?= e($edit) ?>">
  <div class="input-icon grow"><?= icon('search') ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search texts…"></div>
  <select name="show" data-autosubmit>
    <option value="all">All texts (<?= count($strings) ?>)</option>
    <?php if ($edit !== 'en'): ?><option value="missing" <?= $filter === 'missing' ? 'selected' : '' ?>>Not translated yet (<?= $missing ?>)</option><?php endif; ?>
    <option value="changed" <?= $filter === 'changed' ? 'selected' : '' ?>>Changed here (<?= count($db) ?>)</option>
  </select>
  <button class="btn btn-ghost" type="submit">Filter</button>
</form>

<?php if ($rows): ?>
<form method="post" class="card translate-form">
  <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="code" value="<?= e($edit) ?>">
  <div class="translate-list">
    <?php foreach ($rows as [$msgid, $value]): $h = sha1($msgid); ?>
      <div class="translate-row">
        <label for="t<?= $h ?>"><?= e($msgid) ?><?php if (isset($db[$msgid])): ?> <span class="badge badge-info">changed</span><?php endif; ?></label>
        <input type="hidden" name="id[<?= $h ?>]" value="<?= e($msgid) ?>">
        <textarea id="t<?= $h ?>" name="t[<?= $h ?>]" rows="<?= mb_strlen($msgid) > 80 ? 3 : 1 ?>" lang="<?= e($edit) ?>" dir="<?= $l['direction'] === 'rtl' ? 'rtl' : 'ltr' ?>" placeholder="<?= e($msgid) ?>"><?= e($value) ?></textarea>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="sticky-save"><span class="muted small">Words in {curly brackets} are filled in automatically — keep them as they are.</span><button class="btn btn-primary" type="submit"><?= icon('check') ?>Save translations</button></div>
</form>
<?= pagination_links($page, $pages) ?>
<?php else: ?>
  <div class="card empty-state"><?= icon('check-circle') ?><h3>Nothing to show</h3><p><?= $filter === 'missing' ? 'Every text is translated. 🎉' : 'No texts match your search.' ?></p></div>
<?php endif; ?>
<?php require APP_ROOT . '/includes/admin-bottom.php';

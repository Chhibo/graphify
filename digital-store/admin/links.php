<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

$errors = [];
$edit = ['id' => 0, 'code' => '', 'title' => '', 'description' => '', 'image' => '', 'target_url' => '', 'active' => 1];
if (!empty($_GET['edit'])) {
    $q = db()->prepare('SELECT * FROM links WHERE id = ?');
    $q->execute([(int)$_GET['edit']]);
    $edit = $q->fetch() ?: $edit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $q = db()->prepare('SELECT image FROM links WHERE id = ?');
        $q->execute([$id]);
        if ($img = $q->fetchColumn()) {
            @unlink(UPLOADS_DIR . '/' . basename($img));
        }
        db()->prepare('DELETE FROM links WHERE id = ?')->execute([$id]);
        flash('Link deleted.');
        redirect('admin/links.php');
    }
    if ($action === 'toggle') {
        db()->prepare('UPDATE links SET active = 1 - active WHERE id = ?')->execute([$id]);
        redirect('admin/links.php');
    }
    if ($action === 'save') {
        if ($id) {
            $q = db()->prepare('SELECT * FROM links WHERE id = ?');
            $q->execute([$id]);
            $edit = $q->fetch() ?: $edit;
        }
        $edit['title'] = trim((string)($_POST['title'] ?? ''));
        $edit['description'] = trim((string)($_POST['description'] ?? ''));
        $edit['target_url'] = trim((string)($_POST['target_url'] ?? ''));
        $edit['active'] = isset($_POST['active']) ? 1 : 0;
        $code = strtolower(preg_replace('/[^A-Za-z0-9-]/', '', (string)($_POST['code'] ?? '')));
        if ($edit['title'] === '') {
            $errors[] = 'Give the link a title.';
        }
        if (!preg_match('#^https?://#i', $edit['target_url'])) {
            $errors[] = 'The hidden link must start with http:// or https://';
        }
        if ($code === '') {
            $code = $edit['code'] !== '' ? $edit['code'] : substr(random_token(4), 0, 7);
        }
        $q = db()->prepare('SELECT COUNT(*) FROM links WHERE code = ? AND id != ?');
        $q->execute([$code, $id]);
        if ((int)$q->fetchColumn()) {
            $errors[] = 'That short code is already used. Pick another one.';
        }
        $edit['code'] = $code;
        if (!$errors) {
            $img = save_uploaded_image($_FILES['image'] ?? null, $errors);
            if ($img) {
                if ($edit['image']) @unlink(UPLOADS_DIR . '/' . basename($edit['image']));
                $edit['image'] = $img;
            }
        }
        if (!$errors) {
            $vals = [$code, $edit['title'], $edit['description'], $edit['image'], $edit['target_url'], $edit['active']];
            if ($id) {
                db()->prepare('UPDATE links SET code=?, title=?, description=?, image=?, target_url=?, active=? WHERE id=?')->execute(array_merge($vals, [$id]));
            } else {
                db()->prepare('INSERT INTO links (code, title, description, image, target_url, active, created_at) VALUES (?,?,?,?,?,?,?)')->execute(array_merge($vals, [time()]));
            }
            flash('Link saved. Copy its short address and share it.');
            redirect('admin/links.php');
        }
    }
}

$links = db()->query("SELECT l.*,
        (SELECT COUNT(*) FROM unlocks u WHERE u.link_id = l.id) AS opened,
        (SELECT COUNT(*) FROM unlocks u WHERE u.link_id = l.id AND u.completed_at IS NOT NULL AND u.network != 'test') AS unlocks,
        (SELECT COALESCE(SUM(payout), 0) FROM unlocks u WHERE u.link_id = l.id AND u.completed_at IS NOT NULL AND u.network != 'test') AS earned
    FROM links l ORDER BY l.id DESC")->fetchAll();

admin_header('Link locker', 'Lock any link behind an offer and share it anywhere',
    '<a class="btn btn-grad" href="#form">' . icon('plus', 16) . ' New locked link</a>');
?>
<p class="muted">Put a Mega or Google Drive link, a Discord invite, a coupon page or anything else behind an offer. Share the short address on TikTok, YouTube, Telegram or Instagram. Visitors complete one offer and are sent to the hidden link.</p>

<?php if ($links): ?>
<div class="table-wrap"><table>
  <thead><tr><th>Link</th><th>Short address</th><th class="num">Views</th><th class="num">Unlocks</th><th class="num">Conv.</th><th class="num">Earned</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($links as $l): $short = url_link($l['code']); ?>
    <tr>
      <td><div class="prod-cell">
        <?php if ($l['image']): ?><img class="mini" src="../uploads/<?= e($l['image']) ?>" alt=""><?php else: ?><span class="mini-empty"><?= icon('link') ?></span><?php endif; ?>
        <div><a href="?edit=<?= (int)$l['id'] ?>#form"><?= e($l['title']) ?></a><br><small><?= e(mb_strimwidth($l['target_url'], 0, 48, '…')) ?></small></div>
      </div></td>
      <td><code class="wrap"><?= e($short) ?></code>
        <button type="button" class="btn btn-small btn-light copy-btn" data-copy="<?= e($short) ?>"><?= icon('link', 13) ?> Copy</button></td>
      <td class="num"><?= number_format((int)$l['views']) ?></td>
      <td class="num"><?= number_format((int)$l['unlocks']) ?></td>
      <td class="num"><?= $l['opened'] ? round($l['unlocks'] / $l['opened'] * 100) : 0 ?>%</td>
      <td class="num">$<?= number_format((float)$l['earned'], 2) ?></td>
      <td><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
        <button class="pill <?= $l['active'] ? 'on' : '' ?>" name="action" value="toggle"><?= $l['active'] ? 'Live' : 'Off' ?></button></form></td>
      <td class="actions">
        <a class="icon-btn" href="<?= e($short) ?>" target="_blank" title="Open"><?= icon('external', 16) ?></a>
        <a class="icon-btn" href="?edit=<?= (int)$l['id'] ?>#form" title="Edit"><?= icon('pencil', 16) ?></a>
        <form method="post" onsubmit="return confirm('Delete this locked link?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
          <button class="icon-btn danger" name="action" value="delete" title="Delete"><?= icon('trash', 16) ?></button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card form" id="form" style="max-width:720px">
  <h2><?= $edit['id'] ? 'Edit locked link' : 'New locked link' ?></h2>
  <p class="card-sub">Visitors see the title, picture and text, then complete an offer to open the hidden link.</p>
  <?php foreach ($errors as $err): ?><?= notice(e($err), '', '', 'bad', 'x') ?><?php endforeach; ?>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
  <label>Title <input name="title" required maxlength="150" value="<?= e($edit['title']) ?>" placeholder="e.g. Free Minecraft Texture Pack"></label>
  <label>Hidden link (where visitors go after the offer)
    <input name="target_url" required value="<?= e($edit['target_url']) ?>" placeholder="https://mega.nz/…">
    <small>Never shown on the page. Visitors are sent there only after their offer is confirmed.</small></label>
  <label>Short code (optional) <input name="code" maxlength="40" value="<?= e($edit['code']) ?>" placeholder="random if empty, e.g. texture-pack">
    <small>The address will be <?= e(url_link('your-code')) ?></small></label>
  <label>Text (optional) <textarea name="description" rows="3"><?= e($edit['description']) ?></textarea></label>
  <label>Picture (optional)
    <?php if ($edit['image']): ?><img class="preview" src="../uploads/<?= e($edit['image']) ?>" alt="" style="max-height:120px"><?php endif; ?>
    <input type="file" name="image" accept="image/png,image/jpeg,image/webp,image/gif"></label>
  <label class="switch"><input type="checkbox" name="active" <?= $edit['active'] ? 'checked' : '' ?>><span></span> Live</label><br><br>
  <div class="form-actions">
    <button class="btn btn-grad" name="action" value="save"><?= icon('save', 16) ?> Save link</button>
    <?php if ($edit['id']): ?><a class="btn btn-light" href="links.php">Cancel</a><?php endif; ?>
  </div>
</form>
<script>
document.querySelectorAll('.copy-btn').forEach(function (b) {
  b.addEventListener('click', function () {
    navigator.clipboard && navigator.clipboard.writeText(b.dataset.copy).then(function () { b.lastChild.textContent = ' Copied!'; });
  });
});
</script>
<?php admin_footer();

<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_login();
$uid = (int) $me['id'];

if (setting('enable_saved_searches', '1') !== '1') {
    flash('info', 'Saved searches are turned off on this site.');
    redirect('dashboard/');
}

if (is_post()) {
    $action = input('action');
    if ($action === 'save') {
        parse_str(input('query'), $raw);
        $query = search_query(listing_search($raw)['values']);
        if (!$query) {
            flash('error', 'Add a keyword or a filter before saving a search.');
            back('dashboard/searches.php');
        }
        $qs = http_build_query($query);
        if (q_val('SELECT id FROM saved_searches WHERE user_id = ? AND query = ?', [$uid, $qs])) {
            flash('info', 'You already saved this search.');
        } elseif ((int) q_val('SELECT COUNT(*) FROM saved_searches WHERE user_id = ?', [$uid]) >= 25) {
            flash('error', 'You can keep up to 25 saved searches. Delete one first.');
        } else {
            $name = mb_substr(input('name') ?: search_summary(listing_search($query)['values']), 0, 150);
            db_insert('saved_searches', ['user_id' => $uid, 'name' => $name, 'query' => $qs, 'alerts' => 1, 'last_alert_at' => now(), 'created_at' => now()]);
            flash('success', 'Search saved! We\'ll email you when new ads match.');
        }
        back('dashboard/searches.php');
    }
    $s = q_one('SELECT * FROM saved_searches WHERE id = ? AND user_id = ?', [(int) input('id'), $uid]);
    if ($s) {
        if ($action === 'toggle') {
            q('UPDATE saved_searches SET alerts = ?, last_alert_at = ? WHERE id = ?', [$s['alerts'] ? 0 : 1, now(), $s['id']]);
            flash('success', $s['alerts'] ? 'Email alerts turned off.' : 'Email alerts turned on.');
        } elseif ($action === 'rename' && input('name') !== '') {
            q('UPDATE saved_searches SET name = ? WHERE id = ?', [mb_substr(input('name'), 0, 150), $s['id']]);
            flash('success', 'Search renamed.');
        } elseif ($action === 'delete') {
            q('DELETE FROM saved_searches WHERE id = ?', [$s['id']]);
            flash('success', 'Saved search deleted.');
        }
    }
    redirect('dashboard/searches.php');
}

$rows = q_all('SELECT * FROM saved_searches WHERE user_id = ? ORDER BY created_at DESC', [$uid]);
$dashActive = 'searches';
$pageTitle = 'Saved searches';
require APP_ROOT . '/includes/dash-top.php';
?>
<div class="dash-head"><div><h1>Saved searches</h1><p class="muted">We'll email you when new ads match a saved search. Save one from any search results page.</p></div>
  <a class="btn btn-primary" href="<?= e(search_url([])) ?>"><?= icon('search') ?>Browse ads</a></div>

<?php if ($rows): ?>
  <div class="search-list">
    <?php foreach ($rows as $s):
        parse_str($s['query'], $raw);
        $r = listing_search($raw);
        $newCount = (int) q_val("SELECT COUNT(*) FROM listings l JOIN users u ON u.id = l.user_id WHERE {$r['where']} AND l.created_at > ? AND l.user_id <> ?", array_merge($r['params'], [$s['created_at'], $uid]));
        $total = (int) q_val("SELECT COUNT(*) FROM listings l JOIN users u ON u.id = l.user_id WHERE {$r['where']}", $r['params']);
    ?>
      <article class="card search-item">
        <span class="stat-icon<?= $s['alerts'] ? '' : ' muted-icon' ?>"><?= icon($s['alerts'] ? 'bell' : 'bell-off') ?></span>
        <div class="search-body">
          <form method="post" class="rename-form"><?= csrf_field() ?><input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
            <input name="name" value="<?= e($s['name']) ?>" aria-label="Search name" maxlength="150" data-rename></form>
          <p class="muted small"><?= e(search_summary($r['values'])) ?></p>
          <div class="meta-row small"><span><?= icon('package') ?><?= number_format($total) ?> matching ads</span><?php if ($newCount): ?><span class="text-accent"><?= icon('zap') ?><?= $newCount ?> new since saved</span><?php endif; ?><span><?= icon('calendar') ?>Saved <?= e(format_date($s['created_at'])) ?></span></div>
        </div>
        <div class="mi-actions">
          <a class="btn btn-primary btn-sm" href="<?= e(search_url(search_query($r['values']))) ?>"><?= icon('search') ?>View results</a>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= icon($s['alerts'] ? 'bell-off' : 'bell') ?><?= $s['alerts'] ? 'Pause alerts' : 'Turn on alerts' ?></button></form>
          <form method="post" data-confirm="Delete this saved search?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn-ghost btn-sm danger" type="submit"><?= icon('trash') ?>Delete</button></form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="card empty-state"><?= icon('bell') ?><h3>No saved searches yet</h3><p>Search for something, then tap <strong>Save search</strong> to get an email when new ads match.</p><a class="btn btn-primary" href="<?= e(search_url([])) ?>">Start searching</a></div>
<?php endif; ?>
<?php require APP_ROOT . '/includes/dash-bottom.php';

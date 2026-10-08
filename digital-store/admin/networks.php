<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/networks.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

$defs = network_definitions();
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        save_setting('network_mode', ($_POST['network_mode'] ?? '') === 'random' ? 'random' : 'priority');
        foreach ($defs as $net => $def) {
            save_setting("net_{$net}_enabled", isset($_POST[$net]['enabled']) ? '1' : '0');
            save_setting("net_{$net}_priority", (string)max(1, (int)($_POST[$net]['priority'] ?? 1)));
            foreach ($def['fields'] as $field => $f) {
                $val = trim((string)($_POST[$net][$field] ?? ''));
                // Secret fields are shown blank; leaving them blank keeps the saved key.
                if (!empty($f['secret']) && $val === '') {
                    continue;
                }
                save_setting("net_{$net}_{$field}", $val);
            }
        }
        flash('Networks saved.');
        redirect('admin/networks.php');
    }
    if ($action === 'test' && isset($defs[$_POST['network'] ?? ''])) {
        $net = $_POST['network'];
        try {
            $offers = fetch_offers($net, 'testtoken' . random_token(4), client_ip(),
                (string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 5);
            $testResult = [$net, true, count($offers) . ' offer(s) returned for your IP ' . client_ip() . '.', $offers];
        } catch (Throwable $ex) {
            $testResult = [$net, false, $ex->getMessage(), []];
        }
    }
}

admin_header('CPA Networks');
?>
<p class="muted">Switch networks on or off at any time. Visitors only see offers from networks that are switched on.</p>

<?php if ($testResult): [$tNet, $tOk, $tMsg, $tOffers] = $testResult; ?>
  <div class="card">
    <h2>Test: <?= e($defs[$tNet]['label']) ?></h2>
    <p class="<?= $tOk ? 'ok' : 'bad' ?>"><?= e($tMsg) ?></p>
    <?php if ($tOffers): ?>
      <ul><?php foreach ($tOffers as $o): ?><li><?= e($o['title']) ?> · $<?= number_format($o['payout'], 2) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </div>
<?php endif; ?>

<form method="post" class="form">
  <?= csrf_field() ?>
  <div class="card">
    <h2>How to choose a network for each visitor</h2>
    <label class="check"><input type="radio" name="network_mode" value="priority" <?= setting('network_mode') !== 'random' ? 'checked' : '' ?>>
      Priority: try the network with the lowest priority number first, and fall back to the next one if it has no offers.</label>
    <label class="check"><input type="radio" name="network_mode" value="random" <?= setting('network_mode') === 'random' ? 'checked' : '' ?>>
      Random: split visitors between the networks that are on (still falls back if one has no offers).</label>
  </div>

  <?php foreach ($defs as $net => $def): $on = network_enabled($net); ?>
    <div class="card network <?= $on ? 'is-on' : '' ?>">
      <div class="network-head">
        <h2><?= e($def['label']) ?></h2>
        <label class="switch"><input type="checkbox" name="<?= e($net) ?>[enabled]" <?= $on ? 'checked' : '' ?>><span></span> On</label>
      </div>
      <label>Priority <input type="number" min="1" name="<?= e($net) ?>[priority]" value="<?= e(setting("net_{$net}_priority", '1')) ?>" class="short"></label>
      <?php foreach ($def['fields'] as $field => $f): $val = network_setting($net, $field); ?>
        <label><?= e($f['label']) ?>
          <?php if (!empty($f['secret'])): ?>
            <input type="password" name="<?= e($net) ?>[<?= e($field) ?>]" autocomplete="off"
                   placeholder="<?= $val !== '' ? 'Saved (…' . e(substr($val, -4)) . '). Leave blank to keep.' : 'Paste your key' ?>">
          <?php else: ?>
            <input name="<?= e($net) ?>[<?= e($field) ?>]" value="<?= e($val) ?>">
          <?php endif; ?>
          <?php if (!empty($f['help'])): ?><small><?= e($f['help']) ?></small><?php endif; ?>
        </label>
      <?php endforeach; ?>
      <div class="postback">
        <b>Postback URL</b>: paste this into <?= e($def['label']) ?> → Postback / Global postback settings:
        <code class="copy"><?= e(postback_url($net)) ?></code>
        <small>Check that <code>{<?= e(network_setting($net, 'sub_param')) ?>}</code>, <code><?= e($def['payout_macro']) ?></code> and <code><?= e($def['offer_macro']) ?></code>
          match the macro names listed in your <?= e($def['label']) ?> postback page, and rename them here if they differ.</small>
      </div>
      <button class="btn btn-small btn-light" name="action" value="test" formnovalidate
              onclick="this.form.network.value='<?= e($net) ?>'">Test this network (save first)</button>
    </div>
  <?php endforeach; ?>
  <input type="hidden" name="network" value="">
  <button class="btn" name="action" value="save">Save networks</button>
</form>
<?php admin_footer();

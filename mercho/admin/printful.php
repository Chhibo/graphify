<?php
require __DIR__ . '/includes/auth.php';
require_admin('products');
set_time_limit(120);

const PF_BATCH = 10; // products imported per page load (keeps each request short on shared hosting)

/* ---------- Product sync, continued page by page ---------- */
if (isset($_GET['sync'])) {
    $state = $_SESSION['pf_sync'] ?? null;
    if (!$state || !hash_equals($state['token'], (string) $_GET['sync'])) {
        redirect('admin/printful.php');
    }
    try {
        $page = printful_list_products($state['offset'], PF_BATCH);
        foreach ($page['items'] as $item) {
            $state['seen'][] = (string) $item['id'];
            try {
                $r = printful_import_product((int) $item['id'], $state['category']);
                $state[$r]++;
            } catch (RuntimeException $ex) {
                $state['errors'][] = ($item['name'] ?? '#' . $item['id']) . ': ' . $ex->getMessage();
            }
        }
        $state['offset'] += PF_BATCH;
        $state['total'] = $page['total'];
        $done = $state['offset'] >= $page['total'] || !$page['items'];
    } catch (RuntimeException $ex) {
        $state['errors'][] = $ex->getMessage();
        $done = true;
    }
    $_SESSION['pf_sync'] = $state;

    if ($done) {
        unset($_SESSION['pf_sync']);
        $hidden = empty($state['errors']) ? printful_hide_missing($state['seen']) : 0;
        set_setting('printful_last_sync', now());
        flash('success', sprintf('Printful sync finished: %d new, %d updated, %d skipped%s.',
            $state['created'], $state['updated'], $state['skipped'], $hidden ? ', ' . $hidden . ' hidden (deleted in Printful)' : ''));
        foreach (array_slice($state['errors'], 0, 5) as $err) {
            flash('error', $err);
        }
        if ($state['skipped']) {
            flash('error', 'Skipped products have no retail price or no available variant in Printful. Set a retail price in Printful and sync again.');
        }
        redirect('admin/printful.php');
    }
    $adminTitle = 'Printful sync';
    include __DIR__ . '/includes/header.php';
    $pct = $state['total'] ? min(100, (int) round($state['offset'] / $state['total'] * 100)) : 0;
    ?>
    <div class="card narrow">
      <h2>Importing products from Printful…</h2>
      <p class="muted">Please keep this page open. <?= min($state['offset'], $state['total']) ?> of <?= (int) $state['total'] ?> products done.</p>
      <div class="progress"><span style="width: <?= $pct ?>%"></span></div>
    </div>
    <meta http-equiv="refresh" content="0;url=?sync=<?= e($state['token']) ?>">
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- Actions ---------- */
if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            set_setting('printful_enabled', post_flag('printful_enabled') ? '1' : '0');
            $token = trim((string) ($_POST['printful_token'] ?? ''));
            if ($token !== '') {
                set_setting('printful_token', $token);
            }
            if (!empty($_POST['printful_token__clear'])) {
                set_setting('printful_token', '');
            }
            set_setting('printful_store_id', preg_replace('/\D+/', '', (string) ($_POST['printful_store_id'] ?? '')));
            set_setting('printful_auto_paid', post_flag('printful_auto_paid') ? '1' : '0');
            set_setting('printful_auto_cod', post_flag('printful_auto_cod') ? '1' : '0');
            set_setting('printful_confirm', post_flag('printful_confirm') ? '1' : '0');
            set_setting('printful_category_id', (string) (int) ($_POST['printful_category_id'] ?? 0));
            settings_all(true);
            if (printful_connected()) {
                flash('success', 'Saved. Connected to ' . printful_test() . ' ✓');
            } else {
                flash('success', 'Saved.');
            }
        } elseif ($action === 'sync') {
            if (!printful_connected()) {
                throw new RuntimeException('Add your Printful API token first.');
            }
            $_SESSION['pf_sync'] = [
                'token' => bin2hex(random_bytes(8)), 'offset' => 0, 'total' => 0, 'seen' => [], 'errors' => [],
                'created' => 0, 'updated' => 0, 'skipped' => 0,
                'category' => (int) setting('printful_category_id') ?: null,
            ];
            redirect('admin/printful.php?sync=' . $_SESSION['pf_sync']['token']);
        } elseif ($action === 'webhook') {
            printful_register_webhook();
            set_setting('printful_webhook_on', '1');
            flash('success', 'Done. Printful will now update orders automatically when they ship.');
        }
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('admin/printful.php');
}

$products = q_all("SELECT p.id, p.name, p.image, p.price, p.active, c.name AS category_name,
                          (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id AND v.active = 1) AS variant_count
                   FROM products p LEFT JOIN categories c ON c.id = p.category_id
                   WHERE p.printful_id <> '' ORDER BY p.id DESC");
$https = strpos(full_url(), 'https://') === 0;
$adminTitle = 'Printful';
include __DIR__ . '/includes/header.php';
?>
<div class="grid-main">
  <div>
    <form method="post" class="card">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <div class="card-head"><h2>Connection</h2><?= printful_connected() ? '<span class="status st-delivered">Connected</span>' : '<span class="status st-pending">Not connected</span>' ?></div>
      <label class="inline"><input type="checkbox" name="printful_enabled" value="1" <?= setting_on('printful_enabled') ? 'checked' : '' ?>> Enable Printful (send orders of Printful products to Printful)</label>
      <label>API token
        <input type="password" name="printful_token" autocomplete="new-password" placeholder="<?= printful_connected() ? '•••••••• saved - leave empty to keep' : 'Paste your Printful private token' ?>">
      </label>
      <?php if (printful_connected()): ?><label class="inline small"><input type="checkbox" name="printful_token__clear" value="1"> Disconnect (remove token)</label><?php endif; ?>
      <p class="help">Printful → <b>Settings → Developers (API)</b> → <b>Create token</b>. Give it access to <i>Orders</i>, <i>Sync products</i>, <i>Webhooks</i> and <i>Stores</i>.</p>
      <label>Store ID <small class="muted">(only if your token is for the whole account and you have several Printful stores)</small>
        <input name="printful_store_id" value="<?= e(setting('printful_store_id')) ?>" inputmode="numeric">
      </label>

      <h3 class="sub">Orders</h3>
      <label class="inline"><input type="checkbox" name="printful_auto_paid" value="1" <?= setting_on('printful_auto_paid') ? 'checked' : '' ?>> Send PayPal / Stripe orders to Printful automatically when they are paid</label>
      <label class="inline"><input type="checkbox" name="printful_auto_cod" value="1" <?= setting_on('printful_auto_cod') ? 'checked' : '' ?>> Send Cash on Delivery orders automatically</label>
      <p class="help">Recommended: leave this off and send COD orders yourself from the order page after you confirm them with the customer (Printful charges you when it produces the order).</p>
      <label class="inline"><input type="checkbox" name="printful_confirm" value="1" <?= setting_on('printful_confirm') ? 'checked' : '' ?>> Submit orders for production immediately</label>
      <p class="help">When off, orders arrive in Printful as <b>drafts</b> and you confirm them in your Printful dashboard. When on, Printful starts production right away and charges your Printful billing method.</p>

      <h3 class="sub">Products</h3>
      <label>Category for new imported products
        <select name="printful_category_id">
          <option value="0">- None -</option>
          <?php foreach (categories() as $c): ?><option value="<?= (int) $c['id'] ?>" <?= setting('printful_category_id') === (string) $c['id'] ? 'selected' : '' ?>><?= $c['depth'] ? '&nbsp;&nbsp;— ' : '' ?><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <button class="btn btn-primary" type="submit">Save & test connection</button>
    </form>

    <div class="card">
      <div class="card-head"><h2>Printful products in your store (<?= count($products) ?>)</h2>
        <?php if (printful_connected()): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="sync"><button class="btn btn-primary" type="submit"><?= icon('refresh', 16) ?> Sync products now</button></form>
        <?php endif; ?>
      </div>
      <?php if (setting('printful_last_sync') !== ''): ?><p class="muted small">Last sync: <?= e(setting('printful_last_sync')) ?></p><?php endif; ?>
      <?php if ($products): ?>
        <div class="table-wrap"><table>
          <thead><tr><th></th><th>Product</th><th>Category</th><th>From</th><th>Options</th><th>Visible</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($products as $p): ?>
            <tr>
              <td><img class="thumb" src="<?= e(img_url($p['image'])) ?>" alt=""></td>
              <td><b><?= e($p['name']) ?></b></td>
              <td><?= e($p['category_name'] ?? '-') ?></td>
              <td><?= money($p['price']) ?></td>
              <td><?= (int) $p['variant_count'] ?></td>
              <td><?= $p['active'] ? 'Yes' : 'No' ?></td>
              <td class="actions"><a class="btn btn-sm btn-light" href="product-edit.php?id=<?= (int) $p['id'] ?>">Edit</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php else: ?>
        <p class="muted">No Printful products yet. Create products in Printful (in a store of type <b>“Manual order platform / API”</b>) with a retail price, then click <b>Sync products now</b>.</p>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div class="card">
      <div class="card-head"><h2>How it works</h2></div>
      <ol class="steps-list">
        <li>In Printful, create a store of type <b>Manual order platform / API</b> and design your products there. Set a <b>retail price</b> on every product.</li>
        <li>Create an API token and paste it here, then click <b>Save</b>.</li>
        <li>Click <b>Sync products now</b>. Products appear in your store with mockups, sizes, colors and prices. Sync again whenever you change something in Printful.</li>
        <li>When a customer buys a Printful product, the order is sent to Printful (automatically or with the <b>Send to Printful</b> button on the order page). Printful prints and ships it to your customer.</li>
      </ol>
      <p class="help">Imported products keep your edits to description, category, home page sections and old price. Name, images, sizes, colors and prices always come from Printful. Prices are used as they are in Printful, so set your Printful store currency to the same currency as this store (<?= e(setting('currency_code', 'USD')) ?>).</p>
    </div>
    <div class="card">
      <div class="card-head"><h2>Shipping updates</h2><?= setting_on('printful_webhook_on') ? '<span class="status st-delivered">On</span>' : '' ?></div>
      <p class="muted">Let Printful tell your store when an order ships. The order is marked <b>Shipped</b> and the tracking link is shown to the customer on the Track Order page.</p>
      <?php if (!$https): ?><div class="alert alert-warn">Your store must use <b>https://</b> for this to work.</div><?php endif; ?>
      <?php if (printful_connected()): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="webhook"><button class="btn btn-light btn-block" type="submit">Turn on automatic shipping updates</button></form>
      <?php endif; ?>
      <p class="help" style="margin-top:10px">You can also click <b>Refresh from Printful</b> on any order page.</p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

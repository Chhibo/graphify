<?php
require __DIR__ . '/includes/auth.php';
require_admin();

/** Clean a list of links coming from the editor. */
function clean_links($list, bool $withType = false): array
{
    $out = [];
    foreach (is_array($list) ? $list : [] as $l) {
        $label = mb_substr(trim((string) ($l['label'] ?? '')), 0, 60);
        if ($label === '') {
            continue;
        }
        $item = ['label' => $label, 'url' => mb_substr(trim((string) ($l['url'] ?? '')), 0, 300)];
        if ($withType) {
            $item['type'] = in_array($l['type'] ?? '', ['categories', 'brands'], true) ? $l['type'] : 'link';
            $item['children'] = clean_links($l['children'] ?? []);
        }
        $out[] = $item;
    }
    return $out;
}

if (is_post()) {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'reset') {
        foreach (['header', 'topbar', 'footer'] as $m) {
            set_setting('menu_' . $m, '');
        }
        flash('success', 'Menus restored to the default links.');
        redirect('admin/menus.php');
    }
    $header = clean_links(json_decode((string) ($_POST['menu_header'] ?? ''), true), true);
    $topbar = clean_links(json_decode((string) ($_POST['menu_topbar'] ?? ''), true));
    $footer = [];
    foreach ((array) json_decode((string) ($_POST['menu_footer'] ?? ''), true) as $col) {
        $title = mb_substr(trim((string) ($col['title'] ?? '')), 0, 60);
        $links = clean_links($col['links'] ?? []);
        if ($title !== '' || $links) {
            $footer[] = ['title' => $title, 'links' => $links];
        }
    }
    set_setting('menu_header', json_encode($header));
    set_setting('menu_topbar', json_encode($topbar));
    set_setting('menu_footer', json_encode(array_slice($footer, 0, 6)));
    set_setting('store_tagline', trim((string) ($_POST['store_tagline'] ?? '')));
    set_setting('footer_copyright', trim((string) ($_POST['footer_copyright'] ?? '')));
    flash('success', 'Menus saved.');
    redirect('admin/menus.php');
}

// Suggestions for the link fields
$suggest = ['' => 'Home', 'shop.php' => 'Shop - all products', 'shop.php?sort=new' => 'New arrivals', 'shop.php?sale=1' => 'On sale',
    'blog.php' => 'Blog', 'contact.php' => 'Contact page', 'track.php' => 'Track order', 'cart.php' => 'Cart', 'wishlist.php' => 'Wishlist', 'checkout.php' => 'Checkout'];
foreach (categories() as $c) {
    $suggest['shop.php?category=' . (int) $c['id']] = 'Category: ' . $c['name'];
}
foreach (q_all('SELECT title, slug, type FROM pages WHERE active = 1 ORDER BY type, title') as $pg) {
    $suggest['page.php?slug=' . $pg['slug']] = ($pg['type'] === 'post' ? 'Blog post: ' : 'Page: ') . $pg['title'];
}

$link_row = function (array $l = [], string $extra = '') {
    return '<div class="row-item" data-link>'
        . '<input data-f="label" value="' . e($l['label'] ?? '') . '" placeholder="Text">'
        . '<input data-f="url" class="grow" list="link-suggest" value="' . e($l['url'] ?? '') . '" placeholder="Link, e.g. shop.php or https://...">'
        . $extra
        . '<button type="button" class="icon-x" data-move="up" title="Move up">↑</button>'
        . '<button type="button" class="icon-x" data-move="down" title="Move down">↓</button>'
        . '<button type="button" class="icon-x" data-remove-row title="Remove">×</button></div>';
};

$adminTitle = 'Menus';
include __DIR__ . '/includes/header.php';
?>
<datalist id="link-suggest"><?php foreach ($suggest as $u => $label): ?><option value="<?= e($u) ?>"><?= e($label) ?></option><?php endforeach; ?></datalist>

<form method="post" id="menus-form">
  <?= csrf_field() ?>
  <input type="hidden" name="menu_header"><input type="hidden" name="menu_topbar"><input type="hidden" name="menu_footer">

  <div class="grid-main">
    <div>
      <div class="card">
        <div class="card-head"><h2>Header menu</h2><button type="button" class="btn btn-sm btn-light" data-add-row="#header-menu"><?= icon('plus', 14) ?> Add menu item</button></div>
        <p class="help">The black menu bar. Type <b>Categories dropdown</b> or <b>Brands dropdown</b> lists them automatically. Add sub-links to make your own dropdown. Leave the link empty for the home page.</p>
        <div id="header-menu" class="rows">
          <?php foreach (menu('header') ?: [['label' => '', 'url' => '']] as $m): ?>
            <div class="menu-block row-item" data-item>
              <input data-f="label" value="<?= e($m['label'] ?? '') ?>" placeholder="Text">
              <input data-f="url" class="grow" list="link-suggest" value="<?= e($m['url'] ?? '') ?>" placeholder="Link">
              <select data-f="type">
                <?php foreach (['link' => 'Normal link', 'categories' => 'Categories dropdown', 'brands' => 'Brands dropdown'] as $k => $l): ?>
                  <option value="<?= $k ?>" <?= ($m['type'] ?? 'link') === $k ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
              <button type="button" class="icon-x" data-move="up" title="Move up">↑</button>
              <button type="button" class="icon-x" data-move="down" title="Move down">↓</button>
              <button type="button" class="icon-x" data-remove-row title="Remove">×</button>
              <div class="sub-links rows" data-children>
                <?php foreach ((array) ($m['children'] ?? []) as $c): ?><?= $link_row($c) ?><?php endforeach; ?>
                <?= !empty($m['children']) ? '' : $link_row() ?>
              </div>
              <button type="button" class="btn btn-sm btn-light" data-add-sub>+ Sub-link (dropdown)</button>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2>Footer columns</h2><button type="button" class="btn btn-sm btn-light" data-add-row="#footer-menu"><?= icon('plus', 14) ?> Add column</button></div>
        <div id="footer-menu" class="rows">
          <?php foreach (menu('footer') ?: [['title' => '', 'links' => []]] as $col): ?>
            <div class="menu-block row-item" data-col>
              <input data-f="title" class="grow" value="<?= e($col['title'] ?? '') ?>" placeholder="Column title (e.g. Help)">
              <button type="button" class="icon-x" data-move="up" title="Move left">↑</button>
              <button type="button" class="icon-x" data-move="down" title="Move right">↓</button>
              <button type="button" class="icon-x" data-remove-row title="Remove column">×</button>
              <div class="sub-links rows" data-children>
                <?php foreach ((array) ($col['links'] ?? []) as $l): ?><?= $link_row($l) ?><?php endforeach; ?>
                <?= !empty($col['links']) ? '' : $link_row() ?>
              </div>
              <button type="button" class="btn btn-sm btn-light" data-add-sub>+ Add link</button>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div>
      <div class="card">
        <div class="card-head"><h2>Top bar links</h2><button type="button" class="btn btn-sm btn-light" data-add-row="#topbar-menu"><?= icon('plus', 14) ?> Add</button></div>
        <p class="help">Small links on the left of the red top bar.</p>
        <div id="topbar-menu" class="rows">
          <?php foreach (menu('topbar') ?: [[]] as $l): ?><?= $link_row($l) ?><?php endforeach; ?>
        </div>
      </div>
      <div class="card">
        <div class="card-head"><h2>Footer text</h2></div>
        <label>Text under the logo<textarea name="store_tagline" rows="3"><?= e(setting('store_tagline')) ?></textarea></label>
        <label>Copyright line <small class="muted">({store} = store name, {year} = current year)</small><input name="footer_copyright" value="<?= e(setting('footer_copyright', '{store} © {year}, All Rights Reserved')) ?>"></label>
        <p class="help">Social links: <a href="settings.php">Settings → General</a>.</p>
      </div>
      <div class="card">
        <button class="btn btn-primary btn-block" type="submit">Save menus</button>
        <button class="btn btn-light btn-block" type="submit" name="action" value="reset" formnovalidate onclick="return confirm('Restore all menus to the default links?')">Restore default menus</button>
      </div>
    </div>
  </div>
</form>

<script>
(function () {
  var form = document.getElementById('menus-form');
  var blank = <?= json_encode($link_row()) ?>;
  // "+ Sub-link" adds an empty link row inside the block
  form.addEventListener('click', function (e) {
    var b = e.target.closest('[data-add-sub]');
    if (!b) return;
    var box = b.parentNode.querySelector('[data-children]');
    box.insertAdjacentHTML('beforeend', blank);
    box.lastElementChild.querySelector('input').focus();
  });
  var val = function (el, f) { var i = el.querySelector(':scope > [data-f="' + f + '"]'); return i ? i.value : ''; };
  var links = function (box) {
    return Array.prototype.map.call(box.querySelectorAll(':scope > [data-link]'), function (r) { return { label: val(r, 'label'), url: val(r, 'url') }; });
  };
  // Collect everything into JSON when saving
  form.addEventListener('submit', function () {
    form.menu_header.value = JSON.stringify(Array.prototype.map.call(document.querySelectorAll('#header-menu > [data-item]'), function (it) {
      return { label: val(it, 'label'), url: val(it, 'url'), type: val(it, 'type'), children: links(it.querySelector('[data-children]')) };
    }));
    form.menu_topbar.value = JSON.stringify(links(document.getElementById('topbar-menu')));
    form.menu_footer.value = JSON.stringify(Array.prototype.map.call(document.querySelectorAll('#footer-menu > [data-col]'), function (c) {
      return { title: val(c, 'title'), links: links(c.querySelector('[data-children]')) };
    }));
  });
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>

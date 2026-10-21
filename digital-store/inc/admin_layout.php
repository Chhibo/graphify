<?php
declare(strict_types=1);

/**
 * $actions: extra HTML (buttons) shown at the top right, e.g. "+ Add product".
 */
function admin_header(string $title, string $subtitle = '', string $actions = ''): void
{
    $unread = unread_messages();
    $groups = [
        'Store' => [
            'index.php' => ['Dashboard', 'dashboard'],
            'products.php' => ['Products', 'package'],
            'categories.php' => ['Categories', 'folder'],
            'pages.php' => ['Pages', 'file'],
            'messages.php' => ['Messages', 'mail'],
        ],
        'Money' => [
            'networks.php' => ['CPA Networks', 'plug'],
            'postbacks.php' => ['Postback log', 'activity'],
        ],
        'Setup' => [
            'appearance.php' => ['Appearance', 'palette'],
            'settings.php' => ['Settings', 'sliders'],
        ],
    ];
    $current = basename($_SERVER['SCRIPT_NAME']);
    // Edit pages highlight their list page.
    $current = ['product_edit.php' => 'products.php', 'page_edit.php' => 'pages.php'][$current] ?? $current;
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> · Admin</title>
<link rel="stylesheet" href="<?= e(base_url('assets/admin.css')) ?>?v=3">
<?php if (valid_color(setting('color_accent'))): ?><style>:root{--accent:<?= setting('color_accent') ?>}</style><?php endif; ?>
</head>
<body>
<aside class="sidebar" id="sidebar">
  <a class="side-brand" href="<?= e(base_url('admin/')) ?>"><span class="mark"><?= icon('gift', 18) ?></span><span><?= e(setting('store_name')) ?></span></a>
  <nav class="side-nav">
    <?php foreach ($groups as $group => $items): ?>
      <div class="side-group"><?= e($group) ?></div>
      <?php foreach ($items as $file => [$label, $ico]): ?>
        <a href="<?= e($file) ?>" class="<?= $current === $file ? 'active' : '' ?>">
          <?= icon($ico) ?> <?= e($label) ?>
          <?php if ($file === 'messages.php' && $unread): ?><span class="count"><?= $unread ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
  <nav class="side-nav side-bottom">
    <a href="<?= e(base_url()) ?>" target="_blank"><?= icon('external') ?> View store</a>
    <a href="logout.php"><?= icon('logout') ?> Log out</a>
  </nav>
</aside>
<div class="side-backdrop" onclick="document.body.classList.remove('side-open')"></div>
<div class="admin-wrap">
  <header class="topbar">
    <button class="icon-btn side-toggle" type="button" aria-label="Menu" onclick="document.body.classList.toggle('side-open')"><?= icon('menu') ?></button>
    <div>
      <h1><?= e($title) ?></h1>
      <?php if ($subtitle !== ''): ?><p class="sub"><?= $subtitle ?></p><?php endif; ?>
    </div>
    <div class="topbar-actions">
      <?= $actions ?>
      <a class="btn btn-light hide-sm" href="<?= e(base_url()) ?>" target="_blank"><?= icon('external', 16) ?> Store</a>
    </div>
  </header>
  <main class="admin-main">
    <?php if ($m = flash()): ?><div class="toast" role="status"><?= icon('circle-check') ?> <?= e($m) ?></div><?php endif; ?>
<?php
}

function unread_messages(): int
{
    return (int)db()->query('SELECT COUNT(*) FROM messages WHERE is_read = 0')->fetchColumn();
}

function admin_footer(): void
{
    echo "  </main>\n</div>\n</body>\n</html>\n";
}

/** A highlighted message with an optional link on the right. $text is HTML. */
function notice(string $text, string $linkUrl = '', string $linkText = '', string $type = 'warn', string $ico = 'megaphone'): string
{
    $link = $linkUrl !== '' ? '<a href="' . e($linkUrl) . '">' . e($linkText) . ' →</a>' : '';
    return '<div class="notice notice-' . $type . '">' . icon($ico) . ' <span>' . $text . '</span>' . $link . '</div>';
}

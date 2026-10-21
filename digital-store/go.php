<?php
// Public page of a locked link: visitors complete an offer to open it.
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_installed();

$code = (string)($_GET['c'] ?? '');
// Lets Settings check that readable links work on this host.
if ($code === '__rewrite_test') {
    header('Content-Type: text/plain');
    exit('rewrite-ok');
}
$stmt = db()->prepare('SELECT * FROM links WHERE code = ? AND active = 1');
$stmt->execute([$code]);
$link = $stmt->fetch();
if (!$link) {
    http_response_code(404);
    page_header('Not found');
    echo empty_state('This link does not exist or was removed. <a href="' . e(base_url()) . '">Visit the store</a>', 'link');
    page_footer();
    exit;
}
if (!is_admin() && empty($_SESSION['viewed_link'][$link['id']])) {
    $_SESSION['viewed_link'][$link['id']] = 1;
    db()->prepare('UPDATE links SET views = views + 1 WHERE id = ?')->execute([$link['id']]);
}
$image = $link['image'] !== '' ? base_url('uploads/' . $link['image']) : '';

page_header($link['title'], '', ['description' => $link['description'] !== '' ? $link['description'] : 'Unlock this link for free.', 'image' => $image ?: null]);
?>
<section class="link-lock">
  <div class="card link-card">
    <?php if ($image !== ''): ?><img class="link-cover" src="<?= e($image) ?>" alt=""><?php else: ?><span class="link-icon"><?= icon('unlock', 34) ?></span><?php endif; ?>
    <span class="hero-kicker-dark"><?= icon('link', 14) ?> Locked link</span>
    <h1><?= e($link['title']) ?></h1>
    <?php if ($link['description'] !== ''): ?><div class="description"><?= render_content($link['description']) ?></div><?php endif; ?>
    <button class="btn btn-big btn-glow" id="unlock-btn" data-link="<?= (int)$link['id'] ?>"><?= icon('unlock', 20) ?> Unlock link</button>
    <p class="muted link-note"><?= icon('shield', 14) ?> Complete one quick offer and the link opens automatically.</p>
  </div>
</section>
<?= locker_modal($link['title'], $image, 'Complete one offer to unlock this link') ?>
<?php page_footer();

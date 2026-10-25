<?php
require __DIR__ . '/includes/bootstrap.php';

$tree = category_tree();
$counts = category_counts();
$pageTitle = __('All categories');
require __DIR__ . '/includes/header.php';
?>
<section class="container section">
  <div class="page-head">
    <nav class="breadcrumb"><a href="<?= e(url()) ?>"><?= t('Home') ?></a><?= icon('chevron-right') ?><span><?= t('Categories') ?></span></nav>
    <h1><?= t('All categories') ?></h1>
  </div>
  <div class="cat-directory">
    <?php foreach ($tree as $id => $c): ?>
      <div class="card cat-block">
        <a class="cat-block-head" href="<?= e(category_url((int) $id)) ?>">
          <span class="cat-icon"><?= icon($c['icon']) ?></span>
          <span><strong><?= e($c['name']) ?></strong><small><?= e(n_('{n} ad', '{n} ads', $counts[$id] ?? 0)) ?></small></span>
        </a>
        <?php if ($c['children']): ?>
          <ul>
            <?php foreach ($c['children'] as $cid => $ch): ?>
              <li><a href="<?= e(category_url((int) $cid)) ?>"><?= e($ch['name']) ?><span><?= number_format($counts[$cid] ?? 0) ?></span></a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';

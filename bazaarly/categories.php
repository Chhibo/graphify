<?php
require __DIR__ . '/includes/bootstrap.php';

$tree = category_tree();
$counts = category_counts();
$pageTitle = 'All categories';
require __DIR__ . '/includes/header.php';
?>
<section class="container section">
  <div class="page-head">
    <nav class="breadcrumb"><a href="<?= e(url()) ?>">Home</a><?= icon('chevron-right') ?><span>Categories</span></nav>
    <h1>All categories</h1>
  </div>
  <div class="cat-directory">
    <?php foreach ($tree as $id => $c): ?>
      <div class="card cat-block">
        <a class="cat-block-head" href="<?= e(url('listings.php?category=' . $id)) ?>">
          <span class="cat-icon"><?= icon($c['icon']) ?></span>
          <span><strong><?= e($c['name']) ?></strong><small><?= ($n = $counts[$id] ?? 0) === 1 ? "1 ad" : number_format($n) . " ads" ?></small></span>
        </a>
        <?php if ($c['children']): ?>
          <ul>
            <?php foreach ($c['children'] as $cid => $ch): ?>
              <li><a href="<?= e(url('listings.php?category=' . $cid)) ?>"><?= e($ch['name']) ?><span><?= number_format($counts[$cid] ?? 0) ?></span></a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';

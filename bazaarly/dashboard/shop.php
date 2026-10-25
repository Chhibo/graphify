<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_login();
$uid = (int) $me['id'];
if (!shops_enabled()) {
    flash('info', __('Shop pages are turned off on this site.'));
    redirect('dashboard/');
}
$shop = q_one('SELECT * FROM shops WHERE user_id = ?', [$uid]);
$errors = [];
$d = $shop ?? ['name' => '', 'slug' => '', 'tagline' => '', 'description' => '', 'logo' => '', 'banner' => '', 'phone' => $me['phone'],
    'email' => $me['email'], 'website' => '', 'address' => $me['location'], 'hours' => '', 'status' => 'new', 'reject_reason' => ''];
$hours = shop_hours($d);

if (is_post()) {
    if (input('action') === 'delete' && $shop) {
        delete_upload($shop['logo']);
        delete_upload($shop['banner']);
        q('DELETE FROM shops WHERE id = ?', [$shop['id']]);
        flash('success', __('Your shop page was deleted. Your ads are still online.'));
        redirect('dashboard/shop.php');
    }
    require_verified_email('dashboard/shop.php');
    $d = array_merge($d, [
        'name' => mb_substr(input('name'), 0, 120),
        'tagline' => mb_substr(input('tagline'), 0, 190),
        'description' => mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 5000),
        'phone' => mb_substr(input('phone'), 0, 40),
        'email' => mb_substr(strtolower(input('email')), 0, 191),
        'website' => mb_substr(input('website'), 0, 255),
        'address' => mb_substr(input('address'), 0, 255),
    ]);
    $hoursIn = [];
    foreach (WEEK_DAYS as $k => $_) {
        $open = preg_match('/^\d{2}:\d{2}$/', input("open_$k")) ? input("open_$k") : '';
        $close = preg_match('/^\d{2}:\d{2}$/', input("close_$k")) ? input("close_$k") : '';
        $hoursIn[$k] = ['closed' => !empty($_POST["closed_$k"]), 'open' => $open, 'close' => $close];
    }
    $d['hours'] = json_encode($hoursIn);
    $hours = shop_hours($d);
    if (mb_strlen($d['name']) < 2) $errors['name'] = __('Enter your business name.');
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = __('Enter a valid email address.');
    if ($d['website'] !== '' && !preg_match('~^https?://~i', $d['website'])) $d['website'] = 'https://' . $d['website'];
    if ($d['website'] !== '' && !filter_var($d['website'], FILTER_VALIDATE_URL)) $errors['website'] = __('Enter a valid website address.');
    if (($bad = find_banned_word($d['name'], $d['tagline'], (string) $d['description'])) !== null) $errors['name'] = __('Your shop contains a word or phrase that is not allowed (“{word}”).', ['word' => $bad]);
    $wantSlug = slugify(input('slug') ?: $d['name']);
    $slug = $wantSlug;
    $n = 2;
    while (q_val('SELECT id FROM shops WHERE slug = ? AND user_id <> ?', [$slug, $uid])) {
        $slug = $wantSlug . '-' . $n++;
    }
    $d['slug'] = $slug;
    foreach (['logo' => [400, 200], 'banner' => [1600, 800]] as $field => [$w, $tw]) {
        $file = normalize_files($_FILES[$field] ?? null)[0] ?? null;
        if (!$errors && $file) {
            $res = store_image($file, 'shops', $w, $tw);
            if (is_string($res)) {
                $errors[$field] = $res;
            } else {
                delete_upload($shop[$field] ?? '');
                if ($res['thumb'] !== $res['path']) {
                    delete_upload($field === 'logo' ? $res['path'] : $res['thumb']);
                }
                $d[$field] = $field === 'logo' ? $res['thumb'] : $res['path'];
            }
        }
        if (!empty($_POST['remove_' . $field])) {
            delete_upload($shop[$field] ?? '');
            $d[$field] = '';
        }
    }
    if (!$errors) {
        $needsApproval = setting('shops_require_approval') === '1' && $me['role'] !== 'admin';
        $data = array_intersect_key($d, array_flip(['name', 'slug', 'tagline', 'description', 'logo', 'banner', 'phone', 'email', 'website', 'address', 'hours']));
        $data['updated_at'] = now();
        if ($shop) {
            if ($shop['status'] === 'rejected') {
                $data['status'] = $needsApproval ? 'pending' : 'active';
                $data['reject_reason'] = '';
            }
            db_update('shops', $data, 'id = ?', [$shop['id']]);
            flash('success', ($data['status'] ?? $shop['status']) === 'pending' ? __('Shop saved and sent for review.') : __('Your shop page has been updated.'));
        } else {
            db_insert('shops', $data + ['user_id' => $uid, 'status' => $needsApproval ? 'pending' : 'active', 'reject_reason' => '', 'created_at' => now()]);
            flash('success', $needsApproval ? __('Your shop was created and will be visible once our team approves it.') : __('Your shop page is live!'));
        }
        redirect('dashboard/shop.php');
    }
}
$err = fn(string $k) => isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';
$cls = fn(string $k) => isset($errors[$k]) ? ' has-error' : '';

$dashActive = 'shop';
$pageTitle = __('My shop');
require APP_ROOT . '/includes/dash-top.php';
?>
<div class="dash-head">
  <div><h1><?= t('My shop') ?></h1><p class="muted"><?= t('A shop page shows your business name, logo, opening hours and all your ads in one place, with a “Business” badge on your ads.') ?></p></div>
  <?php if ($shop && $shop['status'] === 'active'): ?><a class="btn btn-ghost" href="<?= e(shop_url($shop)) ?>" target="_blank"><?= icon('external') ?><?= t('View my shop') ?></a><?php endif; ?>
</div>

<?php if ($shop): ?>
  <?php if ($shop['status'] === 'pending'): ?><div class="alert alert-info"><?= icon('clock') ?><span><?= t('Your shop is waiting for approval. We will email you when it is live.') ?></span></div><?php endif; ?>
  <?php if ($shop['status'] === 'rejected'): ?><div class="alert alert-error"><?= icon('alert') ?><span><?= t('Your shop was not approved.') ?> <?= e($shop['reject_reason']) ?> <?= t('Update it and save to send it again.') ?></span></div><?php endif; ?>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <section class="card form-section">
    <h2><?= icon('shop') ?><?= t('Business details') ?></h2>
    <div class="shop-media-edit">
      <div class="field<?= $cls('banner') ?>"><label><?= t('Cover image') ?> <small class="muted">(1600×500)</small></label>
        <div class="banner-preview" <?= $d['banner'] ? 'style="background-image:url(\'' . e(upload_url($d['banner'])) . '\')"' : '' ?>></div>
        <input type="file" name="banner" accept="image/jpeg,image/png,image/webp"><?php if ($d['banner']): ?><label class="check small"><input type="checkbox" name="remove_banner" value="1"><span><?= t('Remove') ?></span></label><?php endif; ?><?= $err('banner') ?></div>
      <div class="field<?= $cls('logo') ?>"><label><?= t('Logo') ?> <small class="muted">(<?= t('square') ?>)</small></label>
        <?php if ($d['logo']): ?><img class="logo-preview" src="<?= e(upload_url($d['logo'])) ?>" alt=""><?php else: ?><span class="logo-preview shop-logo-ph"><?= icon('shop') ?></span><?php endif; ?>
        <input type="file" name="logo" accept="image/jpeg,image/png,image/webp"><?php if ($d['logo']): ?><label class="check small"><input type="checkbox" name="remove_logo" value="1"><span><?= t('Remove') ?></span></label><?php endif; ?><?= $err('logo') ?></div>
    </div>
    <div class="form-grid">
      <div class="field<?= $cls('name') ?>"><label for="s-name"><?= t('Business name') ?></label><input id="s-name" name="name" value="<?= e($d['name']) ?>" required maxlength="120"><?= $err('name') ?></div>
      <div class="field"><label for="s-slug"><?= t('Shop address on this site') ?></label><div class="input-prefix"><span><?= e(clean_urls() ? '/shop/' : 'shop.php?slug=') ?></span><input id="s-slug" name="slug" value="<?= e($d['slug']) ?>" placeholder="<?= t('auto') ?>"></div></div>
    </div>
    <div class="field"><label for="s-tag"><?= t('Tagline') ?></label><input id="s-tag" name="tagline" value="<?= e($d['tagline']) ?>" maxlength="190" placeholder="<?= t('e.g. Certified used cars since 2010') ?>"></div>
    <div class="field"><label for="s-desc"><?= t('About your business') ?></label><textarea id="s-desc" name="description" rows="5" maxlength="5000"><?= e((string) $d['description']) ?></textarea></div>
  </section>

  <section class="card form-section">
    <h2><?= icon('phone') ?><?= t('Contact') ?></h2>
    <div class="form-grid">
      <div class="field"><label for="s-phone"><?= t('Phone') ?></label><input id="s-phone" name="phone" type="tel" value="<?= e($d['phone']) ?>"></div>
      <div class="field<?= $cls('email') ?>"><label for="s-email"><?= t('Email') ?></label><input id="s-email" name="email" type="email" value="<?= e($d['email']) ?>"><?= $err('email') ?></div>
      <div class="field<?= $cls('website') ?>"><label for="s-web"><?= t('Website') ?></label><input id="s-web" name="website" value="<?= e($d['website']) ?>" placeholder="https://"><?= $err('website') ?></div>
      <div class="field"><label for="s-addr"><?= t('Address') ?></label><input id="s-addr" name="address" value="<?= e($d['address']) ?>"></div>
    </div>
  </section>

  <section class="card form-section">
    <h2><?= icon('clock') ?><?= t('Opening hours') ?> <small class="muted"><?= t('optional') ?></small></h2>
    <div class="hours-edit">
      <?php foreach (WEEK_DAYS as $k => $dayName): $h = $hours[$k]; ?>
        <div class="hours-row" data-hours-row>
          <strong><?= t($dayName) ?></strong>
          <input type="time" name="open_<?= $k ?>" value="<?= e($h['open']) ?>" aria-label="<?= t('Opens') ?>" <?= $h['closed'] ? 'disabled' : '' ?>>
          <span>–</span>
          <input type="time" name="close_<?= $k ?>" value="<?= e($h['close']) ?>" aria-label="<?= t('Closes') ?>" <?= $h['closed'] ? 'disabled' : '' ?>>
          <label class="check small"><input type="checkbox" name="closed_<?= $k ?>" value="1" <?= $h['closed'] ? 'checked' : '' ?> data-closed-toggle><span><?= t('Closed') ?></span></label>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-ghost btn-sm" data-copy-hours><?= icon('copy') ?><?= t('Copy Monday to all weekdays') ?></button>
  </section>
  <div class="btn-row"><button class="btn btn-primary btn-lg" type="submit"><?= icon('check') ?><?= $shop ? t('Save shop') : t('Create my shop') ?></button></div>
</form>

<?php if ($shop): ?>
<form method="post" class="card form-section danger-zone" data-confirm="<?= t('Delete your shop page? Your ads stay online.') ?>">
  <?= csrf_field() ?><input type="hidden" name="action" value="delete">
  <h2><?= t('Delete shop page') ?></h2>
  <p class="muted"><?= t('Removes the shop page and the “Business” badge. Your ads are not deleted.') ?></p>
  <button class="btn btn-danger" type="submit"><?= icon('trash') ?><?= t('Delete shop page') ?></button>
</form>
<?php endif; ?>
<?php require APP_ROOT . '/includes/dash-bottom.php';

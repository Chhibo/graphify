<?php
/**
 * Shared create/edit logic for listings, used by dashboard/edit.php (owners)
 * and admin/listing-edit.php (administrators).
 */
declare(strict_types=1);

const PRICE_TYPES = ['fixed' => 'Fixed price', 'negotiable' => 'Negotiable', 'free' => 'Free', 'contact' => 'Ask for price'];
const CONDITIONS = ['' => 'Not applicable', 'new' => 'New', 'like_new' => 'Like new', 'used' => 'Used', 'refurbished' => 'Refurbished'];
const LISTING_STATUSES = ['active', 'pending', 'rejected', 'sold', 'expired'];

function listing_form_defaults(array $user): array
{
    return [
        'title' => '', 'category_id' => 0, 'description' => '', 'price' => '', 'price_type' => 'fixed',
        'item_condition' => '', 'location' => $user['location'] ?? '', 'location_id' => 0, 'phone' => $user['phone'] ?? '', 'show_phone' => 1,
        'tags' => '', 'status' => 'pending', 'featured' => 0, 'reject_reason' => '', 'video_url' => '',
    ];
}

/**
 * Validates and saves the posted form. Returns validation errors, or redirects on success.
 */
function listing_form_save(?array $listing, bool $asAdmin, string $successUrl): array
{
    $me = current_user();
    $errors = [];
    $d = [
        'title' => mb_substr(input('title'), 0, 150),
        'category_id' => (int) input('category_id'),
        'description' => mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 20000),
        'price_type' => array_key_exists(input('price_type'), PRICE_TYPES) ? input('price_type') : 'fixed',
        'item_condition' => array_key_exists(input('item_condition'), CONDITIONS) ? input('item_condition') : '',
        'location' => mb_substr(input('location'), 0, 150),
        'phone' => mb_substr(input('phone'), 0, 40),
        'show_phone' => empty($_POST['show_phone']) ? 0 : 1,
        'tags' => mb_substr(implode(', ', array_unique(array_filter(array_map(fn($t) => mb_strtolower(trim($t)), explode(',', input('tags')))))), 0, 255),
    ];
    $d['video_url'] = '';
    if (setting('enable_video', '1') === '1') {
        $d['video_url'] = normalize_video_url(input('video_url'));
        if (input('video_url') !== '' && $d['video_url'] === '') {
            $errors['video_url'] = __('Paste a YouTube or Vimeo link (e.g. https://youtu.be/…).');
        }
    }
    $rawPrice = str_replace([',', ' '], '', input('price'));
    $d['price'] = in_array($d['price_type'], ['fixed', 'negotiable'], true) ? (float) $rawPrice : 0;

    if (mb_strlen($d['title']) < 5) $errors['title'] = __('Give your ad a clear title (at least 5 characters).');
    if (!isset(categories_all()[$d['category_id']])) $errors['category_id'] = __('Please choose a category.');
    if (mb_strlen($d['description']) < 15) $errors['description'] = __('Describe your item in a bit more detail (at least 15 characters).');
    if (in_array($d['price_type'], ['fixed', 'negotiable'], true) && ($rawPrice === '' || !is_numeric($rawPrice) || $d['price'] < 0)) {
        $errors['price'] = __('Enter a valid price.');
    }
    if ($d['price'] > 999999999999) $errors['price'] = __('That price is too high.');

    // location from the admin-managed list
    if (location_list_mode()) {
        $d['location_id'] = (int) input('location_id');
        if (!isset(locations_all()[$d['location_id']])) {
            $errors['location_id'] = __('Please choose a location.');
            $d['location_id'] = 0;
        }
        $d['location'] = location_label($d['location_id']);
    } else {
        $d['location_id'] = 0;
    }

    // category-specific fields
    [$fieldValues, $fieldErrors] = clean_field_input($d['category_id'], (array) ($_POST['cf'] ?? []));
    $errors += $fieldErrors;

    $forceReview = false;
    if (!$asAdmin && $me['role'] !== 'admin') {
        if (!$listing) {
            $limit = (int) setting('max_ads_per_day', '20');
            if ($limit > 0 && (int) q_val('SELECT COUNT(*) FROM listings WHERE user_id = ? AND created_at > ?', [$me['id'], date('Y-m-d H:i:s', time() - 86400)]) >= $limit) {
                $errors['title'] = __('You\'ve reached the limit of {n} new ads per day. Please try again tomorrow.', ['n' => $limit]);
            }
            if (!captcha_passed('post')) {
                $errors['captcha'] = __('Please complete the security check.');
            }
        }
        $bad = find_banned_word($d['title'], $d['description'], $d['tags'], implode(' ', $fieldValues));
        if ($bad !== null) {
            if (setting('banned_words_action') === 'block') {
                $errors['title'] = __('Your ad contains a word or phrase that is not allowed (“{word}”). Please remove it.', ['word' => $bad]);
            } else {
                $forceReview = true;
            }
        }
    }

    if ($asAdmin) {
        $status = input('status');
        $d['status'] = in_array($status, LISTING_STATUSES, true) ? $status : 'active';
        $d['featured'] = empty($_POST['featured']) ? 0 : 1;
        $d['reject_reason'] = mb_substr(input('reject_reason'), 0, 255);
        $exp = input('expires_at');
        $d['expires_at'] = $exp !== '' && strtotime($exp) ? date('Y-m-d H:i:s', strtotime($exp . ' 23:59:59')) : null;
    }

    if ($errors) {
        return $errors;
    }

    $needsApproval = (setting('require_approval') === '1' || $forceReview) && !$asAdmin && $me['role'] !== 'admin';
    $d['updated_at'] = now();

    if ($listing) {
        $id = (int) $listing['id'];
        if (!$asAdmin) {
            if ($listing['status'] === 'sold') {
                // keep sold
            } elseif ($needsApproval) {
                $d['status'] = 'pending';
            } elseif (in_array($listing['status'], ['rejected', 'pending'], true)) {
                $d['status'] = 'active';
            }
            if (isset($d['status']) && $d['status'] !== $listing['status']) {
                $d['reject_reason'] = '';
            }
        } elseif ($d['status'] === 'active' && $listing['status'] === 'expired' && (!$d['expires_at'] || $d['expires_at'] <= now())) {
            $d['expires_at'] = new_expiry();
        }
        db_update('listings', $d, 'id = ?', [$id]);
        handle_price_change($listing, (float) $d['price'], $d['price_type']);
        if ($asAdmin) {
            log_activity('listing.edit', $d['title'] . ' (status: ' . $d['status'] . ')', 'listing', $id);
            if ($d['status'] !== $listing['status']) {
                notify_listing_status($id, $d['status'], $d['reject_reason']);
            }
        }
    } else {
        $d += [
            'user_id' => $me['id'],
            'status' => $needsApproval ? 'pending' : 'active',
            'featured' => 0,
            'views' => 0,
            'created_at' => now(),
            'expires_at' => new_expiry(),
        ];
        $id = db_insert('listings', $d);
    }
    save_listing_fields($id, $fieldValues);

    // ---- images
    $existing = q_all('SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id', [$id]);
    $remove = array_map('intval', (array) ($_POST['remove_images'] ?? []));
    foreach ($existing as $k => $img) {
        if (in_array((int) $img['id'], $remove, true)) {
            delete_upload($img['path']);
            delete_upload($img['thumb']);
            q('DELETE FROM listing_images WHERE id = ?', [$img['id']]);
            unset($existing[$k]);
        }
    }
    $max = max(1, (int) setting('max_images', '8'));
    $room = $max - count($existing);
    $files = normalize_files($_FILES['images'] ?? null);
    $uploadErrors = [];
    if (count($files) > $room) {
        $uploadErrors[] = __('Only {n} photos are allowed per ad; extra photos were skipped.', ['n' => $max]);
        $files = array_slice($files, 0, max(0, $room));
    }
    $order = count($existing) + 1;
    foreach ($files as $f) {
        $res = store_image($f, 'listings', 1600, 520, false, true);
        if (is_string($res)) {
            $uploadErrors[] = $res;
            continue;
        }
        db_insert('listing_images', ['listing_id' => $id, 'path' => $res['path'], 'thumb' => $res['thumb'], 'phash' => $res['phash'] ?? '', 'sort_order' => $order++]);
    }
    $cover = (int) input('cover');
    if ($cover) {
        $i = 1;
        foreach (q_all('SELECT id FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id', [$id]) as $img) {
            q('UPDATE listing_images SET sort_order = ? WHERE id = ?', [(int) $img['id'] === $cover ? 0 : $i++, $img['id']]);
        }
    }

    // duplicate ads (same title / same photos as another live ad)
    if (!$asAdmin && $me['role'] !== 'admin' && check_duplicates($id)) {
        q("UPDATE listings SET status = 'pending' WHERE id = ? AND status = 'active'", [$id]);
        flash('info', __('This ad looks very similar to another ad, so our team will check it before it goes live.'));
    }
    foreach ($uploadErrors as $ue) {
        flash('warning', $ue);
    }
    $status = q_val('SELECT status FROM listings WHERE id = ?', [$id]);
    if ($listing) {
        flash('success', $status === 'pending' && !$asAdmin ? __('Ad updated and sent for review.') : __('Ad updated successfully.'));
    } else {
        flash('success', $status === 'pending' ? __('Your ad was submitted and will go live once approved.') : __('Your ad is live! 🎉'));
    }
    if ($forceReview && $status === 'pending') {
        flash('info', __('Your ad will be checked by our team before it goes live.'));
    }
    redirect(str_replace('{id}', (string) $id, $successUrl));
}

function render_listing_form(array $d, array $images, array $errors, bool $asAdmin, string $cancelUrl, array $fieldValues = []): void
{
    $err = fn(string $k) => isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';
    $cls = fn(string $k) => isset($errors[$k]) ? ' has-error' : '';
    $max = (int) setting('max_images', '8');
    $isEdit = !empty($d['id']);
    ?>
    <?php if ($errors): ?><div class="alert alert-error"><?= icon('alert') ?><span><?= t('Please fix the highlighted fields below.') ?></span></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="listing-form" data-listing-form>
      <?= csrf_field() ?>
      <div class="form-main">
        <section class="card form-section">
          <h2><span class="step-num">1</span><?= t('What are you selling?') ?></h2>
          <div class="field<?= $cls('title') ?>">
            <label for="lf-title"><?= t('Ad title') ?></label>
            <input id="lf-title" name="title" maxlength="150" value="<?= e($d['title']) ?>" placeholder="<?= t('e.g. iPhone 14 Pro, 128GB, excellent condition') ?>" required data-count>
            <?= $err('title') ?>
          </div>
          <div class="form-grid">
            <div class="field<?= $cls('category_id') ?>">
              <label for="lf-cat"><?= t('Category') ?></label>
              <select id="lf-cat" name="category_id" required><option value=""><?= t('Choose a category') ?></option><?= category_options((int) $d['category_id'], false) ?></select>
              <?= $err('category_id') ?>
            </div>
            <div class="field">
              <label for="lf-cond"><?= t('Condition') ?></label>
              <select id="lf-cond" name="item_condition">
                <?php foreach (CONDITIONS as $k => $lbl): ?><option value="<?= e($k) ?>" <?= $d['item_condition'] === $k ? 'selected' : '' ?>><?= t($lbl) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <?php render_custom_field_inputs($fieldValues, $errors); ?>
          <div class="field<?= $cls('description') ?>">
            <label for="lf-desc"><?= t('Description') ?></label>
            <textarea id="lf-desc" name="description" rows="8" maxlength="20000" placeholder="<?= t('Include details buyers care about: brand, model, age, size, defects, reason for selling…') ?>" required><?= e($d['description']) ?></textarea>
            <?= $err('description') ?>
          </div>
          <div class="field">
            <label for="lf-tags"><?= t('Tags') ?> <small class="muted">(<?= t('comma separated, optional') ?>)</small></label>
            <input id="lf-tags" name="tags" value="<?= e($d['tags']) ?>" placeholder="<?= t('apple, smartphone, unlocked') ?>">
          </div>
        </section>

        <section class="card form-section">
          <h2><span class="step-num">2</span><?= t('Price') ?></h2>
          <div class="seg-radio">
            <?php foreach (PRICE_TYPES as $k => $lbl): ?>
              <label><input type="radio" name="price_type" value="<?= $k ?>" <?= $d['price_type'] === $k ? 'checked' : '' ?> data-price-type><span><?= t($lbl) ?></span></label>
            <?php endforeach; ?>
          </div>
          <div class="field price-field<?= $cls('price') ?>" data-price-field>
            <label for="lf-price"><?= t('Amount') ?></label>
            <div class="input-prefix"><span><?= e(setting('currency_symbol')) ?></span><input id="lf-price" name="price" inputmode="decimal" value="<?= e($d['price'] === '' ? '' : (string) (float) $d['price']) ?>" placeholder="0"></div>
            <?= $err('price') ?>
          </div>
        </section>

        <section class="card form-section">
          <h2><span class="step-num">3</span><?= t('Photos') ?> <small class="muted"><?= t('up to {n}', ['n' => $max]) ?></small></h2>
          <?php if ($images): ?>
            <p class="muted small"><?= t('Pick a cover photo and tick any photo you want to remove.') ?></p>
            <div class="image-manager">
              <?php foreach ($images as $i => $img): ?>
                <div class="im-item">
                  <img src="<?= e(upload_url($img['thumb'])) ?>" alt="">
                  <label class="im-cover" title="<?= t('Use as cover') ?>"><input type="radio" name="cover" value="<?= (int) $img['id'] ?>" <?= $i === 0 ? 'checked' : '' ?>><span><?= t('Cover') ?></span></label>
                  <label class="im-remove" title="<?= t('Remove photo') ?>"><input type="checkbox" name="remove_images[]" value="<?= (int) $img['id'] ?>"><?= icon('trash') ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <label class="dropzone" data-dropzone>
            <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple data-max="<?= max(0, $max - count($images)) ?>">
            <span class="dz-icon"><?= icon('upload') ?></span>
            <strong><?= t('Click to add photos or drag them here') ?></strong>
            <small><?= t('JPG, PNG, WEBP or GIF · max {mb} MB each · the first one becomes the cover', ['mb' => setting('max_image_mb', '5')]) ?></small>
          </label>
          <div class="upload-preview" data-preview></div>
          <?php if (setting('enable_video', '1') === '1'): ?>
          <div class="field<?= $cls('video_url') ?>">
            <label for="lf-video"><?= icon('play') ?> <?= __('Video') ?> <small class="muted"><?= __('(YouTube or Vimeo link, optional)') ?></small></label>
            <input id="lf-video" name="video_url" type="url" maxlength="255" value="<?= e($d['video_url'] ?? '') ?>" placeholder="https://www.youtube.com/watch?v=…">
            <?= $err('video_url') ?>
          </div>
          <?php endif; ?>
        </section>

        <section class="card form-section">
          <h2><span class="step-num">4</span><?= t('Location & contact') ?></h2>
          <div class="form-grid">
            <?php if (location_list_mode()): ?>
            <div class="field<?= $cls('location_id') ?>">
              <label for="lf-loc"><?= t('Location') ?></label>
              <select id="lf-loc" name="location_id" required><option value=""><?= t('Choose a location') ?></option><?= location_options((int) ($d['location_id'] ?? 0)) ?></select>
              <?= $err('location_id') ?>
            </div>
            <?php else: ?>
            <div class="field">
              <label for="lf-loc"><?= t('Location') ?></label>
              <div class="input-icon"><?= icon('map-pin') ?><input id="lf-loc" name="location" maxlength="150" value="<?= e($d['location']) ?>" placeholder="<?= t('City, area') ?>"></div>
            </div>
            <?php endif; ?>
            <div class="field">
              <label for="lf-phone"><?= t('Phone') ?></label>
              <div class="input-icon"><?= icon('phone') ?><input id="lf-phone" name="phone" type="tel" maxlength="40" value="<?= e($d['phone']) ?>" placeholder="<?= t('Optional') ?>"></div>
            </div>
          </div>
          <label class="switch"><input type="checkbox" name="show_phone" value="1" <?= $d['show_phone'] ? 'checked' : '' ?>><span class="switch-ui"></span><span><?= t('Show my phone number on this ad') ?></span></label>
        </section>
      </div>

      <aside class="form-side">
        <?php if ($asAdmin): ?>
          <section class="card form-section">
            <h2><?= t('Moderation') ?></h2>
            <div class="field"><label for="lf-status"><?= t('Status') ?></label>
              <select id="lf-status" name="status">
                <?php foreach (LISTING_STATUSES as $s): ?><option value="<?= $s ?>" <?= $d['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
              </select></div>
            <div class="field"><label for="lf-reason"><?= t('Rejection reason') ?> <small class="muted">(<?= t('shown to the owner') ?>)</small></label><input id="lf-reason" name="reject_reason" value="<?= e($d['reject_reason']) ?>"></div>
            <div class="field"><label for="lf-exp"><?= t('Expires on') ?></label><input id="lf-exp" type="date" name="expires_at" value="<?= !empty($d['expires_at']) ? e(date('Y-m-d', strtotime($d['expires_at']))) : '' ?>"></div>
            <label class="switch"><input type="checkbox" name="featured" value="1" <?= $d['featured'] ? 'checked' : '' ?>><span class="switch-ui"></span><span><?= t('Featured ad') ?></span></label>
          </section>
        <?php endif; ?>
        <section class="card form-section sticky">
          <h2><?= $isEdit ? t('Save changes') : t('Ready to post?') ?></h2>
          <?php if (!$asAdmin && setting('require_approval') === '1' && !is_admin()): ?>
            <p class="muted small"><?= icon('info') ?> <?= t('Ads are reviewed by our team before they go live.') ?></p>
          <?php endif; ?>
          <ul class="check-list small">
            <li><?= icon('check') ?><?= t('Use a clear, specific title') ?></li>
            <li><?= icon('check') ?><?= t('Add bright photos from several angles') ?></li>
            <li><?= icon('check') ?><?= t('Set a fair, realistic price') ?></li>
          </ul>
          <?php if (!$isEdit && !$asAdmin && !is_admin()): ?><?= captcha_widget('post') ?><?= $err('captcha') ?><?php endif; ?>
          <button class="btn btn-primary btn-block btn-lg" type="submit"><?= icon($isEdit ? 'check' : 'zap') ?><?= $isEdit ? t('Save ad') : t('Post my ad') ?></button>
          <a class="btn btn-ghost btn-block" href="<?= e($cancelUrl) ?>"><?= t('Cancel') ?></a>
        </section>
      </aside>
    </form>
    <?php
}

/** Inputs for every category's custom fields; JS shows only the groups for the chosen category. */
function render_custom_field_inputs(array $values, array $errors): void
{
    $groups = [];
    foreach (fields_all() as $id => $f) {
        $groups[(int) $f['category_id']][$id] = $f;
    }
    if (!$groups) {
        return;
    }
    echo '<div class="cf-wrap" data-cf-wrap>';
    foreach ($groups as $catId => $fields) {
        echo '<div class="cf-group form-grid" data-cf-cat="' . $catId . '" hidden>';
        foreach ($fields as $id => $f) {
            $name = 'cf[' . $id . ']';
            $val = (string) ($values[$id] ?? '');
            $req = $f['required'] && $f['type'] !== 'checkbox' ? ' data-required' : '';
            $has = isset($errors['cf_' . $id]) ? ' has-error' : '';
            $label = t($f['label']) . ($f['unit'] !== '' ? ' <small class="muted">(' . e($f['unit']) . ')</small>' : '') . ($f['required'] ? '' : ' <small class="muted">' . t('optional') . '</small>');
            if ($f['type'] === 'checkbox') {
                echo '<div class="field cf-check"><label class="switch"><input type="checkbox" name="' . $name . '" value="1"' . ($val === '1' ? ' checked' : '') . '><span class="switch-ui"></span><span>' . t($f['label']) . '</span></label></div>';
                continue;
            }
            echo '<div class="field' . $has . '"><label for="cf' . $id . '">' . $label . '</label>';
            if ($f['type'] === 'select') {
                echo '<select id="cf' . $id . '" name="' . $name . '"' . $req . '><option value="">' . t('Choose…') . '</option>';
                foreach (field_options($f) as $o) {
                    echo '<option value="' . e($o) . '"' . ($o === $val ? ' selected' : '') . '>' . t($o) . '</option>';
                }
                echo '</select>';
            } else {
                $type = $f['type'] === 'number' ? ' inputmode="decimal"' : ' maxlength="190"';
                echo '<input id="cf' . $id . '" name="' . $name . '" value="' . e($val) . '"' . $type . $req . '>';
            }
            if (isset($errors['cf_' . $id])) {
                echo '<span class="field-error">' . e($errors['cf_' . $id]) . '</span>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
}

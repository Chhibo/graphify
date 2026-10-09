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
        'item_condition' => '', 'location' => $user['location'] ?? '', 'phone' => $user['phone'] ?? '', 'show_phone' => 1,
        'tags' => '', 'status' => 'pending', 'featured' => 0, 'reject_reason' => '',
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
    $rawPrice = str_replace([',', ' '], '', input('price'));
    $d['price'] = in_array($d['price_type'], ['fixed', 'negotiable'], true) ? (float) $rawPrice : 0;

    if (mb_strlen($d['title']) < 5) $errors['title'] = 'Give your ad a clear title (at least 5 characters).';
    if (!isset(categories_all()[$d['category_id']])) $errors['category_id'] = 'Please choose a category.';
    if (mb_strlen($d['description']) < 15) $errors['description'] = 'Describe your item in a bit more detail (at least 15 characters).';
    if (in_array($d['price_type'], ['fixed', 'negotiable'], true) && ($rawPrice === '' || !is_numeric($rawPrice) || $d['price'] < 0)) {
        $errors['price'] = 'Enter a valid price.';
    }
    if ($d['price'] > 999999999999) $errors['price'] = 'That price is too high.';

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

    $needsApproval = setting('require_approval') === '1' && !$asAdmin && $me['role'] !== 'admin';
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
        $uploadErrors[] = 'Only ' . $max . ' photos are allowed per ad; extra photos were skipped.';
        $files = array_slice($files, 0, max(0, $room));
    }
    $order = count($existing) + 1;
    foreach ($files as $f) {
        $res = store_image($f, 'listings');
        if (is_string($res)) {
            $uploadErrors[] = $res;
            continue;
        }
        db_insert('listing_images', ['listing_id' => $id, 'path' => $res['path'], 'thumb' => $res['thumb'], 'sort_order' => $order++]);
    }
    $cover = (int) input('cover');
    if ($cover) {
        $i = 1;
        foreach (q_all('SELECT id FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id', [$id]) as $img) {
            q('UPDATE listing_images SET sort_order = ? WHERE id = ?', [(int) $img['id'] === $cover ? 0 : $i++, $img['id']]);
        }
    }

    foreach ($uploadErrors as $ue) {
        flash('warning', $ue);
    }
    $status = q_val('SELECT status FROM listings WHERE id = ?', [$id]);
    if ($listing) {
        flash('success', $status === 'pending' && !$asAdmin ? 'Ad updated and sent for review.' : 'Ad updated successfully.');
    } else {
        flash('success', $status === 'pending' ? 'Your ad was submitted and will go live once approved.' : 'Your ad is live! 🎉');
    }
    redirect(str_replace('{id}', (string) $id, $successUrl));
}

function render_listing_form(array $d, array $images, array $errors, bool $asAdmin, string $cancelUrl): void
{
    $err = fn(string $k) => isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';
    $cls = fn(string $k) => isset($errors[$k]) ? ' has-error' : '';
    $max = (int) setting('max_images', '8');
    $isEdit = !empty($d['id']);
    ?>
    <?php if ($errors): ?><div class="alert alert-error"><?= icon('alert') ?><span>Please fix the highlighted fields below.</span></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="listing-form" data-listing-form>
      <?= csrf_field() ?>
      <div class="form-main">
        <section class="card form-section">
          <h2><span class="step-num">1</span>What are you selling?</h2>
          <div class="field<?= $cls('title') ?>">
            <label for="lf-title">Ad title</label>
            <input id="lf-title" name="title" maxlength="150" value="<?= e($d['title']) ?>" placeholder="e.g. iPhone 14 Pro, 128GB, excellent condition" required data-count>
            <?= $err('title') ?>
          </div>
          <div class="form-grid">
            <div class="field<?= $cls('category_id') ?>">
              <label for="lf-cat">Category</label>
              <select id="lf-cat" name="category_id" required><option value="">Choose a category</option><?= category_options((int) $d['category_id'], false) ?></select>
              <?= $err('category_id') ?>
            </div>
            <div class="field">
              <label for="lf-cond">Condition</label>
              <select id="lf-cond" name="item_condition">
                <?php foreach (CONDITIONS as $k => $lbl): ?><option value="<?= e($k) ?>" <?= $d['item_condition'] === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="field<?= $cls('description') ?>">
            <label for="lf-desc">Description</label>
            <textarea id="lf-desc" name="description" rows="8" maxlength="20000" placeholder="Include details buyers care about: brand, model, age, size, defects, reason for selling…" required><?= e($d['description']) ?></textarea>
            <?= $err('description') ?>
          </div>
          <div class="field">
            <label for="lf-tags">Tags <small class="muted">(comma separated, optional)</small></label>
            <input id="lf-tags" name="tags" value="<?= e($d['tags']) ?>" placeholder="apple, smartphone, unlocked">
          </div>
        </section>

        <section class="card form-section">
          <h2><span class="step-num">2</span>Price</h2>
          <div class="seg-radio">
            <?php foreach (PRICE_TYPES as $k => $lbl): ?>
              <label><input type="radio" name="price_type" value="<?= $k ?>" <?= $d['price_type'] === $k ? 'checked' : '' ?> data-price-type><span><?= e($lbl) ?></span></label>
            <?php endforeach; ?>
          </div>
          <div class="field price-field<?= $cls('price') ?>" data-price-field>
            <label for="lf-price">Amount</label>
            <div class="input-prefix"><span><?= e(setting('currency_symbol')) ?></span><input id="lf-price" name="price" inputmode="decimal" value="<?= e($d['price'] === '' ? '' : (string) (float) $d['price']) ?>" placeholder="0"></div>
            <?= $err('price') ?>
          </div>
        </section>

        <section class="card form-section">
          <h2><span class="step-num">3</span>Photos <small class="muted">up to <?= $max ?></small></h2>
          <?php if ($images): ?>
            <p class="muted small">Pick a cover photo and tick any photo you want to remove.</p>
            <div class="image-manager">
              <?php foreach ($images as $i => $img): ?>
                <div class="im-item">
                  <img src="<?= e(upload_url($img['thumb'])) ?>" alt="">
                  <label class="im-cover" title="Use as cover"><input type="radio" name="cover" value="<?= (int) $img['id'] ?>" <?= $i === 0 ? 'checked' : '' ?>><span>Cover</span></label>
                  <label class="im-remove" title="Remove photo"><input type="checkbox" name="remove_images[]" value="<?= (int) $img['id'] ?>"><?= icon('trash') ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <label class="dropzone" data-dropzone>
            <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple data-max="<?= max(0, $max - count($images)) ?>">
            <span class="dz-icon"><?= icon('upload') ?></span>
            <strong>Click to add photos or drag them here</strong>
            <small>JPG, PNG, WEBP or GIF · max <?= e(setting('max_image_mb', '5')) ?> MB each · the first one becomes the cover</small>
          </label>
          <div class="upload-preview" data-preview></div>
        </section>

        <section class="card form-section">
          <h2><span class="step-num">4</span>Location & contact</h2>
          <div class="form-grid">
            <div class="field">
              <label for="lf-loc">Location</label>
              <div class="input-icon"><?= icon('map-pin') ?><input id="lf-loc" name="location" maxlength="150" value="<?= e($d['location']) ?>" placeholder="City, area"></div>
            </div>
            <div class="field">
              <label for="lf-phone">Phone</label>
              <div class="input-icon"><?= icon('phone') ?><input id="lf-phone" name="phone" type="tel" maxlength="40" value="<?= e($d['phone']) ?>" placeholder="Optional"></div>
            </div>
          </div>
          <label class="switch"><input type="checkbox" name="show_phone" value="1" <?= $d['show_phone'] ? 'checked' : '' ?>><span class="switch-ui"></span><span>Show my phone number on this ad</span></label>
        </section>
      </div>

      <aside class="form-side">
        <?php if ($asAdmin): ?>
          <section class="card form-section">
            <h2>Moderation</h2>
            <div class="field"><label for="lf-status">Status</label>
              <select id="lf-status" name="status">
                <?php foreach (LISTING_STATUSES as $s): ?><option value="<?= $s ?>" <?= $d['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
              </select></div>
            <div class="field"><label for="lf-reason">Rejection reason <small class="muted">(shown to the owner)</small></label><input id="lf-reason" name="reject_reason" value="<?= e($d['reject_reason']) ?>"></div>
            <div class="field"><label for="lf-exp">Expires on</label><input id="lf-exp" type="date" name="expires_at" value="<?= !empty($d['expires_at']) ? e(date('Y-m-d', strtotime($d['expires_at']))) : '' ?>"></div>
            <label class="switch"><input type="checkbox" name="featured" value="1" <?= $d['featured'] ? 'checked' : '' ?>><span class="switch-ui"></span><span>Featured ad</span></label>
          </section>
        <?php endif; ?>
        <section class="card form-section sticky">
          <h2><?= $isEdit ? 'Save changes' : 'Ready to post?' ?></h2>
          <?php if (!$asAdmin && setting('require_approval') === '1' && !is_admin()): ?>
            <p class="muted small"><?= icon('info') ?> Ads are reviewed by our team before they go live.</p>
          <?php endif; ?>
          <ul class="check-list small">
            <li><?= icon('check') ?>Use a clear, specific title</li>
            <li><?= icon('check') ?>Add bright photos from several angles</li>
            <li><?= icon('check') ?>Set a fair, realistic price</li>
          </ul>
          <button class="btn btn-primary btn-block btn-lg" type="submit"><?= icon($isEdit ? 'check' : 'zap') ?><?= $isEdit ? 'Save ad' : 'Post my ad' ?></button>
          <a class="btn btn-ghost btn-block" href="<?= e($cancelUrl) ?>">Cancel</a>
        </section>
      </aside>
    </form>
    <?php
}

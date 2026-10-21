<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'save' || $action === 'tg_test') {
        $token = trim((string)($_POST['tg_token'] ?? ''));
        if ($token !== '') {
            save_setting('tg_token', $token); // blank keeps the saved token
        }
        save_setting('tg_chat', trim((string)($_POST['tg_chat'] ?? '')));
        foreach (['tg_on_lead', 'tg_on_message', 'tg_on_subscribe', 'newsletter_show', 'cookie_banner', 'cookie_wait'] as $k) {
            save_setting($k, isset($_POST[$k]) ? '1' : '0');
        }
        foreach (['newsletter_title', 'newsletter_text', 'cookie_text'] as $k) {
            save_setting($k, trim((string)($_POST[$k] ?? '')));
        }
        foreach (['ad_top', 'ad_product', 'ad_bottom'] as $k) {
            save_setting($k, (string)($_POST[$k] ?? ''));
        }
    }
    if ($action === 'tg_test') {
        $err = telegram_send('✅ Test from ' . setting('store_name') . ': Telegram alerts are working!');
        $testResult = $err === '' ? [true, 'Test message sent. Check Telegram.'] : [false, $err];
    } elseif ($action === 'tg_forget') {
        save_setting('tg_token', '');
        flash('Telegram bot token removed.');
        redirect('admin/growth.php');
    } else {
        flash('Saved.');
        redirect('admin/growth.php');
    }
}
$on = fn(string $k, string $def = '0') => setting($k, $def) === '1' ? 'checked' : '';

admin_header('Alerts, ads & email', 'Telegram alerts, ad slots, email signups and the cookie banner');
?>
<?php if ($testResult): ?><?= notice(e($testResult[1]), '', '', $testResult[0] ? 'warn' : 'bad', $testResult[0] ? 'circle-check' : 'x') ?><?php endif; ?>
<form method="post" class="form">
  <?= csrf_field() ?>
  <div class="grid-2-even">
    <div>
      <div class="card" id="telegram">
        <h2>Telegram alerts</h2>
        <p class="card-sub">Get a message on your phone for every completed offer.</p>
        <ol class="muted" style="padding-left:18px;margin-top:0">
          <li>In Telegram, open <b>@BotFather</b>, send <code>/newbot</code> and follow the steps. Copy the <b>bot token</b>.</li>
          <li>Open your new bot and press <b>Start</b> (send it any message).</li>
          <li>Open <b>@userinfobot</b>, press Start, and copy your <b>Id</b>: that's your chat ID.</li>
        </ol>
        <label>Bot token
          <input type="password" name="tg_token" autocomplete="off" placeholder="<?= setting('tg_token') !== '' ? 'Saved (…' . e(substr(setting('tg_token'), -4)) . '). Leave blank to keep.' : '123456789:AA…' ?>"></label>
        <label>Chat ID <input name="tg_chat" value="<?= e(setting('tg_chat')) ?>" placeholder="e.g. 123456789"></label>
        <label class="check"><input type="checkbox" name="tg_on_lead" <?= $on('tg_on_lead', '1') ?>> Completed offers (with payout and country)</label>
        <label class="check"><input type="checkbox" name="tg_on_message" <?= $on('tg_on_message', '1') ?>> New contact messages</label>
        <label class="check"><input type="checkbox" name="tg_on_subscribe" <?= $on('tg_on_subscribe') ?>> New email subscribers</label>
        <button class="btn btn-light" name="action" value="tg_test"><?= icon('telegram', 16) ?> Save &amp; send test message</button>
        <?php if (setting('tg_token') !== ''): ?><button class="btn btn-small btn-danger" name="action" value="tg_forget" formnovalidate>Remove token</button><?php endif; ?>
      </div>

      <div class="card" id="newsletter">
        <h2>Email list</h2>
        <p class="card-sub">A "get new freebies by email" box in the footer. Subscribers appear in <a href="subscribers.php">Subscribers</a>, where you can export them.</p>
        <label class="switch"><input type="checkbox" name="newsletter_show" <?= $on('newsletter_show') ?>><span></span> Show signup box</label><br><br>
        <label>Title <input name="newsletter_title" value="<?= e(setting('newsletter_title')) ?>" placeholder="Get new freebies first"></label>
        <label>Text <input name="newsletter_text" value="<?= e(setting('newsletter_text')) ?>" placeholder="One email when we add something new. No spam."></label>
        <small>Mention the email list in your Privacy Policy, and only send emails people signed up for.</small>
      </div>

      <div class="card" id="cookies">
        <h2>Cookie consent banner</h2>
        <p class="card-sub">Recommended if you get visitors from the EU or UK.</p>
        <label class="switch"><input type="checkbox" name="cookie_banner" <?= $on('cookie_banner') ?>><span></span> Show cookie banner</label><br><br>
        <label>Banner text <textarea name="cookie_text" rows="3" placeholder="We use cookies to run this site and, with your consent, for analytics. Offers are provided by advertising partners who may collect data."><?= e(setting('cookie_text')) ?></textarea></label>
        <label class="check"><input type="checkbox" name="cookie_wait" <?= $on('cookie_wait', '1') ?>>
          <span>Load my tracking code only after the visitor clicks Accept <small>Applies to the Google Analytics / Pixel code in Settings → Tracking code.</small></span></label>
      </div>
    </div>

    <div>
      <div class="card" id="ads">
        <h2>Ad slots</h2>
        <p class="card-sub">Paste ad code from AdSense, Adsterra, Monetag… Visitors who don't complete an offer can still earn you something. Leave a box empty to hide that slot.</p>
        <label>Top of every page (under the header) <textarea name="ad_top" rows="4" class="mono" placeholder="<script async src=…></script>"><?= e(setting('ad_top')) ?></textarea></label>
        <label>Product pages (under the download button area) <textarea name="ad_product" rows="4" class="mono"><?= e(setting('ad_product')) ?></textarea></label>
        <label>Bottom of every page (above the footer) <textarea name="ad_bottom" rows="4" class="mono"><?= e(setting('ad_bottom')) ?></textarea></label>
        <small>Ads are never shown inside the offer popup. Check your CPA network's rules about showing other ads on the same page.</small>
      </div>
    </div>
  </div>
  <div class="form-actions"><button class="btn btn-grad" name="action" value="save"><?= icon('save', 16) ?> Save</button></div>
</form>
<?php admin_footer();

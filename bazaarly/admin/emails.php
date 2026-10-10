<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_admin();
$templates = email_templates();
$key = input('tpl');
if ($key !== '' && !isset($templates[$key])) {
    $key = '';
}

if (is_post()) {
    $action = input('action');
    if ($action === 'smtp') {
        $values = [
            'mail_driver' => input('mail_driver') === 'smtp' ? 'smtp' : 'mail',
            'mail_from' => filter_var(input('mail_from'), FILTER_VALIDATE_EMAIL) ? input('mail_from') : '',
            'mail_from_name' => mb_substr(input('mail_from_name'), 0, 100),
            'smtp_host' => mb_substr(input('smtp_host'), 0, 190),
            'smtp_port' => (string) max(1, min(65535, (int) input('smtp_port', '587'))),
            'smtp_encryption' => in_array(input('smtp_encryption'), ['tls', 'ssl', 'none'], true) ? input('smtp_encryption') : 'tls',
            'smtp_username' => mb_substr(input('smtp_username'), 0, 190),
        ];
        if ((string) ($_POST['smtp_password'] ?? '') !== '') {
            $values['smtp_password'] = (string) $_POST['smtp_password'];
        }
        save_settings($values);
        log_activity('settings.email', 'Mail delivery: ' . $values['mail_driver']);
        flash('success', 'Email delivery settings saved.');
        redirect('admin/emails.php');
    }
    if ($action === 'test') {
        $to = filter_var(input('to'), FILTER_VALIDATE_EMAIL) ? input('to') : $me['email'];
        $ok = send_mail($to, 'Test email from ' . setting('site_name'), "Hi!\n\nThis is a test email from your website. If you can read this, email delivery works. 🎉\n\n" . full_url());
        $ok ? flash('success', "Test email sent to $to. Check the inbox (and the spam folder).")
            : flash('error', 'Sending failed: ' . (mail_last_error() ?: 'unknown error'));
        redirect('admin/emails.php');
    }
    if ($action === 'template' && $key !== '') {
        $values = [
            "email_tpl_{$key}_subject" => mb_substr(input('subject'), 0, 190),
            "email_tpl_{$key}_body" => str_replace("\r\n", "\n", (string) ($_POST['body'] ?? '')),
        ];
        if ($templates[$key]['optional']) {
            $values["email_tpl_{$key}_enabled"] = empty($_POST['enabled']) ? '0' : '1';
        }
        if (input('reset') === '1') {
            $values["email_tpl_{$key}_subject"] = '';
            $values["email_tpl_{$key}_body"] = '';
        }
        save_settings($values);
        log_activity('email_template.update', $templates[$key]['name']);
        flash('success', input('reset') === '1' ? 'Template reset to the default text.' : 'Template saved.');
        redirect('admin/emails.php?tpl=' . $key);
    }
}

$adminActive = 'emails';
$pageTitle = 'Emails & SMTP';
require APP_ROOT . '/includes/admin-top.php';

if ($key !== ''):
    $t = email_template($key);
    $sample = ['site_name' => setting('site_name'), 'name' => 'Jane', 'sender_name' => 'Daniel', 'sender_email' => 'daniel@example.com',
        'ad_title' => 'iPhone 15 Pro 256GB', 'message' => 'Hi! Is this still available?', 'reason' => 'Please add real photos of the item.',
        'search_name' => 'iPhone under $900', 'count' => '3', 'subject' => 'Question about my ad',
        'ads' => "• iPhone 15 Pro 256GB — $820.00\n  " . full_url('ad/1'), 'action_url' => full_url('example-link')];
    $map = [];
    foreach ($sample as $k => $v) {
        $map['{' . $k . '}'] = $v;
    }
    $previewSubject = strtr($t['subject'], $map);
?>
  <div class="page-actions"><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/emails.php')) ?>"><?= icon('chevron-left') ?>All emails</a><span class="muted small"><?= e($templates[$key]['desc']) ?></span></div>
  <div class="admin-grid">
    <form method="post" class="card form-section">
      <?= csrf_field() ?><input type="hidden" name="action" value="template"><input type="hidden" name="tpl" value="<?= e($key) ?>">
      <h2><?= e($t['name']) ?></h2>
      <?php if ($t['optional']): ?><label class="switch"><input type="checkbox" name="enabled" value="1" <?= $t['enabled'] ? 'checked' : '' ?>><span class="switch-ui"></span><span>Send this email</span></label><?php endif; ?>
      <div class="field"><label for="t-sub">Subject</label><input id="t-sub" name="subject" value="<?= e($t['subject']) ?>" required></div>
      <div class="field"><label for="t-body">Message</label><textarea id="t-body" name="body" rows="14" required><?= e($t['body']) ?></textarea>
        <small class="help">Placeholders: <?php foreach (array_merge(['site_name'], $t['vars']) as $v): ?><code data-insert="{<?= $v ?>}">{<?= $v ?>}</code> <?php endforeach; ?><br>Put <code>{action_url}</code> on its own line to show it as a “<?= e($t['button']) ?>” button.</small></div>
      <div class="btn-row">
        <button class="btn btn-primary" type="submit">Save template</button>
        <button class="btn btn-ghost" type="submit" name="reset" value="1" data-confirm-click="Reset this email to the original text?">Reset to default</button>
      </div>
    </form>
    <section class="card form-section">
      <h2>Preview</h2>
      <p class="muted small">Subject: <strong><?= e($previewSubject) ?></strong></p>
      <iframe class="email-preview" title="Email preview" sandbox srcdoc="<?= e(email_html($previewSubject, strtr($t['body'], $map), $sample['action_url'], $t['button'])) ?>"></iframe>
    </section>
  </div>
<?php else: ?>
  <div class="admin-grid">
    <form method="post" class="card form-section" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="action" value="smtp">
      <h2><?= icon('send') ?>How emails are sent</h2>
      <div class="db-choice">
        <label class="choice-card"><input type="radio" name="mail_driver" value="mail" <?= setting('mail_driver') !== 'smtp' ? 'checked' : '' ?> data-mail-driver>
          <span><strong>PHP mail()</strong><small>Works on most shared hosting with no setup. Emails may land in spam.</small></span></label>
        <label class="choice-card"><input type="radio" name="mail_driver" value="smtp" <?= setting('mail_driver') === 'smtp' ? 'checked' : '' ?> data-mail-driver>
          <span><strong>SMTP (recommended)</strong><small>Use your hosting email, Gmail, Brevo, Mailgun, SendGrid… Better delivery.</small></span></label>
      </div>
      <div class="form-grid">
        <div class="field"><label for="m-from">From email</label><input id="m-from" type="email" name="mail_from" value="<?= e(setting('mail_from')) ?>" placeholder="no-reply@yourdomain.com"></div>
        <div class="field"><label for="m-name">From name</label><input id="m-name" name="mail_from_name" value="<?= e(setting('mail_from_name')) ?>" placeholder="<?= e(setting('site_name')) ?>"></div>
      </div>
      <div data-smtp-fields <?= setting('mail_driver') === 'smtp' ? '' : 'hidden' ?>>
        <div class="form-grid">
          <div class="field"><label for="s-host">SMTP host</label><input id="s-host" name="smtp_host" value="<?= e(setting('smtp_host')) ?>" placeholder="mail.yourdomain.com"></div>
          <div class="field"><label for="s-port">Port</label><input id="s-port" type="number" name="smtp_port" value="<?= e(setting('smtp_port', '587')) ?>"><small class="help">587 for TLS, 465 for SSL</small></div>
          <div class="field"><label for="s-enc">Security</label><select id="s-enc" name="smtp_encryption">
            <?php foreach (['tls' => 'TLS (STARTTLS)', 'ssl' => 'SSL', 'none' => 'None'] as $k => $lbl): ?><option value="<?= $k ?>" <?= setting('smtp_encryption', 'tls') === $k ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?></select></div>
          <div class="field"><label for="s-user">Username</label><input id="s-user" name="smtp_username" value="<?= e(setting('smtp_username')) ?>" autocomplete="off"></div>
        </div>
        <div class="field"><label for="s-pass">Password</label><input id="s-pass" type="password" name="smtp_password" placeholder="<?= setting('smtp_password') !== '' ? '•••••••• (leave blank to keep)' : '' ?>" autocomplete="new-password"></div>
      </div>
      <button class="btn btn-primary" type="submit">Save delivery settings</button>
    </form>

    <div>
      <form method="post" class="card form-section">
        <?= csrf_field() ?><input type="hidden" name="action" value="test">
        <h2><?= icon('mail') ?>Send a test email</h2>
        <p class="muted small">Save your settings first, then send a test to make sure emails arrive.</p>
        <div class="field"><label for="t-to">Send to</label><input id="t-to" type="email" name="to" value="<?= e($me['email']) ?>"></div>
        <button class="btn btn-ghost" type="submit"><?= icon('send') ?>Send test</button>
      </form>
      <section class="card table-card">
        <div class="card-head"><h2>Email templates</h2></div>
        <ul class="mini-list">
          <?php foreach ($templates as $k => $tp): $cur = email_template($k); ?>
            <li><span class="stat-icon sm<?= $cur['enabled'] ? '' : ' muted-icon' ?>"><?= icon('mail') ?></span>
              <div class="mini-body"><a href="?tpl=<?= $k ?>"><?= e($tp['name']) ?></a><small><?= e($tp['desc']) ?></small></div>
              <?= $cur['enabled'] ? '<span class="badge badge-success">On</span>' : '<span class="badge">Off</span>' ?></li>
          <?php endforeach; ?>
        </ul>
      </section>
    </div>
  </div>
<?php endif; ?>
<?php require APP_ROOT . '/includes/admin-bottom.php';

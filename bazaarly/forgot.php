<?php
require __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    $email = strtolower(input('email'));
    if (too_many_attempts()) {
        flash('error', 'Too many requests. Please try again later.');
        redirect('forgot.php');
    }
    record_attempt();
    $user = q_one("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]);
    if ($user) {
        $token = bin2hex(random_bytes(32));
        q('DELETE FROM password_resets WHERE user_id = ?', [$user['id']]);
        db_insert('password_resets', ['user_id' => $user['id'], 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 3600)]);
        $link = full_url('reset.php?token=' . $token);
        send_mail($user['email'], 'Reset your password',
            "Hi {$user['name']},\n\nWe received a request to reset your password. Use the link below within the next hour:\n\n$link\n\nIf you didn't ask for this you can ignore this email.\n\n— " . setting('site_name'));
    }
    // Same message either way so the form can't be used to discover accounts.
    flash('success', 'If an account exists for that email, we have sent a reset link. Check your inbox (and spam folder).');
    redirect('forgot.php');
}

$pageTitle = 'Forgot password';
require __DIR__ . '/includes/header.php';
?>
<section class="container section-sm">
  <form method="post" class="card auth-single">
    <?= csrf_field() ?>
    <span class="auth-icon"><?= icon('lock') ?></span>
    <h1>Forgot your password?</h1>
    <p class="muted">Enter your account email and we'll send you a link to choose a new one.</p>
    <div class="field"><label for="email">Email</label><div class="input-icon"><?= icon('mail') ?><input id="email" type="email" name="email" required autofocus></div></div>
    <button class="btn btn-primary btn-block" type="submit">Send reset link</button>
    <p class="center muted"><a href="<?= e(url('login.php')) ?>">Back to sign in</a></p>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php';

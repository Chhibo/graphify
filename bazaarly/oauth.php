<?php
/**
 * Sign in with Google / Facebook (OAuth 2.0). Enable in Admin → Settings → Social login.
 * Redirect URL to register with the provider: https://your-site.com/oauth.php?provider=google (or facebook)
 */
define('ALLOW_IN_MAINTENANCE', true);
require __DIR__ . '/includes/bootstrap.php';

$provider = input('provider');
$providers = social_providers();
if (!isset($providers[$provider])) {
    flash('error', 'This sign-in method is not available.');
    redirect('login.php');
}

$redirectUri = full_url('oauth.php?provider=' . $provider);
$cfg = $provider === 'google'
    ? [
        'auth' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token' => 'https://oauth2.googleapis.com/token',
        'id' => setting('google_client_id'), 'secret' => setting('google_client_secret'),
        'scope' => 'openid email profile',
    ]
    : [
        'auth' => 'https://www.facebook.com/v19.0/dialog/oauth',
        'token' => 'https://graph.facebook.com/v19.0/oauth/access_token',
        'id' => setting('facebook_app_id'), 'secret' => setting('facebook_app_secret'),
        'scope' => 'email,public_profile',
    ];

$fail = function (string $msg): never {
    flash('error', $msg);
    redirect('login.php');
};

// Step 1: send the visitor to the provider
if (!isset($_GET['code']) && !isset($_GET['error'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth'] = ['state' => $state, 'provider' => $provider];
    $params = ['client_id' => $cfg['id'], 'redirect_uri' => $redirectUri, 'response_type' => 'code', 'scope' => $cfg['scope'], 'state' => $state];
    if ($provider === 'google') {
        $params['prompt'] = 'select_account';
    }
    header('Location: ' . $cfg['auth'] . '?' . http_build_query($params));
    exit;
}

// Step 2: the provider sent the visitor back
$saved = $_SESSION['oauth'] ?? null;
unset($_SESSION['oauth']);
if (isset($_GET['error'])) {
    $fail('Sign-in was cancelled.');
}
if (!$saved || $saved['provider'] !== $provider || !hash_equals($saved['state'], input('state'))) {
    $fail('Sign-in session expired. Please try again.');
}

$tokenRes = http_request($cfg['token'], 'POST', [
    'client_id' => $cfg['id'], 'client_secret' => $cfg['secret'], 'redirect_uri' => $redirectUri,
    'code' => input('code'), 'grant_type' => 'authorization_code',
]);
$token = json_decode($tokenRes['body'], true)['access_token'] ?? '';
if ($token === '') {
    error_log('Bazaarly OAuth token error (' . $provider . '): ' . $tokenRes['status'] . ' ' . $tokenRes['body'] . ' ' . $tokenRes['error']);
    $fail('Could not sign you in with ' . $providers[$provider] . '. Please try again.');
}

if ($provider === 'google') {
    $res = http_request('https://openidconnect.googleapis.com/v1/userinfo', 'GET', null, ['Authorization: Bearer ' . $token]);
    $p = json_decode($res['body'], true) ?: [];
    $profile = ['uid' => (string) ($p['sub'] ?? ''), 'email' => (string) ($p['email'] ?? ''), 'name' => (string) ($p['name'] ?? ''),
        'email_ok' => !empty($p['email_verified'])];
} else {
    $proof = hash_hmac('sha256', $token, $cfg['secret']);
    $res = http_request('https://graph.facebook.com/v19.0/me?' . http_build_query(['fields' => 'id,name,email', 'access_token' => $token, 'appsecret_proof' => $proof]));
    $p = json_decode($res['body'], true) ?: [];
    $profile = ['uid' => (string) ($p['id'] ?? ''), 'email' => (string) ($p['email'] ?? ''), 'name' => (string) ($p['name'] ?? ''), 'email_ok' => true];
}
if ($profile['uid'] === '') {
    $fail('Could not read your ' . $providers[$provider] . ' profile. Please try again.');
}
$profile['email'] = strtolower($profile['email']);

// Find the member: linked account → same email → new account
$user = null;
$link = q_one('SELECT user_id FROM user_social WHERE provider = ? AND provider_uid = ?', [$provider, $profile['uid']]);
if ($link) {
    $user = q_one('SELECT * FROM users WHERE id = ?', [$link['user_id']]);
}
if (!$user) {
    if ($profile['email'] === '' || !filter_var($profile['email'], FILTER_VALIDATE_EMAIL)) {
        $fail('Your ' . $providers[$provider] . ' account did not share an email address. Please sign up with your email instead.');
    }
    if (!$profile['email_ok']) {
        $fail('Please verify your email with ' . $providers[$provider] . ' first, or sign up with your email instead.');
    }
    $user = q_one('SELECT * FROM users WHERE email = ?', [$profile['email']]);
    if (!$user) {
        if (setting('allow_registration') !== '1') {
            $fail('New sign-ups are currently closed.');
        }
        $name = mb_substr($profile['name'] ?: strtok($profile['email'], '@'), 0, 100);
        $base = substr(preg_replace('/[^a-z0-9_.]/', '', strtolower(str_replace(' ', '.', $name))) ?: 'member', 0, 24);
        $username = strlen($base) >= 3 ? $base : 'member';
        while (q_val('SELECT id FROM users WHERE username = ?', [$username])) {
            $username = $base . random_int(10, 9999);
        }
        $id = db_insert('users', [
            'name' => $name, 'username' => $username, 'email' => $profile['email'],
            'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'phone' => '', 'location' => '', 'bio' => '',
            'avatar' => '', 'role' => 'user', 'status' => 'active', 'verified' => 0, 'created_at' => now(), 'email_verified_at' => now(),
        ]);
        $user = q_one('SELECT * FROM users WHERE id = ?', [$id]);
        send_template('welcome', $user['email'], ['name' => $user['name'], 'action_url' => full_url('dashboard/edit.php')]);
        flash('success', 'Welcome to ' . setting('site_name') . '! Your account is ready.');
    }
    db_insert('user_social', ['user_id' => $user['id'], 'provider' => $provider, 'provider_uid' => $profile['uid'], 'created_at' => now()]);
}

if ($user['status'] === 'banned') {
    $fail('This account has been suspended. Please contact support.');
}
if (empty($user['email_verified_at'])) {
    q('UPDATE users SET email_verified_at = ? WHERE id = ?', [now(), $user['id']]);
}
login_user($user, true);
$_SESSION['social_login'] = true;
if ($user['role'] === 'admin') {
    log_activity('admin.login', 'Signed in with ' . $providers[$provider], 'user', (int) $user['id']);
}
header('Location: ' . intended_url('dashboard/'));
exit;

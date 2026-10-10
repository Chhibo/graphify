<?php
/**
 * Email: PHP mail() or SMTP, HTML layout and admin-editable templates.
 */
declare(strict_types=1);

/** Built-in templates. Admins can edit subject/body (and enable/disable optional ones). */
function email_templates(): array
{
    return [
        'verify_email' => [
            'name' => 'Verify email address',
            'desc' => 'Sent after sign-up (when email verification is on) or when a user changes their email.',
            'vars' => ['name', 'action_url'],
            'button' => 'Verify my email',
            'optional' => false,
            'subject' => 'Please confirm your email for {site_name}',
            'body' => "Hi {name},\n\nThanks for joining {site_name}! Please confirm your email address so you can post ads and message other members.\n\n{action_url}\n\nThis link is valid for 48 hours. If you didn't create an account, you can ignore this email.",
        ],
        'welcome' => [
            'name' => 'Welcome email',
            'desc' => 'Sent to new members right after they create an account.',
            'vars' => ['name', 'action_url'],
            'button' => 'Post your first ad',
            'optional' => true,
            'subject' => 'Welcome to {site_name}!',
            'body' => "Hi {name},\n\nWelcome to {site_name} — we're happy to have you.\n\nPosting an ad is free and takes about two minutes. Add a few clear photos and a fair price and you'll hear from buyers in no time.\n\n{action_url}\n\nHappy selling!",
        ],
        'password_reset' => [
            'name' => 'Password reset',
            'desc' => 'Sent when someone uses "Forgot password".',
            'vars' => ['name', 'action_url'],
            'button' => 'Choose a new password',
            'optional' => false,
            'subject' => 'Reset your {site_name} password',
            'body' => "Hi {name},\n\nWe received a request to reset your password. Click the button below within the next hour to choose a new one:\n\n{action_url}\n\nIf you didn't ask for this, you can safely ignore this email.",
        ],
        'new_message' => [
            'name' => 'New message',
            'desc' => 'Sent when a member receives a message or reply about an ad.',
            'vars' => ['name', 'sender_name', 'ad_title', 'message', 'action_url'],
            'button' => 'Reply now',
            'optional' => true,
            'subject' => 'New message about "{ad_title}"',
            'body' => "Hi {name},\n\n{sender_name} sent you a message about \"{ad_title}\":\n\n“{message}”\n\n{action_url}",
        ],
        'ad_approved' => [
            'name' => 'Ad approved',
            'desc' => 'Sent when an admin approves an ad that was waiting for review.',
            'vars' => ['name', 'ad_title', 'action_url'],
            'button' => 'View my ad',
            'optional' => true,
            'subject' => 'Your ad "{ad_title}" is live',
            'body' => "Hi {name},\n\nGood news — your ad \"{ad_title}\" has been approved and is now visible to everyone.\n\n{action_url}",
        ],
        'ad_rejected' => [
            'name' => 'Ad rejected',
            'desc' => 'Sent when an admin rejects an ad.',
            'vars' => ['name', 'ad_title', 'reason', 'action_url'],
            'button' => 'Edit my ad',
            'optional' => true,
            'subject' => 'Your ad "{ad_title}" needs changes',
            'body' => "Hi {name},\n\nYour ad \"{ad_title}\" was not approved.\n\nReason: {reason}\n\nYou can edit the ad and submit it again:\n\n{action_url}",
        ],
        'saved_search_alert' => [
            'name' => 'Saved search alert',
            'desc' => 'Sent when new ads match a member\'s saved search.',
            'vars' => ['name', 'search_name', 'count', 'ads', 'action_url'],
            'button' => 'See all results',
            'optional' => true,
            'subject' => 'New ads for "{search_name}" ({count})',
            'body' => "Hi {name},\n\nWe found {count} new ad(s) matching your saved search \"{search_name}\":\n\n{ads}\n\n{action_url}\n\nYou can turn these alerts off any time from your dashboard.",
        ],
        'contact_form' => [
            'name' => 'Contact form (to admin)',
            'desc' => 'Sent to the site contact email when someone uses the Contact page.',
            'vars' => ['sender_name', 'sender_email', 'subject', 'message', 'action_url'],
            'button' => 'Open inbox',
            'optional' => true,
            'subject' => '[Contact] {subject}',
            'body' => "New message from {sender_name} <{sender_email}>:\n\n{message}\n\n{action_url}",
        ],
    ];
}

function email_template(string $key): array
{
    $t = email_templates()[$key];
    $t['subject'] = setting("email_tpl_{$key}_subject", $t['subject']) ?: $t['subject'];
    $t['body'] = setting("email_tpl_{$key}_body", $t['body']) ?: $t['body'];
    $t['enabled'] = !$t['optional'] || setting("email_tpl_{$key}_enabled", '1') === '1';
    return $t;
}

/** Send a templated email. Returns true on success (false if disabled or failed). */
function send_template(string $key, string $to, array $vars = []): bool
{
    $t = email_template($key);
    if (!$t['enabled']) {
        return false;
    }
    $vars += ['site_name' => setting('site_name'), 'site_url' => full_url(), 'name' => '', 'action_url' => full_url()];
    $map = [];
    foreach ($vars as $k => $v) {
        $map['{' . $k . '}'] = (string) $v;
    }
    $subject = strtr($t['subject'], $map);
    $body = strtr($t['body'], $map);
    return send_mail($to, $subject, $body, email_html($subject, $body, (string) $vars['action_url'], $t['button']));
}

/** Wrap a plain-text body in a simple, mobile friendly HTML layout. */
function email_html(string $subject, string $text, string $actionUrl = '', string $buttonLabel = 'Open'): string
{
    $brand = valid_color(setting('primary_color'), '#0d9488');
    $site = e(setting('site_name'));
    $html = '';
    foreach (preg_split('/\n{2,}/', trim($text)) as $para) {
        $trim = trim($para);
        if ($actionUrl !== '' && $trim === $actionUrl) {
            $html .= '<p style="margin:24px 0"><a href="' . e($actionUrl) . '" style="display:inline-block;background:' . $brand . ';color:#fff;text-decoration:none;font-weight:600;padding:12px 22px;border-radius:999px">' . e($buttonLabel) . '</a></p>'
                . '<p style="font-size:12px;color:#64748b;word-break:break-all">Or copy this link: ' . e($actionUrl) . '</p>';
            continue;
        }
        $p = nl2br(e($trim));
        $p = preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:' . $brand . '">$1</a>', $p);
        $html .= '<p style="margin:0 0 16px">' . $p . '</p>';
    }
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . e($subject) . '</title></head>'
        . '<body style="margin:0;background:#f1f5f9;font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#0f172a;font-size:15px;line-height:1.6">'
        . '<div style="max-width:560px;margin:0 auto;padding:24px 16px">'
        . '<div style="font-weight:800;font-size:20px;color:' . $brand . ';padding:8px 4px 16px">' . $site . '</div>'
        . '<div style="background:#fff;border-radius:16px;padding:28px;border:1px solid #e2e8f0">' . $html . '</div>'
        . '<p style="font-size:12px;color:#94a3b8;text-align:center;margin-top:18px">' . e(copyright_text()) . '<br><a href="' . e(full_url()) . '" style="color:#94a3b8">' . e(full_url()) . '</a></p>'
        . '</div></body></html>';
}

function mail_last_error(?string $set = null): string
{
    static $err = '';
    if ($set !== null) {
        $err = $set;
    }
    return $err;
}

function send_mail(string $to, string $subject, string $text, ?string $html = null): bool
{
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        mail_last_error('Invalid recipient address.');
        return false;
    }
    $html ??= email_html($subject, $text);
    $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $from = setting('mail_from') ?: (setting('smtp_username') && filter_var(setting('smtp_username'), FILTER_VALIDATE_EMAIL) ? setting('smtp_username') : 'no-reply@' . $host);
    $fromName = str_replace(["\r", "\n", '"'], '', setting('mail_from_name') ?: setting('site_name'));

    $boundary = 'b' . bin2hex(random_bytes(12));
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'Date: ' . date('r'),
        'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
        'Reply-To: ' . (setting('contact_email') ?: $from),
        'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . $host . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
        . quoted_printable_encode(str_replace("\n", "\r\n", str_replace("\r\n", "\n", $text))) . "\r\n"
        . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
        . quoted_printable_encode($html) . "\r\n--$boundary--\r\n";

    if (defined('BZR_MAIL_LOG')) {
        // development/testing: write mails to a file instead of sending
        @file_put_contents(BZR_MAIL_LOG, "To: $to\nSubject: $subject\n\n$text\n-----\n", FILE_APPEND);
        return true;
    }

    if (setting('mail_driver') === 'smtp' && setting('smtp_host') !== '') {
        $res = smtp_send($to, $from, "To: <$to>\r\nSubject: $encodedSubject\r\n" . implode("\r\n", $headers) . "\r\n\r\n" . $body);
        if ($res !== true) {
            mail_last_error($res);
            error_log('Bazaarly SMTP error: ' . $res);
            return false;
        }
        return true;
    }
    $ok = @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
    if (!$ok) {
        mail_last_error('PHP mail() failed. Your host may not allow it — configure SMTP in Admin → Emails.');
    }
    return $ok;
}

/** Minimal SMTP client (SSL / STARTTLS / plain, AUTH LOGIN). Returns true or an error string. */
function smtp_send(string $to, string $from, string $message): bool|string
{
    $host = setting('smtp_host');
    $port = (int) (setting('smtp_port') ?: 587);
    $enc = setting('smtp_encryption', 'tls');
    $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return "Could not connect to $host:$port ($errstr).";
    }
    stream_set_timeout($fp, 20);
    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function (string $c, array $ok) use ($fp, $read): string|false {
        if ($c !== '') {
            fwrite($fp, $c . "\r\n");
        }
        $r = $read();
        return in_array((int) substr($r, 0, 3), $ok, true) ? $r : false;
    };
    $fail = function (string $step) use ($fp): string {
        @fclose($fp);
        return "SMTP error during $step.";
    };
    $ehlo = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost') ?: 'localhost';
    if ($cmd('', [220]) === false) return $fail('greeting');
    if ($cmd("EHLO $ehlo", [250]) === false) return $fail('EHLO');
    if ($enc === 'tls') {
        if ($cmd('STARTTLS', [220]) === false) return $fail('STARTTLS');
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT : 0))) {
            return $fail('TLS handshake');
        }
        if ($cmd("EHLO $ehlo", [250]) === false) return $fail('EHLO after TLS');
    }
    if (setting('smtp_username') !== '') {
        if ($cmd('AUTH LOGIN', [334]) === false) return $fail('AUTH');
        if ($cmd(base64_encode(setting('smtp_username')), [334]) === false) return $fail('username');
        if ($cmd(base64_encode(setting('smtp_password')), [235]) === false) {
            @fclose($fp);
            return 'SMTP login failed — check the username and password.';
        }
    }
    if ($cmd("MAIL FROM:<$from>", [250]) === false) return $fail('MAIL FROM (is the sender address allowed?)');
    if ($cmd("RCPT TO:<$to>", [250, 251]) === false) return $fail('RCPT TO');
    if ($cmd('DATA', [354]) === false) return $fail('DATA');
    $message = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $message));
    fwrite($fp, str_replace("\n", "\r\n", $message) . "\r\n.\r\n");
    if ($cmd('', [250]) === false) return $fail('sending message');
    $cmd('QUIT', [221]);
    fclose($fp);
    return true;
}

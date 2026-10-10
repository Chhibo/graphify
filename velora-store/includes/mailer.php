<?php
/**
 * Email sending: PHP mail() or SMTP (your hosting email account), chosen in Admin > Settings > Email.
 * Built-in SMTP client: SSL (port 465), STARTTLS (port 587) or no encryption, AUTH LOGIN / PLAIN.
 */

/** Last sending error (shown by the "Send test email" button). */
function mail_last_error(?string $set = null): string
{
    static $error = '';
    if ($set !== null) {
        $error = $set;
    }
    return $error;
}

function mail_from(): array
{
    $email = setting('mail_from_email') !== '' ? setting('mail_from_email') : setting('store_email');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = 'no-reply@' . preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    $name = setting('mail_from_name') !== '' ? setting('mail_from_name') : setting('store_name', 'Store');
    return [$email, str_replace(["\r", "\n", '"'], '', $name)];
}

/** Email address that receives store notifications (new orders, messages, reviews). */
function admin_email(): string
{
    foreach ([setting('admin_notify_email'), setting('store_email'), setting('mail_from_email')] as $e) {
        if (filter_var($e, FILTER_VALIDATE_EMAIL)) {
            return $e;
        }
    }
    return '';
}

/**
 * Send an email. $body is plain text (or pass $html for an HTML version).
 * Returns false on failure (see mail_last_error()).
 */
function send_mail(string $to, string $subject, string $body, string $replyTo = '', ?string $html = null): bool
{
    mail_last_error('');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        mail_last_error('Invalid recipient address.');
        return false;
    }
    [$fromEmail, $fromName] = mail_from();
    $subject = str_replace(["\r", "\n"], ' ', $subject);
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $boundary = 'b' . bin2hex(random_bytes(12));
    $headers = [
        'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>',
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . substr(strrchr($fromEmail, '@'), 1) . '>',
        'MIME-Version: 1.0',
    ];
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    if ($html !== null) {
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $content = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($body))
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--$boundary--\r\n";
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';
        $content = chunk_split(base64_encode($body));
    }

    if (setting('mail_driver', 'mail') === 'smtp') {
        try {
            smtp_send($fromEmail, $to, 'To: ' . $to . "\r\nSubject: " . $encodedSubject . "\r\n" . implode("\r\n", $headers), $content);
            return true;
        } catch (Throwable $ex) {
            mail_last_error($ex->getMessage());
            return false;
        }
    }
    if (!function_exists('mail')) {
        mail_last_error('PHP mail() is disabled on this server. Use SMTP instead.');
        return false;
    }
    $ok = @mail($to, $encodedSubject, $content, implode("\r\n", $headers), '-f' . $fromEmail);
    if (!$ok) {
        $ok = @mail($to, $encodedSubject, $content, implode("\r\n", $headers));
    }
    if (!$ok) {
        mail_last_error('PHP mail() could not send the email. Use SMTP with your hosting email account instead.');
    }
    return $ok;
}

/* ---------- SMTP client ---------- */

function smtp_send(string $from, string $to, string $headers, string $body): void
{
    $host = trim(setting('smtp_host'));
    $port = (int) setting('smtp_port', '465') ?: 465;
    $enc = setting('smtp_encryption', 'ssl');
    if ($host === '') {
        throw new RuntimeException('SMTP host is empty.');
    }
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new RuntimeException("Cannot connect to $host:$port ($errstr). Check host, port and encryption.");
    }
    stream_set_timeout($fp, 20);
    $ehloHost = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    try {
        smtp_expect($fp, [220]);
        smtp_cmd($fp, 'EHLO ' . $ehloHost, [250]);
        if ($enc === 'tls') {
            smtp_cmd($fp, 'STARTTLS', [220]);
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
                throw new RuntimeException('STARTTLS failed. Try encryption "SSL" with port 465.');
            }
            smtp_cmd($fp, 'EHLO ' . $ehloHost, [250]);
        }
        $user = setting('smtp_username');
        if ($user !== '') {
            smtp_cmd($fp, 'AUTH LOGIN', [334]);
            smtp_cmd($fp, base64_encode($user), [334]);
            smtp_cmd($fp, base64_encode(setting('smtp_password')), [235], 'Login failed: check the SMTP username (full email address) and password.');
        }
        smtp_cmd($fp, 'MAIL FROM:<' . $from . '>', [250]);
        smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_cmd($fp, 'DATA', [354]);
        // Dot-stuffing: lines starting with "." get an extra "."
        $data = preg_replace('/^\./m', '..', $headers . "\r\n\r\n" . $body);
        smtp_cmd($fp, $data . "\r\n.", [250]);
        smtp_cmd($fp, 'QUIT', [221, 250]);
    } finally {
        fclose($fp);
    }
}

function smtp_cmd($fp, string $cmd, array $ok, string $hint = ''): string
{
    fwrite($fp, $cmd . "\r\n");
    return smtp_expect($fp, $ok, $hint);
}

function smtp_expect($fp, array $ok, string $hint = ''): string
{
    $response = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') {
            break; // last line of the reply ("250 OK"; multi-line replies use "250-")
        }
    }
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $ok, true)) {
        $msg = trim($response) !== '' ? trim($response) : 'No answer from the SMTP server (timeout).';
        throw new RuntimeException(($hint !== '' ? $hint . ' ' : '') . 'Server said: ' . $msg);
    }
    return $response;
}

/* ---------- Email templates ---------- */

/** Wrap content in the store's simple HTML email layout. */
function mail_layout(string $title, string $contentHtml, string $buttonText = '', string $buttonUrl = ''): string
{
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', setting('theme_color')) ? setting('theme_color') : '#e03a3e';
    $button = $buttonText !== ''
        ? '<p style="margin:26px 0 6px"><a href="' . e($buttonUrl) . '" style="background:' . $color . ';color:#fff;text-decoration:none;padding:12px 26px;border-radius:6px;font-weight:bold;display:inline-block">' . e($buttonText) . '</a></p>'
        : '';
    return '<!doctype html><html><body style="margin:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:#222">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:24px 10px"><tr><td align="center">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:10px;overflow:hidden">'
        . '<tr><td style="background:' . $color . ';padding:20px 28px;color:#fff;font-size:22px;font-weight:bold">' . e(setting('store_name', 'Store')) . '</td></tr>'
        . '<tr><td style="padding:28px;font-size:15px;line-height:1.6"><h2 style="margin:0 0 14px;font-size:20px">' . e($title) . '</h2>'
        . $contentHtml . $button . '</td></tr>'
        . '<tr><td style="padding:16px 28px;background:#fafafa;color:#888;font-size:12px">' . e(setting('store_name')) . ' · <a href="' . e(full_url()) . '" style="color:#888">' . e(full_url()) . '</a></td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Send an HTML email made from simple paragraphs (plain text version is generated automatically). */
function send_template(string $to, string $subject, string $title, string $contentHtml, string $buttonText = '', string $buttonUrl = '', string $replyTo = ''): bool
{
    $text = $title . "\n\n" . trim(html_entity_decode(strip_tags(preg_replace('#<(br|/p|/tr|/h\d)[^>]*>#i', "\n", $contentHtml)), ENT_QUOTES, 'UTF-8'));
    if ($buttonText !== '') {
        $text .= "\n\n" . $buttonText . ': ' . $buttonUrl;
    }
    return send_mail($to, $subject, $text, $replyTo, mail_layout($title, $contentHtml, $buttonText, $buttonUrl));
}

/** Items + totals table of an order, for emails. */
function order_email_html(array $order): string
{
    $rows = '';
    foreach (order_items((int) $order['id']) as $it) {
        $variant = implode(' / ', array_filter([$it['size'], $it['color'], $it['options']]));
        $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #eee">' . (int) $it['qty'] . ' × ' . e($it['name'])
            . ($variant !== '' ? '<br><small style="color:#888">' . e($variant) . '</small>' : '') . '</td>'
            . '<td align="right" style="padding:8px 0;border-bottom:1px solid #eee">' . e(money($it['price'] * $it['qty'])) . '</td></tr>';
    }
    $line = fn($label, $value, $bold = false) => '<tr><td style="padding:4px 0' . ($bold ? ';font-weight:bold' : '') . '">' . e($label) . '</td><td align="right" style="padding:4px 0' . ($bold ? ';font-weight:bold' : '') . '">' . e($value) . '</td></tr>';
    $rows .= $line('Subtotal', money($order['subtotal']));
    if ((float) ($order['discount'] ?? 0) > 0) {
        $rows .= $line('Discount (' . $order['coupon_code'] . ')', '-' . money($order['discount']));
    }
    $rows .= $line('Delivery' . (($order['shipping_method'] ?? '') !== '' ? ' (' . $order['shipping_method'] . ')' : ''), (float) $order['shipping'] > 0 ? money($order['shipping']) : 'Free');
    $rows .= $line('Total', money($order['total']), true);
    $addr = implode(', ', array_filter([$order['address'], $order['city'], $order['state'] ?? '', $order['zip'] ?? '', $order['country']], 'strlen'));
    return '<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;margin:14px 0">' . $rows . '</table>'
        . '<p style="font-size:14px;color:#555"><b>Payment:</b> ' . e(payment_method_label($order['payment_method'])) . '<br>'
        . '<b>Deliver to:</b> ' . e($order['customer_name']) . ', ' . e($addr) . '<br><b>Phone:</b> ' . e($order['phone']) . '</p>';
}

/** Emails after an order is confirmed (COD placed or online payment received). Never throws. */
function notify_new_order(array $order): void
{
    try {
        if (setting_on('notify_customer_order') && filter_var($order['email'], FILTER_VALIDATE_EMAIL)) {
            send_template($order['email'], 'Order ' . $order['order_number'] . ' received - ' . setting('store_name'),
                'Thank you for your order, ' . explode(' ', $order['customer_name'])[0] . '!',
                '<p>We have received your order <b>' . e($order['order_number']) . '</b>'
                . ($order['payment_status'] === 'paid' ? ' and your payment' : '') . '. We will let you know when it ships.</p>' . order_email_html($order),
                'Track your order', full_url('track.php'));
        }
        if (setting_on('notify_admin_order') && admin_email() !== '') {
            send_template(admin_email(), 'New order ' . $order['order_number'] . ' - ' . money($order['total']),
                'New order ' . $order['order_number'],
                '<p>' . e($order['customer_name']) . ' placed an order of <b>' . e(money($order['total'])) . '</b>.</p>' . order_email_html($order),
                'Open in admin', full_url('admin/order.php?id=' . (int) $order['id']), $order['email']);
        }
    } catch (Throwable $ex) {
        // Email problems must never block an order.
    }
}

/** Tell the customer their order status changed. */
function notify_order_status(array $order): bool
{
    if (!filter_var($order['email'], FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $labels = order_status_labels();
    $status = $labels[$order['status']] ?? $order['status'];
    $extra = $order['tracking_url'] !== '' ? '<p>Track your package: <a href="' . e($order['tracking_url']) . '">' . e($order['tracking_url']) . '</a></p>' : '';
    return send_template($order['email'], 'Your order ' . $order['order_number'] . ' is ' . strtolower($status),
        'Order ' . $order['order_number'] . ': ' . $status,
        '<p>Hello ' . e(explode(' ', $order['customer_name'])[0]) . ',</p><p>The status of your order <b>' . e($order['order_number']) . '</b> is now <b>' . e($status) . '</b>.</p>' . $extra,
        'View your order', full_url(customer_logged_in() || !empty($order['customer_id']) ? 'account.php?order=' . rawurlencode($order['order_number']) : 'track.php'));
}

<?php
/**
 * Features added in v1.2: statistics, video, watermark, duplicate detection, price-drop alerts,
 * expiry reminders, offers, shops, ID verification, two-factor sign-in, newsletter.
 */
declare(strict_types=1);

/* ------------------------------------------------------------- statistics */

const STAT_COLUMNS = ['views', 'saves', 'messages', 'offers', 'phone_reveals'];

/** Count one event for an ad on today's date. */
function track_stat(int $listingId, string $column): void
{
    if (!in_array($column, STAT_COLUMNS, true) || $listingId <= 0) {
        return;
    }
    $day = date('Y-m-d');
    try {
        if (q("UPDATE listing_stats SET $column = $column + 1 WHERE listing_id = ? AND day = ?", [$listingId, $day])->rowCount() === 0) {
            try {
                db_insert('listing_stats', ['listing_id' => $listingId, 'day' => $day, $column => 1]);
            } catch (Throwable) {
                // another request inserted the row first
                q("UPDATE listing_stats SET $column = $column + 1 WHERE listing_id = ? AND day = ?", [$listingId, $day]);
            }
        }
    } catch (Throwable) {
    }
}

/* ------------------------------------------------------------------ video */

/** Recognises YouTube and Vimeo links. Returns ['provider', 'id'] or null. */
function parse_video_url(string $url): ?array
{
    $url = trim($url);
    if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/|youtube-nocookie\.com/embed/)([A-Za-z0-9_-]{11})~', $url, $m)) {
        return ['provider' => 'youtube', 'id' => $m[1]];
    }
    if (preg_match('~vimeo\.com/(?:video/|channels/[^/]+/|groups/[^/]+/videos/)?(\d{5,12})~', $url, $m)) {
        return ['provider' => 'vimeo', 'id' => $m[1]];
    }
    return null;
}

function normalize_video_url(string $url): string
{
    $v = parse_video_url($url);
    if (!$v) {
        return '';
    }
    return $v['provider'] === 'youtube' ? 'https://www.youtube.com/watch?v=' . $v['id'] : 'https://vimeo.com/' . $v['id'];
}

function video_embed_url(array $v): string
{
    return $v['provider'] === 'youtube'
        ? 'https://www.youtube-nocookie.com/embed/' . $v['id'] . '?autoplay=1&rel=0&modestbranding=1'
        : 'https://player.vimeo.com/video/' . $v['id'] . '?autoplay=1&dnt=1';
}

function video_thumb(array $v): string
{
    return $v['provider'] === 'youtube' ? 'https://i.ytimg.com/vi/' . $v['id'] . '/hqdefault.jpg' : '';
}

/* -------------------------------------------------------------- watermark */

function watermark_enabled(): bool
{
    return setting('watermark_enabled') === '1' && function_exists('imagecolorallocatealpha');
}

/** Draws the watermark (text or uploaded image) onto a GD image. */
function apply_watermark(GdImage $img): void
{
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w < 200 || $h < 150) {
        return;
    }
    $opacity = max(10, min(100, (int) setting('watermark_opacity', '60'))) / 100;
    $pos = setting('watermark_position', 'br');
    $margin = (int) round(min($w, $h) * 0.035);
    $place = function (int $bw, int $bh) use ($w, $h, $margin, $pos): array {
        return match ($pos) {
            'tl' => [$margin, $margin],
            'tr' => [$w - $bw - $margin, $margin],
            'bl' => [$margin, $h - $bh - $margin],
            'center' => [(int) (($w - $bw) / 2), (int) (($h - $bh) / 2)],
            default => [$w - $bw - $margin, $h - $bh - $margin],
        };
    };
    $logoPath = setting('watermark_image') ? APP_ROOT . '/' . setting('watermark_image') : '';
    if ($logoPath && is_file($logoPath) && ($logo = @imagecreatefromstring((string) file_get_contents($logoPath)))) {
        $lw = (int) round($w * 0.22);
        $lh = (int) round(imagesy($logo) * $lw / max(1, imagesx($logo)));
        $scaled = imagecreatetruecolor($lw, $lh);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagecopyresampled($scaled, $logo, 0, 0, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
        // apply opacity pixel by pixel (keeps PNG transparency)
        if ($opacity < 1) {
            for ($x = 0; $x < $lw; $x++) {
                for ($y = 0; $y < $lh; $y++) {
                    $c = imagecolorat($scaled, $x, $y);
                    $a = ($c >> 24) & 0x7F;
                    $na = (int) (127 - (127 - $a) * $opacity);
                    imagesetpixel($scaled, $x, $y, ($na << 24) | ($c & 0xFFFFFF));
                }
            }
        }
        [$x, $y] = $place($lw, $lh);
        imagealphablending($img, true);
        imagecopy($img, $scaled, $x, $y, 0, 0, $lw, $lh);
        imagedestroy($scaled);
        imagedestroy($logo);
        return;
    }
    $text = setting('watermark_text') ?: setting('site_name');
    $font = APP_ROOT . '/assets/fonts/DejaVuSans-Bold.ttf';
    if ($text === '' || !function_exists('imagettftext') || !is_file($font)) {
        return;
    }
    $size = max(10, (int) round($w * 0.032));
    $box = imagettfbbox($size, 0, $font, $text);
    $bw = abs($box[2] - $box[0]);
    $bh = abs($box[7] - $box[1]);
    [$x, $y] = $place($bw, $bh);
    $alpha = (int) round(127 * (1 - $opacity));
    imagealphablending($img, true);
    $shadow = imagecolorallocatealpha($img, 0, 0, 0, min(127, $alpha + 40));
    $white = imagecolorallocatealpha($img, 255, 255, 255, $alpha);
    $off = max(1, (int) round($size / 14));
    imagettftext($img, $size, 0, $x + $off, $y + $bh + $off, $shadow, $font, $text);
    imagettftext($img, $size, 0, $x, $y + $bh, $white, $font, $text);
}

/* ------------------------------------------------------ duplicate detection */

/** 64-bit difference hash of an image file (as 16 hex chars), or '' when it can't be read. */
function image_phash(string $file): string
{
    if (!function_exists('imagecreatetruecolor') || !is_file($file)) {
        return '';
    }
    $src = @imagecreatefromstring((string) file_get_contents($file));
    if (!$src) {
        return '';
    }
    $small = imagecreatetruecolor(9, 8);
    imagecopyresampled($small, $src, 0, 0, 0, 0, 9, 8, imagesx($src), imagesy($src));
    imagedestroy($src);
    $bits = '';
    for ($y = 0; $y < 8; $y++) {
        $prev = null;
        for ($x = 0; $x < 9; $x++) {
            $c = imagecolorat($small, $x, $y);
            $gray = (($c >> 16) & 0xFF) * 0.299 + (($c >> 8) & 0xFF) * 0.587 + ($c & 0xFF) * 0.114;
            if ($prev !== null) {
                $bits .= $gray > $prev ? '1' : '0';
            }
            $prev = $gray;
        }
    }
    imagedestroy($small);
    $hex = '';
    foreach (str_split($bits, 4) as $nibble) {
        $hex .= dechex(bindec($nibble));
    }
    return $hex;
}

function phash_distance(string $a, string $b): int
{
    if (strlen($a) !== 16 || strlen($b) !== 16) {
        return 64;
    }
    $d = 0;
    for ($i = 0; $i < 16; $i++) {
        $x = hexdec($a[$i]) ^ hexdec($b[$i]);
        $d += [0, 1, 1, 2, 1, 2, 2, 3, 1, 2, 2, 3, 2, 3, 3, 4][$x];
    }
    return $d;
}

function normalized_title(string $t): string
{
    $t = mb_strtolower($t);
    $t = preg_replace('~[^\pL\pN]+~u', ' ', $t) ?? '';
    return trim(preg_replace('~\s+~', ' ', $t) ?? '');
}

/**
 * Look for other live/pending ads that use the same title or (almost) the same photos.
 * Returns [[related_listing_id, reason], …].
 */
function find_duplicates(int $listingId): array
{
    $l = q_one('SELECT id, user_id, title FROM listings WHERE id = ?', [$listingId]);
    if (!$l) {
        return [];
    }
    $found = [];
    $norm = normalized_title($l['title']);
    $since = date('Y-m-d H:i:s', time() - 90 * 86400);
    if (mb_strlen($norm) >= 8) {
        foreach (q_all("SELECT id, title FROM listings WHERE id <> ? AND status IN ('active','pending') AND created_at > ? AND (title LIKE ? OR user_id = ?) LIMIT 300",
            [$listingId, $since, '%' . mb_substr($l['title'], 0, 12) . '%', $l['user_id']]) as $o) {
            if (normalized_title($o['title']) === $norm) {
                $found[(int) $o['id']] = 'Same title';
            }
        }
    }
    $mine = array_filter(array_column(q_all('SELECT phash FROM listing_images WHERE listing_id = ?', [$listingId]), 'phash'));
    if ($mine) {
        $others = q_all("SELECT i.listing_id, i.phash FROM listing_images i JOIN listings l ON l.id = i.listing_id
            WHERE i.listing_id <> ? AND i.phash <> '' AND l.status IN ('active','pending') ORDER BY i.id DESC LIMIT 20000", [$listingId]);
        foreach ($others as $o) {
            foreach ($mine as $h) {
                if (phash_distance($h, $o['phash']) <= 5) {
                    $found[(int) $o['listing_id']] = isset($found[(int) $o['listing_id']]) ? 'Same title and photos' : 'Same photo';
                    break;
                }
            }
        }
    }
    $out = [];
    foreach ($found as $id => $why) {
        $out[] = [$id, $why];
    }
    return $out;
}

/**
 * Runs duplicate detection for a saved ad and records flags.
 * Returns true when the ad should be held for review.
 */
function check_duplicates(int $listingId): bool
{
    $mode = setting('duplicate_detection', 'flag');
    if ($mode === 'off') {
        return false;
    }
    $dups = find_duplicates($listingId);
    q("DELETE FROM listing_flags WHERE listing_id = ? AND type = 'duplicate' AND resolved = 0", [$listingId]);
    foreach (array_slice($dups, 0, 5) as [$rid, $why]) {
        db_insert('listing_flags', ['listing_id' => $listingId, 'type' => 'duplicate', 'related_id' => $rid, 'details' => $why, 'resolved' => 0, 'created_at' => now()]);
    }
    return $dups && $mode === 'hold';
}

function listing_duplicate_flags(int $listingId): array
{
    return q_all("SELECT f.*, l.title AS related_title, l.status AS related_status, u.name AS related_owner
        FROM listing_flags f LEFT JOIN listings l ON l.id = f.related_id LEFT JOIN users u ON u.id = l.user_id
        WHERE f.listing_id = ? AND f.type = 'duplicate' AND f.resolved = 0", [$listingId]);
}

/** Fill in fingerprints for photos uploaded before v1.2 (a few per cron run). */
function backfill_phashes(int $limit = 60): int
{
    $n = 0;
    foreach (q_all("SELECT id, thumb FROM listing_images WHERE phash = '' ORDER BY id DESC LIMIT " . (int) $limit) as $img) {
        $h = image_phash(APP_ROOT . '/' . $img['thumb']);
        q('UPDATE listing_images SET phash = ? WHERE id = ?', [$h ?: '-', $img['id']]);
        $n++;
    }
    return $n;
}

/* ---------------------------------------------------------- price drops */

/** Called after an owner edits an ad: records the price change and alerts members who saved it. */
function handle_price_change(array $before, float $newPrice, string $newType): void
{
    $priced = fn(string $t) => in_array($t, ['fixed', 'negotiable'], true);
    if (!$priced($before['price_type']) || !$priced($newType)) {
        return;
    }
    $old = (float) $before['price'];
    if (abs($old - $newPrice) < 0.005) {
        return;
    }
    $id = (int) $before['id'];
    if ($newPrice < $old) {
        q('UPDATE listings SET previous_price = ?, price_changed_at = ? WHERE id = ?', [$old, now(), $id]);
        if ($before['status'] === 'active' && setting('price_drop_alerts', '1') === '1') {
            $l = q_one('SELECT id, title, price, price_type FROM listings WHERE id = ?', [$id]);
            foreach (q_all("SELECT u.name, u.email FROM favorites f JOIN users u ON u.id = f.user_id WHERE f.listing_id = ? AND u.status = 'active' AND u.id <> ?", [$id, $before['user_id']]) as $fan) {
                queue_template('price_drop', $fan['email'], [
                    'name' => $fan['name'], 'ad_title' => $l['title'], 'old_price' => money($old),
                    'new_price' => listing_price($l), 'action_url' => abs_url(listing_url($l)),
                ]);
            }
        }
    } else {
        q('UPDATE listings SET previous_price = NULL, price_changed_at = NULL WHERE id = ?', [$id]);
    }
}

function has_price_drop(array $l): bool
{
    return !empty($l['previous_price']) && (float) $l['previous_price'] > (float) $l['price']
        && !empty($l['price_changed_at']) && strtotime($l['price_changed_at']) > time() - 21 * 86400;
}

/* ------------------------------------------------------- expiry reminders */

function app_secret(): string
{
    return (string) ($GLOBALS['config']['app_key'] ?? '') . '|' . setting('cron_key');
}

function renew_token(array $l): string
{
    return substr(hash_hmac('sha256', 'renew|' . $l['id'] . '|' . $l['expires_at'], app_secret()), 0, 32);
}

function run_expiry_reminders(int $limit = 100): int
{
    $days = (int) setting('expiry_reminder_days', '3');
    if ($days <= 0) {
        return 0;
    }
    $sent = 0;
    $rows = q_all("SELECT l.id, l.title, l.expires_at, u.name, u.email FROM listings l JOIN users u ON u.id = l.user_id
        WHERE l.status = 'active' AND l.expires_at IS NOT NULL AND l.expires_at > ? AND l.expires_at <= ?
        AND l.reminder_sent_at IS NULL AND u.status = 'active' ORDER BY l.expires_at LIMIT " . (int) $limit,
        [now(), date('Y-m-d H:i:s', time() + $days * 86400)]);
    foreach ($rows as $l) {
        q('UPDATE listings SET reminder_sent_at = ? WHERE id = ?', [now(), $l['id']]);
        if (send_template('ad_expiring', $l['email'], [
            'name' => $l['name'], 'ad_title' => $l['title'], 'expires' => format_date($l['expires_at']),
            'action_url' => full_url('renew.php?id=' . $l['id'] . '&t=' . renew_token($l)),
        ])) {
            $sent++;
        }
    }
    return $sent;
}

/** Extends an ad by the configured number of days (owner action or one-click link). */
function renew_listing(int $id): void
{
    q("UPDATE listings SET expires_at = ?, reminder_sent_at = NULL, updated_at = ? WHERE id = ?", [new_expiry(), now(), $id]);
}

/* ---------------------------------------------------------------- offers */

function offers_enabled(): bool
{
    return setting('enable_offers', '1') === '1';
}

function listing_accepts_offers(array $l): bool
{
    return offers_enabled() && in_array($l['price_type'], ['fixed', 'negotiable'], true) && $l['status'] === 'active';
}

function offer_status_label(string $s): string
{
    return match ($s) {
        'pending' => __('Waiting for the seller'),
        'accepted' => __('Accepted'),
        'declined' => __('Declined'),
        'countered' => __('Counter-offer'),
        'withdrawn' => __('Withdrawn'),
        default => ucfirst($s),
    };
}

/** Buyer makes an offer. Returns the conversation id or an error message. */
function make_offer(array $listing, array $buyer, float $amount, string $note = ''): int|string
{
    if (!listing_accepts_offers($listing)) {
        return __('This ad does not accept offers.');
    }
    if ($amount <= 0 || $amount > 999999999999) {
        return __('Enter a valid amount.');
    }
    if ((int) $listing['user_id'] === (int) $buyer['id']) {
        return __('You cannot make an offer on your own ad.');
    }
    $text = __('I would like to offer {amount}.', ['amount' => money($amount)]) . ($note !== '' ? "\n" . mb_substr($note, 0, 1000) : '');
    $convId = post_message((int) $listing['id'], $buyer, $text);
    if (is_string($convId)) {
        return $convId;
    }
    q("UPDATE offers SET status = 'withdrawn', updated_at = ? WHERE listing_id = ? AND buyer_id = ? AND status IN ('pending','countered')", [now(), $listing['id'], $buyer['id']]);
    $offerId = db_insert('offers', [
        'listing_id' => $listing['id'], 'conversation_id' => $convId, 'buyer_id' => $buyer['id'], 'seller_id' => $listing['user_id'],
        'amount' => $amount, 'counter_amount' => null, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $lastMsg = (int) q_val('SELECT MAX(id) FROM messages WHERE conversation_id = ? AND sender_id = ?', [$convId, $buyer['id']]);
    q('UPDATE messages SET offer_id = ? WHERE id = ?', [$offerId, $lastMsg]);
    track_stat((int) $listing['id'], 'offers');
    $seller = q_one('SELECT name, email FROM users WHERE id = ?', [$listing['user_id']]);
    if ($seller) {
        send_template('new_offer', $seller['email'], [
            'name' => $seller['name'], 'buyer_name' => $buyer['name'], 'ad_title' => $listing['title'],
            'amount' => money($amount), 'asking_price' => listing_price($listing), 'action_url' => full_url('dashboard/messages.php?c=' . $convId),
        ]);
    }
    return $convId;
}

/**
 * Seller: accept / decline / counter. Buyer: withdraw / accept_counter / decline_counter.
 * Returns true or an error message.
 */
function respond_offer(array $offer, array $user, string $action, float $counter = 0): bool|string
{
    $uid = (int) $user['id'];
    $isSeller = $uid === (int) $offer['seller_id'];
    $isBuyer = $uid === (int) $offer['buyer_id'];
    $open = in_array($offer['status'], ['pending', 'countered'], true);
    if (!$open || (!$isSeller && !$isBuyer)) {
        return __('This offer can no longer be changed.');
    }
    $amount = (float) $offer['amount'];
    $set = [];
    $msg = '';
    if ($isSeller && $offer['status'] === 'pending' && in_array($action, ['accept', 'decline', 'counter'], true)) {
        if ($action === 'accept') {
            $set = ['status' => 'accepted'];
            $msg = __('Offer accepted: {amount}. Let\'s arrange the details!', ['amount' => money($amount)]);
        } elseif ($action === 'decline') {
            $set = ['status' => 'declined'];
            $msg = __('Offer of {amount} declined.', ['amount' => money($amount)]);
        } else {
            if ($counter <= 0) {
                return __('Enter a valid amount.');
            }
            $set = ['status' => 'countered', 'counter_amount' => $counter];
            $amount = $counter;
            $msg = __('Counter-offer: {amount}.', ['amount' => money($counter)]);
        }
    } elseif ($isBuyer && $action === 'withdraw') {
        $set = ['status' => 'withdrawn'];
        $msg = __('Offer withdrawn.');
    } elseif ($isBuyer && $offer['status'] === 'countered' && in_array($action, ['accept_counter', 'decline_counter'], true)) {
        $amount = (float) $offer['counter_amount'];
        if ($action === 'accept_counter') {
            $set = ['status' => 'accepted', 'amount' => $amount];
            $msg = __('Counter-offer of {amount} accepted.', ['amount' => money($amount)]);
        } else {
            $set = ['status' => 'declined'];
            $msg = __('Counter-offer of {amount} declined.', ['amount' => money($amount)]);
        }
    } else {
        return __('This action is not available.');
    }
    db_update('offers', $set + ['updated_at' => now()], 'id = ?', [$offer['id']]);
    $conv = q_one('SELECT * FROM conversations WHERE id = ?', [$offer['conversation_id']]);
    if ($conv) {
        $res = post_message((int) $offer['listing_id'], $user, $msg, $conv);
        if (!is_string($res)) {
            $last = (int) q_val('SELECT MAX(id) FROM messages WHERE conversation_id = ? AND sender_id = ?', [$conv['id'], $uid]);
            q('UPDATE messages SET offer_id = ? WHERE id = ?', [$offer['id'], $last]);
        }
    }
    $otherId = $isSeller ? (int) $offer['buyer_id'] : (int) $offer['seller_id'];
    $other = q_one('SELECT name, email FROM users WHERE id = ?', [$otherId]);
    $listing = q_one('SELECT title FROM listings WHERE id = ?', [$offer['listing_id']]);
    if ($other && $listing) {
        $words = ['accepted' => __('accepted'), 'declined' => __('declined'), 'countered' => __('countered'), 'withdrawn' => __('withdrew')];
        send_template('offer_update', $other['email'], [
            'name' => $other['name'], 'other_name' => $user['name'], 'ad_title' => $listing['title'],
            'status' => $words[$set['status']] ?? $set['status'], 'amount' => money($amount),
            'action_url' => full_url('dashboard/messages.php?c=' . $offer['conversation_id']),
        ]);
    }
    return true;
}

/* ----------------------------------------------------------------- shops */

function shops_enabled(): bool
{
    return setting('enable_shops', '1') === '1';
}

const WEEK_DAYS = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];

function user_shop(int $userId, bool $activeOnly = true): ?array
{
    static $cache = [];
    $key = $userId . ($activeOnly ? 'a' : '');
    if (!array_key_exists($key, $cache)) {
        $cache[$key] = q_one('SELECT * FROM shops WHERE user_id = ?' . ($activeOnly ? " AND status = 'active'" : ''), [$userId]);
    }
    return $cache[$key];
}

function shop_url(array $shop): string
{
    return clean_urls() ? url('shop/' . rawurlencode($shop['slug'])) : url('shop.php?slug=' . rawurlencode($shop['slug']));
}

function shop_hours(array $shop): array
{
    $h = json_decode((string) $shop['hours'], true);
    $out = [];
    foreach (WEEK_DAYS as $k => $_) {
        $d = is_array($h[$k] ?? null) ? $h[$k] : [];
        $out[$k] = ['closed' => !empty($d['closed']), 'open' => (string) ($d['open'] ?? ''), 'close' => (string) ($d['close'] ?? '')];
    }
    return $out;
}

/** true = open now, false = closed now, null = hours not set. */
function shop_open_now(array $shop): ?bool
{
    $hours = shop_hours($shop);
    if (!array_filter($hours, fn($d) => $d['closed'] || ($d['open'] !== '' && $d['close'] !== ''))) {
        return null;
    }
    $today = $hours[strtolower(date('D'))] ?? null;
    if (!$today || $today['closed'] || $today['open'] === '' || $today['close'] === '') {
        return false;
    }
    $now = date('H:i');
    return $today['close'] > $today['open'] ? ($now >= $today['open'] && $now < $today['close']) : ($now >= $today['open'] || $now < $today['close']);
}

/* ------------------------------------------------------------------ chat */

function quick_replies(): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', setting('quick_replies')) ?: [])));
}

/** Marks the other person's messages as read and resets the unread counter. */
function mark_conversation_read(array $conv, int $uid): void
{
    q('UPDATE messages SET read_at = ? WHERE conversation_id = ? AND sender_id <> ? AND read_at IS NULL', [now(), $conv['id'], $uid]);
    $col = (int) $conv['buyer_id'] === $uid ? 'buyer_unread' : 'seller_unread';
    q("UPDATE conversations SET $col = 0 WHERE id = ?", [$conv['id']]);
}

function render_chat_message(array $m, int $uid, string &$lastDay): string
{
    $out = '';
    $day = format_date($m['created_at']);
    if ($day !== $lastDay) {
        $lastDay = $day;
        $out .= '<div class="day-sep"><span>' . e($day) . '</span></div>';
    }
    $mine = (int) $m['sender_id'] === $uid;
    $cls = 'bubble ' . ($mine ? 'mine' : 'theirs') . ((int) $m['offer_id'] ? ' offer-bubble' : '');
    $out .= '<div class="' . $cls . '" data-msg="' . (int) $m['id'] . '">';
    if ($m['image'] !== '') {
        $out .= '<a class="bubble-img" href="' . e(upload_url($m['image'])) . '" target="_blank" rel="noopener"><img src="' . e(upload_url($m['thumb'] ?: $m['image'])) . '" alt="' . t('Photo') . '" loading="lazy"></a>';
    }
    if ((int) $m['offer_id']) {
        $out .= '<span class="offer-tag">' . icon('hand-coins') . t('Offer') . '</span>';
    }
    if ($m['body'] !== '') {
        $out .= '<p>' . nl2br(e($m['body'])) . '</p>';
    }
    $out .= '<time>' . e(date('H:i', strtotime($m['created_at'])));
    if ($mine) {
        $seen = !empty($m['read_at']);
        $out .= '<span class="tick' . ($seen ? ' seen' : '') . '" title="' . ($seen ? t('Seen') : t('Sent')) . '">' . icon($seen ? 'check-check' : 'check') . '</span>';
    }
    return $out . '</time></div>';
}

/* ------------------------------------------------------ two-factor sign-in */

function base32_encode(string $bin): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function base32_decode(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32) ?? '');
    $bits = '';
    foreach (str_split($b32) as $c) {
        $bits .= str_pad(decbin(strpos($alphabet, $c)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function totp_new_secret(): string
{
    return base32_encode(random_bytes(20));
}

/** RFC 6238 time-based one-time password (30-second steps, 6 digits, SHA-1). */
function totp_code(string $secret, ?int $step = null): string
{
    $step ??= intdiv(time(), 30);
    $hash = hash_hmac('sha1', pack('N2', 0, $step), base32_decode($secret), true);
    $o = ord($hash[19]) & 0x0F;
    $num = ((ord($hash[$o]) & 0x7F) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);
    return str_pad((string) ($num % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Accepts the current code and one step either side (phone clocks drift). */
function totp_verify(string $secret, string $code, int $window = 1): bool
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if ($secret === '' || strlen($code) !== 6) {
        return false;
    }
    $now = intdiv(time(), 30);
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_code($secret, $now + $i), $code)) {
            // never accept the same code twice
            $key = 'totp_used_' . md5($secret);
            if (($_SESSION[$key] ?? 0) >= $now + $i) {
                return false;
            }
            $_SESSION[$key] = $now + $i;
            return true;
        }
    }
    return false;
}

function totp_uri(string $secret, string $account): string
{
    $issuer = setting('site_name');
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?' . http_build_query(['secret' => $secret, 'issuer' => $issuer, 'digits' => 6, 'period' => 30]);
}

/** Returns [plain codes to show once, JSON of hashes to store]. */
function recovery_codes_generate(): array
{
    $plain = [];
    for ($i = 0; $i < 10; $i++) {
        $raw = strtolower(base32_encode(random_bytes(5)));
        $plain[] = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
    }
    return [$plain, json_encode(array_map(fn($c) => password_hash($c, PASSWORD_DEFAULT), $plain))];
}

function use_recovery_code(array $user, string $code): bool
{
    $code = strtolower(trim($code));
    $hashes = json_decode((string) $user['recovery_codes'], true) ?: [];
    foreach ($hashes as $i => $h) {
        if (password_verify($code, $h)) {
            unset($hashes[$i]);
            q('UPDATE users SET recovery_codes = ? WHERE id = ?', [json_encode(array_values($hashes)), $user['id']]);
            return true;
        }
    }
    return false;
}

/**
 * Finish a successful password / social sign-in. Accounts with two-factor on are sent
 * to the code page first. Never returns.
 */
function complete_login(array $user, bool $remember, string $fallback = 'dashboard/', bool $social = false): never
{
    if (!empty($user['totp_enabled'])) {
        session_regenerate_id(true);
        $_SESSION['2fa_pending'] = ['uid' => (int) $user['id'], 'remember' => $remember, 'social' => $social, 'at' => time(), 'fallback' => $fallback];
        redirect('login-2fa.php');
    }
    login_user($user, $remember);
    if ($social) {
        $_SESSION['social_login'] = true;
    }
    if ($user['role'] === 'admin') {
        log_activity('admin.login', $social ? 'Signed in (social)' : 'Signed in', 'user', (int) $user['id']);
    }
    header('Location: ' . intended_url($fallback));
    exit;
}

/* ------------------------------------------------------------- newsletter */

function newsletter_audiences(): array
{
    return [
        'all' => 'All members',
        'with_ads' => 'Members with live ads',
        'without_ads' => 'Members without ads',
        'verified' => 'Verified sellers',
        'shops' => 'Shop owners',
    ];
}

/** SQL condition (on users u) for an audience. Only members who agreed to receive news. */
function newsletter_where(string $audience): string
{
    $base = "u.status = 'active' AND u.newsletter = 1";
    if (setting('require_email_verification') === '1') {
        $base .= ' AND u.email_verified_at IS NOT NULL';
    }
    return $base . match ($audience) {
        'with_ads' => " AND EXISTS (SELECT 1 FROM listings l WHERE l.user_id = u.id AND l.status = 'active')",
        'without_ads' => " AND NOT EXISTS (SELECT 1 FROM listings l WHERE l.user_id = u.id AND l.status = 'active')",
        'verified' => ' AND u.verified = 1',
        'shops' => " AND EXISTS (SELECT 1 FROM shops s WHERE s.user_id = u.id AND s.status = 'active')",
        default => '',
    };
}

function unsubscribe_token(int $uid): string
{
    return substr(hash_hmac('sha256', 'unsub|' . $uid, app_secret()), 0, 24);
}

function unsubscribe_url(int $uid): string
{
    return full_url('unsubscribe.php?u=' . $uid . '&t=' . unsubscribe_token($uid));
}

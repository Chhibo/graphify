<?php
/**
 * Small JSON endpoint used by assets/js/app.js.
 */
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json');

if (input('action') === 'suggest' && !is_post()) {
    $term = mb_substr(input('q'), 0, 60);
    $out = ['ads' => [], 'categories' => []];
    if (mb_strlen($term) >= 2) {
        [$where, $params] = visible_listing_where();
        $like = '%' . $term . '%';
        foreach (q_all(listing_select() . " WHERE $where AND l.title LIKE ? ORDER BY l.featured DESC, l.views DESC LIMIT 6", array_merge($params, [$like])) as $l) {
            $out['ads'][] = [
                'title' => $l['title'], 'url' => listing_url($l), 'price' => listing_price($l),
                'thumb' => $l['cover'] ? upload_url($l['cover']) : '', 'category' => (string) $l['category_name'],
            ];
        }
        $needle = mb_strtolower($term);
        foreach (categories_all() as $cid => $c) {
            if (str_contains(mb_strtolower($c['name']), $needle)) {
                $parent = (int) $c['parent_id'] ? (categories_all()[(int) $c['parent_id']]['name'] ?? '') : '';
                $out['categories'][] = ['name' => $c['name'], 'parent' => $parent, 'url' => category_url($cid)];
            }
            if (count($out['categories']) >= 3) {
                break;
            }
        }
    }
    $out['search_url'] = search_url(['q' => $term]);
    header('Cache-Control: private, max-age=60');
    echo json_encode($out);
    exit;
}

// chat: new messages + read receipts since the last message the browser has
if (input('action') === 'thread' && !is_post()) {
    $uid = user_id();
    $conv = $uid ? q_one('SELECT * FROM conversations WHERE id = ? AND (buyer_id = ? OR seller_id = ?)', [(int) input('c'), $uid, $uid]) : null;
    if (!$conv) {
        http_response_code(404);
        echo json_encode(['ok' => false]);
        exit;
    }
    $after = (int) input('after');
    $new = q_all('SELECT * FROM messages WHERE conversation_id = ? AND id > ? ORDER BY id', [$conv['id'], $after]);
    if ($new) {
        mark_conversation_read($conv, $uid);
    }
    $lastDay = input('day');
    $html = '';
    foreach ($new as $m) {
        $html .= render_chat_message($m, $uid, $lastDay);
    }
    $seen = array_map('intval', array_column(q_all('SELECT id FROM messages WHERE conversation_id = ? AND sender_id = ? AND read_at IS NOT NULL ORDER BY id DESC LIMIT 50', [$conv['id'], $uid]), 'id'));
    echo json_encode(['ok' => true, 'html' => $html, 'last' => $new ? (int) end($new)['id'] : $after, 'day' => $lastDay,
        'seen' => $seen, 'unread' => unread_count(), 'offer_changed' => (bool) array_filter($new, fn($m) => (int) $m['offer_id'] > 0)]);
    exit;
}

if (!is_post()) {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$action = input('action');

if ($action === 'favorite') {
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'login' => url('login.php')]);
        exit;
    }
    $lid = (int) input('id');
    if (!q_val('SELECT id FROM listings WHERE id = ?', [$lid])) {
        http_response_code(404);
        echo json_encode(['ok' => false]);
        exit;
    }
    $existing = q_val('SELECT id FROM favorites WHERE user_id = ? AND listing_id = ?', [user_id(), $lid]);
    if ($existing) {
        q('DELETE FROM favorites WHERE id = ?', [$existing]);
        echo json_encode(['ok' => true, 'saved' => false]);
    } else {
        db_insert('favorites', ['user_id' => user_id(), 'listing_id' => $lid, 'created_at' => now()]);
        track_stat($lid, 'saves');
        echo json_encode(['ok' => true, 'saved' => true]);
    }
    exit;
}

if ($action === 'phone') {
    $lid = (int) input('id');
    if (empty($_SESSION['phone_seen'][$lid])) {
        $_SESSION['phone_seen'][$lid] = 1;
        $owner = (int) q_val('SELECT user_id FROM listings WHERE id = ?', [$lid]);
        if ($owner && $owner !== user_id()) {
            track_stat($lid, 'phone_reveals');
        }
    }
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false]);

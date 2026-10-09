<?php
/**
 * Small JSON endpoint used by assets/js/app.js.
 */
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json');

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
        echo json_encode(['ok' => true, 'saved' => true]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false]);

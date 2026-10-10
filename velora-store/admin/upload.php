<?php
/** Image upload for the visual editor. Returns JSON: {success, files: [url], message}. */
require __DIR__ . '/includes/auth.php';
header('Content-Type: application/json');

if (!current_admin() || !is_post() || !hash_equals(csrf_token(), (string) ($_POST['_csrf'] ?? ''))) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Please log in again.']));
}
$urls = [];
try {
    foreach (files_list($_FILES['files'] ?? null) as $f) {
        $path = upload_image($f);
        if ($path) {
            $urls[] = url($path);
        }
    }
} catch (RuntimeException $ex) {
    exit(json_encode(['success' => false, 'message' => $ex->getMessage()]));
}
echo json_encode(['success' => (bool) $urls, 'files' => $urls, 'message' => $urls ? '' : 'No image received.']);

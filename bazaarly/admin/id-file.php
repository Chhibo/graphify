<?php
/** Streams a private ID document to administrators only. */
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$field = in_array(input('f'), ['doc_front', 'doc_back', 'selfie'], true) ? input('f') : 'doc_front';
$rel = (string) q_val("SELECT $field FROM verification_requests WHERE id = ?", [(int) input('id')]);
$path = realpath(APP_ROOT . '/' . $rel);
$base = realpath(APP_ROOT . '/data/private');
if ($rel === '' || !$path || !$base || !str_starts_with($path, $base) || !is_file($path)) {
    http_response_code(404);
    exit;
}
$info = @getimagesize($path);
header('Content-Type: ' . ($info['mime'] ?? 'application/octet-stream'));
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');
header('Content-Length: ' . filesize($path));
readfile($path);

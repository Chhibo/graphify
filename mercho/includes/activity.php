<?php
/**
 * Activity log: who changed what in the admin, and when (Admin > Activity log, owner only).
 *
 * Every form saved in the admin is logged automatically (see admin_log_request()). Pages can describe
 * the change better with activity('Changed order #1001 to Shipped').
 */

/** Write one line in the log now. */
function log_activity(string $action, string $details = '', ?array $admin = null): void
{
    $admin = $admin ?? (function_exists('current_admin') ? current_admin() : null);
    try {
        db_insert('activity_log', [
            'admin_id' => $admin ? (int) $admin['id'] : null,
            'admin_name' => mb_substr((string) ($admin['name'] ?? ''), 0, 120),
            'action' => mb_substr($action, 0, 255),
            'details' => mb_substr($details, 0, 2000),
            'ip' => client_ip(),
            'created_at' => now(),
        ]);
    } catch (Throwable $ex) {
        // the log must never break the admin
    }
}

/** Describe what this request changed (replaces the automatic description). */
function activity(string $action, string $details = ''): void
{
    $GLOBALS['ACTIVITY'][] = [$action, $details];
}

/** Admin pages: log the request when the page finishes (only form submissions, not page views). */
function admin_log_request(string $pageLabel): void
{
    static $registered = false;
    if ($registered || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $registered = true;
    register_shutdown_function(function () use ($pageLabel) {
        if (!empty($GLOBALS['ACTIVITY_SKIP'])) {
            return;
        }
        if (!empty($GLOBALS['ACTIVITY'])) {
            foreach ($GLOBALS['ACTIVITY'] as [$action, $details]) {
                log_activity($action, $details);
            }
            return;
        }
        // Automatic description: page + button + item id (never the form values: they can hold passwords or keys).
        $what = (string) ($_POST['action'] ?? '');
        $id = (int) ($_POST['id'] ?? ($_GET['id'] ?? 0));
        log_activity($pageLabel . ($what !== '' ? ': ' . str_replace('_', ' ', $what) : ': saved') . ($id ? ' (#' . $id . ')' : ''));
    });
}

/** Delete old lines (Settings > Security: keep the log for X days). */
function activity_log_cleanup(): void
{
    $days = (int) setting('activity_log_days', '180');
    if ($days > 0) {
        q('DELETE FROM activity_log WHERE created_at < ?', [date('Y-m-d H:i:s', time() - $days * 86400)]);
    }
}

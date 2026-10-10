<?php
/**
 * Scheduled jobs: expire old ads, send saved-search alerts, clean up.
 *
 * Run every 15 minutes, either from the command line:
 *     php /path/to/cron.php
 * or by URL (key shown in Admin → Settings → Advanced):
 *     https://your-site.com/cron.php?key=YOUR_KEY
 */
define('ALLOW_IN_MAINTENANCE', true);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
if (PHP_SAPI !== 'cli') {
    $key = setting('cron_key');
    if ($key === '' || !hash_equals($key, (string) ($_GET['key'] ?? ''))) {
        http_response_code(403);
        exit("Invalid cron key.\n");
    }
}
@set_time_limit(300);
foreach (run_cron() as $line) {
    echo $line, "\n";
}
echo "Done at ", now(), "\n";

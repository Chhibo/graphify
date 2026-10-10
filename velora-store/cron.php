<?php
/**
 * Background jobs (abandoned cart reminders). Optional: add a cron job in your hosting panel, every 15 minutes:
 *   curl -s "https://yourstore.com/cron.php?key=YOUR_KEY"   (the full link is shown in Admin > Abandoned carts)
 * Without a cron job, reminders are sent when you open the admin panel.
 */
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: text/plain');
if (!hash_equals(setting('cron_key'), (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Wrong key');
}
$sent = send_abandoned_reminders(30);
set_setting('last_cron_run', (string) time());
echo "OK - reminders sent: $sent\n";

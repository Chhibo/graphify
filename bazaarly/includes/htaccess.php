<?php
/**
 * Creates / repairs the Apache & LiteSpeed .htaccess files.
 * They are hidden files (name starts with a dot), so they are easy to miss when uploading —
 * the site recreates them automatically when they are missing.
 */
declare(strict_types=1);

const HTACCESS_BEGIN = '# BEGIN Bazaarly';
const HTACCESS_END = '# END Bazaarly';

function htaccess_deny_all(): string
{
    return "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n";
}

/** The Bazaarly block for the main .htaccess (between BEGIN/END markers). */
function htaccess_root_block(): string
{
    $base = (BASE_PATH === '' ? '' : BASE_PATH) . '/';
    return HTACCESS_BEGIN . " (managed automatically — edit outside these lines)
# Clean URLs: send anything that is not a real file or folder to router.php
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase $base
  RewriteRule ^router\\.php$ - [L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule . {$base}router.php [L,QSA]
</IfModule>

# Never serve configuration or internal files
<FilesMatch \"^(config\\.php|.*\\.sqlite.*|.*\\.md|\\.git.*)$\">
  <IfModule mod_authz_core.c>
    Require all denied
  </IfModule>
  <IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
  </IfModule>
</FilesMatch>

<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/css \"access plus 1 month\"
  ExpiresByType application/javascript \"access plus 1 month\"
  ExpiresByType image/webp \"access plus 1 year\"
  ExpiresByType image/jpeg \"access plus 1 year\"
  ExpiresByType image/png \"access plus 1 year\"
  ExpiresByType image/svg+xml \"access plus 1 month\"
</IfModule>

<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json image/svg+xml
</IfModule>
" . HTACCESS_END . "\n";
}

function htaccess_uploads(): string
{
    return "# Uploaded files are images only – never execute scripts here
<IfModule mod_mime.c>
  RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .pl .py .cgi
  RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phar
</IfModule>
<FilesMatch \"\\.(?i:php\\d?|phtml|phar|pl|py|cgi|sh|html?|svg)$\">
  <IfModule mod_authz_core.c>
    Require all denied
  </IfModule>
  <IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
  </IfModule>
</FilesMatch>
";
}

/** Relative path => expected content (null = the managed block in the main file). */
function htaccess_targets(): array
{
    return [
        '.htaccess' => null,
        'data/.htaccess' => htaccess_deny_all(),
        'includes/.htaccess' => htaccess_deny_all(),
        'uploads/.htaccess' => htaccess_uploads(),
    ];
}

/**
 * Makes sure every .htaccess file exists. With $repairRoot the managed block in the main
 * .htaccess is (re)written even when the file already exists.
 * Returns [relative path => 'ok' | 'created' | 'updated' | 'failed'].
 */
function ensure_htaccess(bool $repairRoot = false): array
{
    $report = [];
    foreach (htaccess_targets() as $rel => $content) {
        $file = APP_ROOT . '/' . $rel;
        if ($content !== null) {
            $existing = is_file($file) ? (string) @file_get_contents($file) : null;
            if ($existing !== null && !htaccess_is_broken($existing)) {
                $report[$rel] = 'ok';
                continue;
            }
            $report[$rel] = @file_put_contents($file, $content) !== false ? ($existing === null ? 'created' : 'updated') : 'failed';
            continue;
        }
        // main .htaccess: keep anything the owner or host added, manage only our block
        $block = htaccess_root_block();
        $current = is_file($file) ? (string) @file_get_contents($file) : null;
        if ($current !== null && !$repairRoot && !htaccess_is_broken($current) && (str_contains($current, HTACCESS_BEGIN) || str_contains($current, 'router.php'))) {
            $report[$rel] = 'ok';
            continue;
        }
        if ($current === null || trim($current) === '' || str_starts_with(ltrim($current), '# Bazaarly – Apache settings') || htaccess_is_broken($current)) {
            $new = $block;
            $status = 'created';
        } elseif (str_contains($current, HTACCESS_BEGIN) && str_contains($current, HTACCESS_END)) {
            $new = preg_replace('/' . preg_quote(HTACCESS_BEGIN, '/') . '.*?' . preg_quote(HTACCESS_END, '/') . '\R?/s', str_replace('$', '\$', $block), $current);
            $status = 'updated';
        } else {
            // an older Bazaarly file or one created by the host: put our block first
            $new = $block . "\n" . $current;
            $status = 'updated';
        }
        if ($current !== null && $new === $current) {
            $report[$rel] = 'ok';
            continue;
        }
        $report[$rel] = @file_put_contents($file, $new) !== false ? $status : 'failed';
    }
    return $report;
}

/**
 * Bazaarly 1.0 shipped files with one-line <IfModule …>Directive</IfModule> blocks, which Apache rejects
 * with a 500 error. Detect them so they can be replaced.
 */
function htaccess_is_broken(string $content): bool
{
    return (bool) preg_match('~<IfModule[^>\n]*>[ \t]*[A-Za-z][^\n<]*</IfModule>~', $content);
}

/** Cheap check used on every request: are all files present? */
function htaccess_missing(): bool
{
    foreach (array_keys(htaccess_targets()) as $rel) {
        $file = APP_ROOT . '/' . $rel;
        if (!is_file($file) || ($rel !== '.htaccess' && filesize($file) < 2048 && htaccess_is_broken((string) @file_get_contents($file)))) {
            return true;
        }
    }
    return false;
}

function server_software(): string
{
    $s = (string) ($_SERVER['SERVER_SOFTWARE'] ?? '');
    return $s !== '' ? $s : 'unknown';
}

function server_uses_htaccess(): bool
{
    return (bool) preg_match('/apache|litespeed|openlitespeed/i', server_software());
}

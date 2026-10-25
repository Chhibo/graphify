<?php
/**
 * Front router for clean URLs (/ad/12-title, /category/cars, /user/jane, /page/about, /sitemap.xml …).
 * Apache/LiteSpeed reach it through .htaccess, Nginx through try_files, and it also works
 * as the router for PHP's built-in server:  php -S localhost:8000 router.php
 */
declare(strict_types=1);

$uriPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// PHP built-in server (run from the app folder): let it serve real files and scripts itself
if (PHP_SAPI === 'cli-server') {
    if (preg_match('~^/(data|includes)(/|$)|^/config\.php$|\.(sqlite|md)$~i', $uriPath)) {
        http_response_code(403);
        exit('Forbidden');
    }
    $file = __DIR__ . $uriPath;
    if ($uriPath !== '/' && (is_file($file) || is_file(rtrim($file, '/') . '/index.php'))) {
        return false;
    }
    $_SERVER['SCRIPT_NAME'] = '/router.php';
}

// path relative to the folder the script is installed in
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/router.php')), '/.');
$path = trim(substr($uriPath, strlen($base)), '/');

$route = function (string $script, array $get = []): never {
    foreach ($get as $k => $v) {
        $_GET[$k] = $v;
    }
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/router.php')), '/.');
    $_SERVER['SCRIPT_NAME'] = $dir . '/' . $script;
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/' . $script;
    require __DIR__ . '/' . $script;
    exit;
};

if ($path === '' || $path === 'index.php') $route('index.php');
if ($path === 'rewrite-test') {
    header('Content-Type: text/plain');
    exit('ok');
}
if (preg_match('~^ad/(\d+)/card\.jpg$~', $path, $m)) $route('share-card.php', ['id' => $m[1]]);
if (preg_match('~^ad/(\d+)(?:-[^/]*)?/?$~', $path, $m)) $route('listing.php', ['id' => $m[1]]);
if (preg_match('~^category/([^/]+)/?$~', $path, $m)) $route('listings.php', ['category_slug' => $m[1]]);
if (preg_match('~^user/([^/]+)/?$~', $path, $m)) $route('profile.php', ['username' => $m[1]]);
if (preg_match('~^page/([^/]+)/?$~', $path, $m)) $route('page.php', ['slug' => $m[1]]);
if (preg_match('~^shop/([^/]+)/?$~', $path, $m)) $route('shop.php', ['slug' => $m[1]]);
if ($path === 'shops') $route('shops.php');
if ($path === 'ads' || $path === 'search') $route('listings.php');
if ($path === 'categories') $route('categories.php');
if ($path === 'contact') $route('contact.php');
if ($path === 'login') $route('login.php');
if ($path === 'register') $route('register.php');
if ($path === 'sitemap.xml') $route('sitemap.php');
if ($path === 'robots.txt') $route('robots.php');
if ($path === 'manifest.webmanifest') $route('manifest.php');

$route('page.php', ['slug' => '', '_not_found' => '1']);

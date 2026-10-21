<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

const IMPORT_COLUMNS = ['title', 'description', 'category', 'file_url', 'image_url', 'badge', 'featured',
                        'file_size', 'file_format', 'version', 'requirements', 'video_url'];

if (isset($_GET['template'])) {
    csv_download('products-template.csv', IMPORT_COLUMNS, [
        ['50 Canva Instagram Templates', 'Editable templates for posts and stories.', 'Templates', 'https://drive.google.com/your-file-link',
         'https://example.com/cover.jpg', 'NEW', 'yes', '25 MB', 'Canva', '1.0', 'Free Canva account', ''],
    ]);
}

/** Download a cover picture from a link into uploads/. Returns the file name or ''. */
function fetch_image(string $url): string
{
    if (!preg_match('#^https?://#i', $url) || !function_exists('curl_init')) {
        return '';
    }
    $tmp = tempnam(sys_get_temp_dir(), 'img');
    $fh = fopen($tmp, 'wb');
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT => 20, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_NOPROGRESS => false, CURLOPT_PROGRESSFUNCTION => fn($c, $dlTotal, $dl) => $dl > 8 * 1024 * 1024 ? 1 : 0]);
    $ok = curl_exec($ch);
    curl_close($ch);
    fclose($fh);
    $info = $ok ? @getimagesize($tmp) : false;
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$ext) {
        @unlink($tmp);
        return '';
    }
    $name = random_token(8) . '.' . $ext;
    rename($tmp, UPLOADS_DIR . '/' . $name);
    @chmod(UPLOADS_DIR . '/' . $name, 0644);
    return $name;
}

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    @set_time_limit(300);
    $file = $_FILES['csv'] ?? null;
    $result = ['added' => 0, 'skipped' => [], 'categories' => 0];
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $result['skipped'][] = 'Choose a CSV file first.';
    } else {
        $fh = fopen($file['tmp_name'], 'r');
        $head = fgetcsv($fh, 0, ',', '"', '');
        if ($head && isset($head[0])) {
            $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$head[0]); // Excel's BOM
        }
        $map = array_flip(array_map(fn($h) => strtolower(trim((string)$h)), (array)$head));
        if (!isset($map['title'])) {
            $result['skipped'][] = 'The first row must contain column names, including "title". Download the template to see the format.';
        } else {
            $visible = isset($_POST['visible']) ? 1 : 0;
            $catIds = [];
            foreach (db()->query('SELECT id, name FROM categories') as $c) {
                $catIds[mb_strtolower($c['name'])] = (int)$c['id'];
            }
            $line = 1;
            while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
                $line++;
                $get = fn(string $k) => isset($map[$k]) ? trim((string)($row[$map[$k]] ?? '')) : '';
                $title = $get('title');
                if ($title === '' && count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                    continue; // empty line
                }
                if ($title === '') {
                    $result['skipped'][] = "Row $line: no title.";
                    continue;
                }
                $fileUrl = $get('file_url');
                if (!preg_match('#^https?://#i', $fileUrl)) {
                    $result['skipped'][] = "Row $line ($title): file_url must be a link starting with https://";
                    continue;
                }
                $video = $get('video_url');
                if ($video !== '' && youtube_id($video) === '') {
                    $video = '';
                }
                $catId = null;
                if (($catName = $get('category')) !== '') {
                    $key = mb_strtolower($catName);
                    if (!isset($catIds[$key])) {
                        db()->prepare('INSERT INTO categories (name, slug, sort_order, show_on_home, show_in_header) VALUES (?, ?, 99, 1, 1)')
                            ->execute([$catName, unique_slug('categories', slugify($catName))]);
                        $catIds[$key] = (int)db()->lastInsertId();
                        $result['categories']++;
                    }
                    $catId = $catIds[$key];
                }
                $image = $get('image_url') !== '' ? fetch_image($get('image_url')) : '';
                if ($get('image_url') !== '' && $image === '') {
                    $result['skipped'][] = "Row $line ($title): added, but the picture could not be downloaded.";
                }
                db()->prepare('INSERT INTO products (title, slug, description, image, file_url, active, category_id, featured, badge,
                        file_size, file_format, version, requirements, video_url, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([mb_substr($title, 0, 150), unique_slug('products', slugify($title)), $get('description'), $image, $fileUrl, $visible,
                        $catId, in_array(strtolower($get('featured')), ['1', 'yes', 'y', 'true'], true) ? 1 : 0, mb_substr($get('badge'), 0, 20),
                        mb_substr($get('file_size'), 0, 60), mb_substr($get('file_format'), 0, 60), mb_substr($get('version'), 0, 60),
                        mb_substr($get('requirements'), 0, 300), $video, time() + $line]);
                $result['added']++;
            }
        }
        fclose($fh);
    }
}

admin_header('Import products', 'Add many products at once from a spreadsheet',
    '<a class="btn btn-light" href="?template=1">' . icon('download', 16) . ' Download template</a>');
?>
<?php if ($result): ?>
  <div class="card">
    <h2><?= $result['added'] ?> product<?= $result['added'] === 1 ? '' : 's' ?> imported</h2>
    <?php if ($result['categories']): ?><p class="muted"><?= $result['categories'] ?> new categor<?= $result['categories'] === 1 ? 'y' : 'ies' ?> created.</p><?php endif; ?>
    <?php if ($result['skipped']): ?>
      <ul><?php foreach ($result['skipped'] as $msg): ?><li class="bad"><?= e($msg) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if ($result['added']): ?><a class="btn" href="products.php">See products</a><?php endif; ?>
  </div>
<?php endif; ?>
<div class="grid-2">
  <form method="post" enctype="multipart/form-data" class="card form">
    <?= csrf_field() ?>
    <h2>Upload a CSV file</h2>
    <p class="card-sub">Make one in Excel or Google Sheets (File → Download → CSV).</p>
    <label>CSV file <input type="file" name="csv" accept=".csv,text/csv" required></label>
    <label class="check"><input type="checkbox" name="visible" checked> Show imported products in the store right away</label>
    <button class="btn btn-grad"><?= icon('database', 16) ?> Import</button>
  </form>
  <div class="card">
    <h2>Columns</h2>
    <p class="card-sub">Only <b>title</b> and <b>file_url</b> are required. The first row must be the column names.</p>
    <div class="table-wrap"><table>
      <tbody>
        <tr><td><code>title</code></td><td>Product name</td></tr>
        <tr><td><code>file_url</code></td><td>Download link (Google Drive, Dropbox…)</td></tr>
        <tr><td><code>description</code></td><td>Text or HTML</td></tr>
        <tr><td><code>category</code></td><td>Category name (created if new)</td></tr>
        <tr><td><code>image_url</code></td><td>Link to a cover picture (downloaded to your store)</td></tr>
        <tr><td><code>badge</code></td><td>NEW, HOT…</td></tr>
        <tr><td><code>featured</code></td><td>yes / no</td></tr>
        <tr><td><code>file_size</code>, <code>file_format</code>, <code>version</code>, <code>requirements</code></td><td>Details table</td></tr>
        <tr><td><code>video_url</code></td><td>YouTube link</td></tr>
      </tbody>
    </table></div>
  </div>
</div>
<?php admin_footer();

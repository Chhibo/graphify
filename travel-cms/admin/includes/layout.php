<?php
/**
 * Admin panel layout and small form helpers.
 */
if (!defined('APP_ROOT')) {
    exit;
}

function admin_nav(): array
{
    $pending = (int) db_value('SELECT COUNT(*) FROM ' . tbl('bookings') . " WHERE status = 'pending'");
    $pendingReviews = (int) db_value('SELECT COUNT(*) FROM ' . tbl('reviews') . " WHERE status = 'pending'");
    $unread = (int) db_value('SELECT COUNT(*) FROM ' . tbl('messages') . ' WHERE is_read = 0');
    return [
        'Main' => [
            ['dashboard', 'index.php', 'fa-gauge-high', 'Dashboard', 0],
            ['bookings', 'bookings.php', 'fa-calendar-check', 'Bookings', $pending],
            ['calendar', 'calendar.php', 'fa-calendar-days', 'Calendar', 0],
        ],
        'Content' => [
            ['trips', 'tours.php?type=trip', 'fa-plane-departure', 'Trips & Tours', 0],
            ['activities', 'tours.php?type=activity', 'fa-person-hiking', 'Activities', 0],
            ['categories', 'categories.php', 'fa-layer-group', 'Categories', 0],
            ['posts', 'posts.php', 'fa-newspaper', 'Blog Posts', 0],
            ['pages', 'pages.php', 'fa-file-lines', 'Pages', 0],
            ['reviews', 'reviews.php', 'fa-star', 'Reviews', $pendingReviews],
            ['messages', 'messages.php', 'fa-envelope', 'Messages', $unread],
        ],
        'System' => [
            ['settings', 'settings.php', 'fa-sliders', 'Settings', 0],
            ['users', 'users.php', 'fa-users-gear', 'Administrators', 0],
            ['profile', 'profile.php', 'fa-user-lock', 'My Profile', 0],
        ],
    ];
}

function admin_header(string $title, string $active = ''): void
{
    $user = current_admin();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> · Admin · <?= e(setting('site_name', 'Travel CMS')) ?></title>
    <meta name="robots" content="noindex">
    <?php require APP_ROOT . '/includes/tailwind.php'; ?>
    <style>
        .inp{width:100%;background:#f8fafc;border:1px solid #e2e8f0;border-radius:.75rem;padding:.55rem .8rem;font-size:.875rem}
        .inp:focus{outline:none;box-shadow:0 0 0 2px #14b8a6;background:#fff}
        .lbl{display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#475569;margin-bottom:.3rem}
        .btn{display:inline-flex;align-items:center;gap:.4rem;padding:.55rem 1rem;border-radius:.75rem;font-weight:700;font-size:.8rem;transition:all .15s}
        .btn-primary{background:#0d9488;color:#fff}.btn-primary:hover{background:#0f766e}
        .btn-accent{background:#f97316;color:#fff}.btn-accent:hover{background:#ea580c}
        .btn-light{background:#f1f5f9;color:#334155}.btn-light:hover{background:#e2e8f0}
        .btn-danger{background:#fff1f2;color:#e11d48}.btn-danger:hover{background:#ffe4e6}
        .card{background:#fff;border:1px solid #f1f5f9;border-radius:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,.05)}
        table.tbl{width:100%;font-size:.85rem}
        table.tbl th{text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;padding:.75rem 1rem;background:#f8fafc;border-bottom:1px solid #f1f5f9}
        table.tbl td{padding:.75rem 1rem;border-bottom:1px solid #f1f5f9;vertical-align:middle}
        table.tbl tr:hover td{background:#fafafa}
        .help{font-size:.72rem;color:#94a3b8;margin-top:.25rem}
    </style>
</head>
<body class="bg-slate-100 font-sans text-slate-800 antialiased">
<div class="flex min-h-screen">
    <aside id="adminSidebar" class="fixed lg:sticky top-0 left-0 z-40 h-screen w-64 bg-slate-900 text-slate-300 flex-shrink-0 overflow-y-auto -translate-x-full lg:translate-x-0 transition-transform">
        <div class="h-16 flex items-center px-5 border-b border-slate-800">
            <div class="w-9 h-9 rounded-lg bg-gradient-to-tr from-brand-600 to-accent-500 flex items-center justify-center text-white mr-2"><i class="fa-solid fa-compass"></i></div>
            <span class="font-serif font-bold text-xl text-white"><?= e(setting('logo_text_1', 'Wander')) ?><span class="text-accent-400"><?= e(setting('logo_text_2', 'Luxe')) ?></span></span>
        </div>
        <nav class="p-3 text-sm">
            <?php foreach (admin_nav() as $group => $items): ?>
                <p class="px-3 mt-4 mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-500"><?= e($group) ?></p>
                <?php foreach ($items as [$key, $href, $icon, $label, $badge]): ?>
                    <a href="<?= e(url('admin/' . $href)) ?>" class="flex items-center justify-between px-3 py-2.5 rounded-xl mb-0.5 <?= $active === $key ? 'bg-brand-600 text-white' : 'hover:bg-slate-800 hover:text-white' ?>">
                        <span><i class="fa-solid <?= $icon ?> w-6"></i><?= e($label) ?></span>
                        <?php if ($badge): ?><span class="bg-accent-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $badge ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div id="sidebarBackdrop" class="fixed inset-0 bg-slate-900/50 z-30 hidden lg:hidden" onclick="toggleSidebar()"></div>

    <div class="flex-1 min-w-0 flex flex-col">
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sm:px-6 sticky top-0 z-20">
            <div class="flex items-center">
                <button type="button" class="lg:hidden mr-3 text-xl text-slate-600" onclick="toggleSidebar()" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
                <h1 class="font-bold text-lg text-slate-900 truncate"><?= e($title) ?></h1>
            </div>
            <div class="flex items-center space-x-3 text-sm">
                <a href="<?= e(url()) ?>" target="_blank" class="btn btn-light hidden sm:inline-flex"><i class="fa-solid fa-arrow-up-right-from-square"></i> View site</a>
                <span class="hidden md:inline text-slate-500"><?= e($user['name'] ?? '') ?></span>
                <a href="<?= e(url('admin/logout.php')) ?>" class="btn btn-light" title="Log out"><i class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        </header>
        <main class="p-4 sm:p-6 lg:p-8 flex-1">
            <?php foreach (get_flashes() as $f): ?>
                <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium flex items-center <?= $f['type'] === 'error' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' ?>">
                    <i class="fa-solid <?= $f['type'] === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check' ?> mr-2"></i><?= e($f['message']) ?>
                </div>
            <?php endforeach; ?>
    <?php
}

function admin_footer(): void
{
    ?>
        </main>
        <footer class="px-8 py-4 text-xs text-slate-400">Travel CMS v<?= APP_VERSION ?> · Pay-On-Arrival Reservation System</footer>
    </div>
</div>
<script>
function toggleSidebar() {
    document.getElementById('adminSidebar').classList.toggle('-translate-x-full');
    document.getElementById('sidebarBackdrop').classList.toggle('hidden');
}
document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('submit', function (e) { if (!confirm(el.getAttribute('data-confirm'))) e.preventDefault(); });
});
document.querySelectorAll('[data-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
        var img = document.getElementById(input.getAttribute('data-preview'));
        if (img && input.files && input.files[0]) { img.src = URL.createObjectURL(input.files[0]); img.classList.remove('hidden'); }
    });
});
</script>
</body>
</html>
    <?php
}

/* ---------------- Form helpers ---------------- */

function f_input(string $label, string $name, $value = '', string $type = 'text', string $extra = '', string $help = ''): void
{
    echo '<div><label class="lbl" for="f_' . e($name) . '">' . e($label) . '</label>'
        . '<input class="inp" type="' . e($type) . '" id="f_' . e($name) . '" name="' . e($name) . '" value="' . e($value) . '" ' . $extra . '>'
        . ($help ? '<p class="help">' . e($help) . '</p>' : '') . '</div>';
}

function f_textarea(string $label, string $name, $value = '', int $rows = 4, string $help = '', string $extra = ''): void
{
    echo '<div><label class="lbl" for="f_' . e($name) . '">' . e($label) . '</label>'
        . '<textarea class="inp" id="f_' . e($name) . '" name="' . e($name) . '" rows="' . $rows . '" ' . $extra . '>' . e($value) . '</textarea>'
        . ($help ? '<p class="help">' . e($help) . '</p>' : '') . '</div>';
}

function f_select(string $label, string $name, array $options, $value = '', string $help = ''): void
{
    echo '<div><label class="lbl" for="f_' . e($name) . '">' . e($label) . '</label><select class="inp" id="f_' . e($name) . '" name="' . e($name) . '">';
    foreach ($options as $k => $v) {
        echo '<option value="' . e($k) . '"' . ((string) $k === (string) $value ? ' selected' : '') . '>' . e($v) . '</option>';
    }
    echo '</select>' . ($help ? '<p class="help">' . e($help) . '</p>' : '') . '</div>';
}

function f_check(string $label, string $name, bool $checked, string $help = ''): void
{
    echo '<label class="flex items-start space-x-2 text-sm cursor-pointer"><input type="hidden" name="' . e($name) . '" value="0">'
        . '<input type="checkbox" class="mt-1 rounded" name="' . e($name) . '" value="1"' . ($checked ? ' checked' : '') . '>'
        . '<span><span class="font-semibold">' . e($label) . '</span>' . ($help ? '<span class="block help">' . e($help) . '</span>' : '') . '</span></label>';
}

/** Image field: upload a file or paste an external URL. */
function f_image(string $label, string $name, string $value = ''): void
{
    $id = 'prev_' . $name;
    ?>
    <div>
        <label class="lbl"><?= e($label) ?></label>
        <img id="<?= e($id) ?>" src="<?= e($value ? img_url($value) : '') ?>" alt="" class="<?= $value ? '' : 'hidden' ?> w-full max-w-xs h-36 object-cover rounded-xl border border-slate-200 mb-2">
        <input type="file" name="<?= e($name) ?>_file" accept="image/*" data-preview="<?= e($id) ?>" class="block w-full text-sm text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
        <input class="inp mt-2" type="text" name="<?= e($name) ?>" value="<?= e($value) ?>" placeholder="…or paste an image URL (https://...)">
        <p class="help">JPG, PNG, WEBP or GIF up to 5 MB. Uploading a file replaces the URL.</p>
    </div>
    <?php
}

/** Resolve an image field submitted by f_image(): new upload wins, then the text value. */
function resolve_image(string $name, string $current = ''): string
{
    $uploaded = upload_image($name . '_file');
    $value = $uploaded ?? trim((string) ($_POST[$name] ?? ''));
    if ($current !== '' && $current !== $value) {
        delete_upload($current);
    }
    return $value;
}

function pagination(int $page, int $pages, callable $link): void
{
    if ($pages <= 1) {
        return;
    }
    echo '<nav class="flex flex-wrap gap-1 mt-4">';
    for ($i = 1; $i <= $pages; $i++) {
        $cls = $i === $page ? 'bg-brand-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50';
        echo '<a href="' . e($link($i)) . '" class="w-9 h-9 flex items-center justify-center rounded-lg text-xs font-bold ' . $cls . '">' . $i . '</a>';
    }
    echo '</nav>';
}

<?php
require __DIR__ . '/includes/auth.php';
require_permission('settings');

$langs = languages();
$translate = isset($langs[$_GET['translate'] ?? '']) ? $_GET['translate'] : '';
$self = 'admin/languages.php' . ($translate ? '?translate=' . $translate : '');
$known = [
    'ar' => ['العربية', 'rtl'], 'en' => ['English', 'ltr'], 'fr' => ['Français', 'ltr'], 'es' => ['Español', 'ltr'], 'de' => ['Deutsch', 'ltr'],
    'it' => ['Italiano', 'ltr'], 'pt' => ['Português', 'ltr'], 'tr' => ['Türkçe', 'ltr'], 'ru' => ['Русский', 'ltr'], 'zh' => ['中文', 'ltr'],
    'ja' => ['日本語', 'ltr'], 'nl' => ['Nederlands', 'ltr'], 'he' => ['עברית', 'rtl'], 'fa' => ['فارسی', 'rtl'], 'ur' => ['اردو', 'rtl'],
    'hi' => ['हिन्दी', 'ltr'], 'id' => ['Bahasa Indonesia', 'ltr'], 'th' => ['ไทย', 'ltr'],
];

/** All interface texts of the website (English source => used as translation key). */
function interface_strings(): array
{
    $list = include APP_ROOT . '/lang/strings.php';
    return is_array($list) ? $list : [];
}

function save_languages(array $langs): void
{
    save_setting('languages', json_encode(array_values($langs), JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf($self);
    $action = post('action');
    $code = strtolower(post('code'));
    if ($action === 'add') {
        if (!preg_match('/^[a-z]{2,3}(-[a-z]{2,4})?$/', $code)) {
            flash('error', 'Use a 2-letter language code such as fr, de or ar.');
        } elseif (isset($langs[$code])) {
            flash('error', 'This language already exists.');
        } else {
            $langs[$code] = ['code' => $code, 'name' => post('name') ?: ($known[$code][0] ?? strtoupper($code)), 'dir' => post('dir') === 'rtl' ? 'rtl' : 'ltr', 'enabled' => false];
            save_languages($langs);
            flash('success', 'Language added. Translate your content, then enable it so visitors can choose it.');
        }
    } elseif (isset($langs[$code])) {
        switch ($action) {
            case 'toggle':
                if ($code === default_lang()) {
                    flash('error', 'The default language is always enabled.');
                    break;
                }
                $langs[$code]['enabled'] = !$langs[$code]['enabled'];
                save_languages($langs);
                flash('success', $langs[$code]['name'] . ($langs[$code]['enabled'] ? ' is now visible on the website.' : ' is now hidden from visitors.'));
                break;
            case 'default':
                $langs[$code]['enabled'] = true;
                save_languages($langs);
                save_setting('default_language', $code);
                flash('success', $langs[$code]['name'] . ' is now the default language.');
                break;
            case 'update':
                $langs[$code]['name'] = mb_substr(post('name'), 0, 40) ?: $langs[$code]['name'];
                $langs[$code]['dir'] = post('dir') === 'rtl' ? 'rtl' : 'ltr';
                save_languages($langs);
                flash('success', 'Language updated.');
                break;
            case 'delete':
                if ($code === default_lang()) {
                    flash('error', 'You cannot delete the default language.');
                    break;
                }
                unset($langs[$code]);
                save_languages($langs);
                db_exec('DELETE FROM ' . tbl('translations') . ' WHERE lang = ?', [$code]);
                flash('success', 'Language and its translations deleted.');
                break;
            case 'strings':
                $pack = language_pack($code);
                $n = 0;
                foreach ((array) ($_POST['s'] ?? []) as $key => $value) {
                    if (!preg_match('/^[a-f0-9]{32}$/', $key)) {
                        continue;
                    }
                    $value = trim((string) $value);
                    $source = (string) ($_POST['src'][$key] ?? '');
                    // Only store real changes; the language pack and the English source are the fallbacks.
                    if ($value === '' || $value === ($pack[$source] ?? null) || ($value === $source && !isset($pack[$source]))) {
                        db_exec('DELETE FROM ' . tbl('translations') . " WHERE lang = ? AND type = 'ui' AND field = ?", [$code, $key]);
                    } else {
                        db_exec('INSERT INTO ' . tbl('translations') . " (lang, type, item_id, field, value) VALUES (?, 'ui', 0, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)", [$code, $key, $value]);
                        $n++;
                    }
                }
                flash('success', 'Interface texts saved (' . $n . ' custom).');
                break;
        }
    }
    if ($action === 'detect') {
        save_setting('detect_browser_language', post('detect_browser_language') === '1' ? '1' : '0');
        flash('success', 'Saved.');
    }
    redirect($self);
}

$strings = interface_strings();
$total = count($strings);
admin_header($translate ? 'Translate interface: ' . $langs[$translate]['name'] : 'Languages', 'languages');

if ($translate):
    $pack = language_pack($translate);
    $over = ui_overrides($translate);
    $done = 0;
    foreach ($strings as $s) {
        if (isset($over[md5($s)]) || isset($pack[$s])) {
            $done++;
        }
    }
    ?>
    <a href="<?= e(url('admin/languages.php')) ?>" class="text-xs font-bold text-slate-500 hover:text-brand-600"><i class="fa-solid fa-arrow-left mr-1"></i> Languages</a>
    <div class="flex flex-wrap items-center justify-between gap-3 my-4">
        <p class="text-sm text-slate-600">Buttons, labels and messages of the website. <?= $translate === 'en' ? 'Change any English wording here.' : ($done . ' of ' . $total . ' texts translated' . ($pack ? ' (built-in ' . e($langs[$translate]['name']) . ' translation included).' : '.')) ?> Empty fields use the <?= $pack ? 'built-in translation' : 'English text' ?>.</p>
        <input id="strSearch" class="inp !w-72" placeholder="Search texts…">
    </div>
    <form method="post" class="card overflow-hidden">
        <?= csrf_field() ?><input type="hidden" name="action" value="strings"><input type="hidden" name="code" value="<?= e($translate) ?>">
        <div class="divide-y divide-slate-100">
            <?php foreach ($strings as $s): $k = md5($s); $packVal = $pack[$s] ?? ''; ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 str-row">
                    <div class="text-sm text-slate-700 pt-2"><?= e($s) ?><?php if (strpos($s, '%') !== false): ?> <span class="text-[10px] text-amber-600">(keep %s / %d)</span><?php endif; ?></div>
                    <input type="hidden" name="src[<?= $k ?>]" value="<?= e($s) ?>">
                    <input class="inp" dir="<?= lang_dir($translate) ?>" name="s[<?= $k ?>]" value="<?= e($over[$k] ?? '') ?>" placeholder="<?= e($packVal ?: $s) ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <div class="p-4 border-t border-slate-100 flex justify-end sticky bottom-0 bg-white"><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save texts</button></div>
    </form>
    <script>
    document.getElementById('strSearch').addEventListener('input', function () {
        var q = this.value.toLowerCase();
        document.querySelectorAll('.str-row').forEach(function (r) {
            var text = r.textContent.toLowerCase() + ' ' + r.querySelector('input.inp').value.toLowerCase();
            r.style.display = !q || text.indexOf(q) !== -1 ? '' : 'none';
        });
    });
    </script>
<?php else: ?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-4">
        <?php foreach ($langs as $l):
            $isDefault = $l['code'] === default_lang();
            $pack = language_pack($l['code']);
            $contentCount = (int) db_value('SELECT COUNT(*) FROM ' . tbl('translations') . " WHERE lang = ? AND type NOT IN ('ui', 'template')", [$l['code']]); ?>
            <div class="card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl bg-brand-100 text-brand-700 font-bold flex items-center justify-center uppercase"><?= e($l['code']) ?></div>
                        <div>
                            <p class="font-bold"><?= e($l['name']) ?>
                                <?php if ($isDefault): ?><span class="ml-1 px-2 py-0.5 rounded-full bg-brand-600 text-white text-[10px] font-bold">DEFAULT</span><?php endif; ?>
                                <?php if ($l['dir'] === 'rtl'): ?><span class="ml-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold">RIGHT-TO-LEFT</span><?php endif; ?>
                                <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold <?= $l['enabled'] || $isDefault ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>"><?= $l['enabled'] || $isDefault ? 'VISIBLE' : 'HIDDEN' ?></span>
                            </p>
                            <p class="text-xs text-slate-500">
                                <?= $isDefault ? 'Main language of your content.' : $contentCount . ' content translations' ?>
                                · Interface: <?= $l['code'] === 'en' ? 'English (built in)' : ($pack ? 'built-in translation included' : 'translate it yourself') ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <a href="<?= e(url('admin/languages.php?translate=' . $l['code'])) ?>" class="btn btn-light !py-1.5"><i class="fa-solid fa-language"></i> Interface texts</a>
                        <?php if (!$isDefault): ?>
                            <a href="<?= e(url('admin/settings.php?tab=home&lang=' . $l['code'])) ?>" class="btn btn-light !py-1.5"><i class="fa-solid fa-sliders"></i> Site texts</a>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="code" value="<?= e($l['code']) ?>"><button name="action" value="toggle" class="btn <?= $l['enabled'] ? 'btn-light' : 'btn-primary' ?> !py-1.5"><?= $l['enabled'] ? 'Hide' : 'Enable' ?></button></form>
                            <form method="post" data-confirm="Make <?= e($l['name']) ?> the default language? The main fields of your content (titles, descriptions) are then considered to be in this language."><?= csrf_field() ?><input type="hidden" name="code" value="<?= e($l['code']) ?>"><button name="action" value="default" class="btn btn-light !py-1.5">Set default</button></form>
                            <form method="post" data-confirm="Delete <?= e($l['name']) ?> and all its translations?"><?= csrf_field() ?><input type="hidden" name="code" value="<?= e($l['code']) ?>"><button name="action" value="delete" class="btn btn-danger !py-1.5 !px-2.5"><i class="fa-solid fa-trash"></i></button></form>
                        <?php endif; ?>
                    </div>
                </div>
                <form method="post" class="flex flex-wrap items-end gap-2 mt-4 pt-4 border-t border-slate-100">
                    <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="code" value="<?= e($l['code']) ?>">
                    <div><label class="lbl">Name shown to visitors</label><input class="inp !w-48" name="name" value="<?= e($l['name']) ?>"></div>
                    <div><label class="lbl">Direction</label><select class="inp !w-44" name="dir"><option value="ltr">Left to right</option><option value="rtl" <?= $l['dir'] === 'rtl' ? 'selected' : '' ?>>Right to left</option></select></div>
                    <button class="btn btn-light"><i class="fa-solid fa-check"></i></button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="space-y-6">
        <form method="post" class="card p-6 space-y-4">
            <?= csrf_field() ?><input type="hidden" name="action" value="add">
            <h2 class="font-bold">Add a language</h2>
            <div><label class="lbl">Language</label>
                <select class="inp" name="code" onchange="var o=this.options[this.selectedIndex];this.form.name.value=o.dataset.name||'';this.form.dir.value=o.dataset.dir||'ltr';">
                    <option value="">— Choose —</option>
                    <?php foreach ($known as $code => [$name, $dir]): if (isset($langs[$code])) continue; ?>
                        <option value="<?= $code ?>" data-name="<?= e($name) ?>" data-dir="<?= $dir ?>"><?= e($name) ?> (<?= $code ?>)<?= is_file(APP_ROOT . '/lang/' . $code . '.php') ? ' ✓' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label class="lbl">Name shown to visitors</label><input class="inp" name="name"></div>
            <div><label class="lbl">Direction</label><select class="inp" name="dir"><option value="ltr">Left to right</option><option value="rtl">Right to left (Arabic, Hebrew…)</option></select></div>
            <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-plus"></i> Add language</button>
            <p class="help">✓ = built-in interface translation included. Other languages start in English: translate them under "Interface texts".</p>
        </form>
        <form method="post" class="card p-6 space-y-3">
            <?= csrf_field() ?><input type="hidden" name="action" value="detect">
            <?php f_check("Use the visitor's browser language on the first visit", 'detect_browser_language', setting('detect_browser_language', '0') === '1', 'Otherwise visitors start in the default language and choose another with the globe menu.'); ?>
            <button class="btn btn-light">Save</button>
        </form>
        <div class="card p-6 text-sm text-slate-600 space-y-2">
            <h2 class="font-bold text-slate-900">How to translate your website</h2>
            <ol class="list-decimal ml-4 space-y-1">
                <li>Add the language (it stays hidden until you enable it).</li>
                <li>Translate your trips, destinations, blog posts, pages, FAQs and categories: each edit page has a <b>Translations</b> box.</li>
                <li>Translate homepage and footer texts in <b>Site texts</b>, and emails/WhatsApp in <a class="text-brand-600 underline" href="<?= e(url('admin/templates.php')) ?>">Message Templates</a>.</li>
                <li>Click <b>Enable</b>. Visitors switch language with the globe icon in the header.</li>
            </ol>
            <p class="help">Anything not translated is shown in the default language.</p>
        </div>
    </div>
</div>
<?php endif; ?>
<?php admin_footer(); ?>

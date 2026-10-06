<?php
/**
 * Reusable card templates matching the original WanderLuxe design.
 */
if (!defined('APP_ROOT')) {
    exit;
}

function tour_link(array $t): string
{
    return url('tour.php?slug=' . urlencode($t['slug']));
}

function trip_card(array $trip): void
{
    ?>
    <div data-category="<?= e($trip['category_slug'] ?? '') ?>" class="bg-white rounded-3xl overflow-hidden shadow-xl border border-slate-100 flex flex-col group hover:-translate-y-1.5 transition-all duration-300">
        <a href="<?= e(tour_link($trip)) ?>" class="relative h-56 overflow-hidden block">
            <img src="<?= e(img_url($trip['image'])) ?>" alt="<?= e($trip['title']) ?>" loading="lazy" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
            <div class="absolute top-4 left-4 bg-white/90 backdrop-blur-md px-3 py-1 rounded-full text-xs font-bold text-slate-800 shadow">
                <i class="fa-solid fa-location-dot text-brand-500 mr-1"></i> <?= e($trip['destination']) ?>
            </div>
            <div class="absolute bottom-4 right-4 bg-accent-500 text-white font-extrabold text-sm px-3 py-1.5 rounded-xl shadow-lg">
                <?= e(money($trip['price'])) ?> <span class="text-[10px] font-normal opacity-90">/ person</span>
            </div>
        </a>
        <div class="p-6 flex-1 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-xs text-slate-500 mb-2">
                    <span class="font-semibold text-brand-600 uppercase tracking-wider"><?= e($trip['category_name'] ?? '') ?></span>
                    <span><i class="fa-solid fa-clock mr-1"></i> <?= e($trip['duration']) ?></span>
                </div>
                <h3 class="font-serif font-bold text-xl text-slate-900 mb-2 line-clamp-1"><a href="<?= e(tour_link($trip)) ?>" class="hover:text-brand-600"><?= e($trip['title']) ?></a></h3>
                <p class="text-xs text-slate-600 line-clamp-2 mb-4"><?= e($trip['short_description']) ?></p>
            </div>
            <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                <div class="flex items-center text-xs font-bold text-amber-500">
                    <i class="fa-solid fa-star mr-1"></i> <?= e(number_format((float) $trip['rating'], 1)) ?>
                    <span class="text-slate-400 font-normal ml-1">(<?= (int) $trip['reviews_count'] ?>)</span>
                </div>
                <div class="flex space-x-2">
                    <a href="<?= e(tour_link($trip)) ?>" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">Details</a>
                    <button type="button" onclick="quickBook(<?= (int) $trip['id'] ?>)" class="px-4 py-2 bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold rounded-xl shadow-md transition">Book Now</button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function activity_card(array $act): void
{
    ?>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-md flex flex-col justify-between hover:border-brand-500 transition duration-300">
        <div>
            <a href="<?= e(tour_link($act)) ?>" class="relative h-36 rounded-xl overflow-hidden mb-4 block">
                <img src="<?= e(img_url($act['image'])) ?>" alt="<?= e($act['title']) ?>" loading="lazy" class="w-full h-full object-cover">
                <span class="absolute top-2 right-2 bg-slate-900/80 text-white text-xs font-bold px-2.5 py-1 rounded-lg"><?= e(money($act['price'])) ?></span>
            </a>
            <span class="text-[10px] font-bold text-accent-500 uppercase tracking-widest block mb-1">
                <i class="fa-solid <?= e($act['icon'] ?: 'fa-compass') ?> mr-1"></i> <?= e($act['duration']) ?>
            </span>
            <h4 class="font-bold text-base text-slate-900 mb-1 line-clamp-1"><a href="<?= e(tour_link($act)) ?>" class="hover:text-brand-600"><?= e($act['title']) ?></a></h4>
            <p class="text-xs text-slate-500 line-clamp-2 mb-4"><?= e($act['short_description']) ?></p>
        </div>
        <button type="button" onclick="quickBook(<?= (int) $act['id'] ?>)" class="w-full py-2 bg-slate-100 hover:bg-brand-600 hover:text-white text-slate-800 font-bold text-xs rounded-xl transition text-center">
            Reserve Excursion
        </button>
    </div>
    <?php
}

function post_card(array $b): void
{
    $link = url('post.php?slug=' . urlencode($b['slug']));
    ?>
    <article class="bg-white rounded-3xl overflow-hidden shadow-lg border border-slate-100 flex flex-col">
        <a href="<?= e($link) ?>" class="h-48 overflow-hidden relative block">
            <img src="<?= e(img_url($b['image'])) ?>" alt="<?= e($b['title']) ?>" loading="lazy" class="w-full h-full object-cover hover:scale-105 transition duration-500">
            <?php if ($b['tag']): ?><span class="absolute top-3 left-3 bg-brand-600 text-white text-[10px] font-bold px-2.5 py-1 rounded-md uppercase tracking-wider"><?= e($b['tag']) ?></span><?php endif; ?>
        </a>
        <div class="p-6 flex-1 flex flex-col justify-between">
            <div>
                <div class="flex items-center space-x-2 text-xs text-slate-400 mb-2">
                    <span><?= e(format_date($b['published_at'])) ?></span>
                    <?php if ($b['read_time']): ?><span>•</span><span><?= e($b['read_time']) ?></span><?php endif; ?>
                </div>
                <h3 class="font-serif font-bold text-lg text-slate-900 mb-3 line-clamp-2 leading-snug"><a href="<?= e($link) ?>" class="hover:text-brand-600"><?= e($b['title']) ?></a></h3>
                <p class="text-xs text-slate-600 line-clamp-3 mb-4"><?= e($b['excerpt'] ?: excerpt($b['content'])) ?></p>
            </div>
            <a href="<?= e($link) ?>" class="text-xs font-bold text-brand-600 hover:underline inline-flex items-center">
                Read Story <i class="fa-solid fa-arrow-right ml-1 text-[10px]"></i>
            </a>
        </div>
    </article>
    <?php
}

function review_card(array $r): void
{
    $avatar = $r['avatar'] ? img_url($r['avatar']) : '';
    ?>
    <div class="bg-slate-800/60 p-5 rounded-2xl border border-slate-700/60 space-y-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <?php if ($avatar): ?>
                    <img src="<?= e($avatar) ?>" alt="" class="w-10 h-10 rounded-full object-cover border-2 border-brand-500">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-brand-600 border-2 border-brand-500 flex items-center justify-center font-bold text-white"><?= e(mb_strtoupper(mb_substr($r['name'], 0, 1))) ?></div>
                <?php endif; ?>
                <div>
                    <h4 class="font-bold text-sm text-white flex items-center">
                        <?= e($r['name']) ?>
                        <i class="fa-solid fa-circle-check text-emerald-400 text-xs ml-1.5" title="Verified Traveler"></i>
                    </h4>
                    <span class="text-xs text-brand-400"><?= e($r['trip']) ?></span>
                </div>
            </div>
            <span class="text-[10px] text-slate-400"><?= e(time_ago($r['created_at'])) ?></span>
        </div>
        <div class="flex text-amber-400 text-xs"><?= stars((int) $r['rating']) ?></div>
        <p class="text-xs text-slate-300 leading-relaxed italic">"<?= e($r['content']) ?>"</p>
    </div>
    <?php
}

/** Shared SELECT for tours joined with their category. */
function tours_query(string $where = '1=1', array $params = [], string $order = 't.sort_order, t.id', int $limit = 0): array
{
    $sql = 'SELECT t.*, c.name AS category_name, c.slug AS category_slug FROM ' . tbl('tours') . ' t LEFT JOIN ' . tbl('categories') . ' c ON c.id = t.category_id WHERE ' . $where . ' ORDER BY ' . $order;
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    return db_all($sql, $params);
}

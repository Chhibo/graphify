<?php
require __DIR__ . '/includes/auth.php';

$b = tbl('bookings');
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$stats = [
    ['Pending bookings', (int) db_value("SELECT COUNT(*) FROM $b WHERE status = 'pending'"), 'fa-hourglass-half', 'bg-amber-100 text-amber-600', 'bookings.php?status=pending'],
    ['Upcoming trips', (int) db_value("SELECT COUNT(*) FROM $b WHERE status IN ('pending','confirmed') AND travel_date >= ?", [$today]), 'fa-plane-departure', 'bg-brand-100 text-brand-600', 'bookings.php?when=upcoming'],
    ['Bookings this month', (int) db_value("SELECT COUNT(*) FROM $b WHERE created_at >= ?", [$monthStart . ' 00:00:00']), 'fa-calendar-plus', 'bg-sky-100 text-sky-600', 'bookings.php'],
    ['Expected revenue (upcoming)', money((float) db_value("SELECT COALESCE(SUM(total),0) FROM $b WHERE status IN ('pending','confirmed') AND travel_date >= ?", [$today])), 'fa-sack-dollar', 'bg-emerald-100 text-emerald-600', 'bookings.php?when=upcoming'],
];
$recent = db_all("SELECT * FROM $b ORDER BY created_at DESC LIMIT 8");
$arrivals = db_all("SELECT * FROM $b WHERE status IN ('pending','confirmed') AND travel_date BETWEEN ? AND ? ORDER BY travel_date, id LIMIT 8", [$today, date('Y-m-d', strtotime('+7 days'))]);
$topTours = db_all("SELECT tour_title, COUNT(*) AS n, SUM(guests) AS g FROM $b WHERE status <> 'cancelled' GROUP BY tour_title ORDER BY n DESC LIMIT 5");

// Bookings per day for the last 14 days (simple bar chart).
$chart = [];
for ($i = 13; $i >= 0; $i--) {
    $chart[date('Y-m-d', strtotime("-$i days"))] = 0;
}
foreach (db_all("SELECT DATE(created_at) AS d, COUNT(*) AS n FROM $b WHERE created_at >= ? GROUP BY DATE(created_at)", [date('Y-m-d', strtotime('-13 days')) . ' 00:00:00']) as $row) {
    if (isset($chart[$row['d']])) {
        $chart[$row['d']] = (int) $row['n'];
    }
}
$chartMax = max(1, max($chart));
$counts = [
    'trips' => (int) db_value('SELECT COUNT(*) FROM ' . tbl('tours') . " WHERE type = 'trip'"),
    'activities' => (int) db_value('SELECT COUNT(*) FROM ' . tbl('tours') . " WHERE type = 'activity'"),
    'posts' => (int) db_value('SELECT COUNT(*) FROM ' . tbl('posts')),
    'reviews' => (int) db_value('SELECT COUNT(*) FROM ' . tbl('reviews') . " WHERE status = 'pending'"),
];

admin_header('Dashboard', 'dashboard');
?>
<?php if (is_dir(APP_ROOT . '/install')): ?>
    <div class="mb-6 px-4 py-3 rounded-xl text-sm bg-amber-50 text-amber-800 border border-amber-200"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Security: please delete the <code>/install</code> folder from your server.</div>
<?php endif; ?>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <?php foreach ($stats as [$label, $value, $icon, $cls, $href]): ?>
        <a href="<?= e(url('admin/' . $href)) ?>" class="card p-5 flex items-center space-x-4 hover:shadow-md transition">
            <div class="w-12 h-12 rounded-xl <?= $cls ?> flex items-center justify-center text-xl"><i class="fa-solid <?= $icon ?>"></i></div>
            <div><p class="text-xs text-slate-500 font-semibold"><?= e($label) ?></p><p class="text-2xl font-extrabold text-slate-900"><?= e($value) ?></p></div>
        </a>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
    <div class="card p-5 xl:col-span-2">
        <h2 class="font-bold mb-4">New bookings – last 14 days</h2>
        <div class="flex items-end h-40 gap-1.5">
            <?php foreach ($chart as $day => $n): ?>
                <div class="flex-1 flex flex-col items-center justify-end h-full group">
                    <span class="text-[10px] font-bold text-slate-500 opacity-0 group-hover:opacity-100"><?= $n ?></span>
                    <div class="w-full rounded-t-md bg-brand-500 group-hover:bg-accent-500" style="height: <?= max(2, round($n / $chartMax * 100)) ?>%"></div>
                    <span class="text-[9px] text-slate-400 mt-1"><?= date('j', strtotime($day)) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card p-5">
        <h2 class="font-bold mb-4">Quick actions</h2>
        <div class="grid grid-cols-2 gap-2 text-xs">
            <a href="<?= e(url('admin/booking.php')) ?>" class="btn btn-accent justify-center"><i class="fa-solid fa-plus"></i> New booking</a>
            <a href="<?= e(url('admin/tour-edit.php?type=trip')) ?>" class="btn btn-primary justify-center"><i class="fa-solid fa-plus"></i> New trip</a>
            <a href="<?= e(url('admin/tour-edit.php?type=activity')) ?>" class="btn btn-light justify-center"><i class="fa-solid fa-plus"></i> New activity</a>
            <a href="<?= e(url('admin/post-edit.php')) ?>" class="btn btn-light justify-center"><i class="fa-solid fa-plus"></i> New post</a>
        </div>
        <ul class="mt-5 text-sm space-y-2 text-slate-600">
            <li class="flex justify-between"><span>Trips</span><b><?= $counts['trips'] ?></b></li>
            <li class="flex justify-between"><span>Activities</span><b><?= $counts['activities'] ?></b></li>
            <li class="flex justify-between"><span>Blog posts</span><b><?= $counts['posts'] ?></b></li>
            <li class="flex justify-between"><span>Reviews awaiting approval</span><a href="<?= e(url('admin/reviews.php?status=pending')) ?>" class="font-bold text-accent-500"><?= $counts['reviews'] ?></a></li>
        </ul>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="card xl:col-span-2 overflow-hidden">
        <div class="flex items-center justify-between p-5 pb-3"><h2 class="font-bold">Latest bookings</h2><a href="<?= e(url('admin/bookings.php')) ?>" class="text-xs font-bold text-brand-600">View all →</a></div>
        <div class="overflow-x-auto">
            <table class="tbl">
                <thead><tr><th>Ref</th><th>Guest</th><th>Experience</th><th>Date</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $r): ?>
                    <tr onclick="location.href='<?= e(url('admin/booking.php?id=' . $r['id'])) ?>'" class="cursor-pointer">
                        <td class="font-mono text-xs font-bold text-brand-700"><?= e($r['reference']) ?></td>
                        <td><?= e($r['customer_name']) ?></td>
                        <td class="max-w-[220px] truncate"><?= e($r['tour_title']) ?></td>
                        <td class="whitespace-nowrap"><?= e(format_date($r['travel_date'])) ?></td>
                        <td><?= e(booking_total($r)) ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recent): ?><tr><td colspan="6" class="text-center text-slate-400 py-8">No bookings yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="space-y-6">
        <div class="card p-5">
            <h2 class="font-bold mb-3">Arrivals – next 7 days</h2>
            <?php foreach ($arrivals as $a): ?>
                <a href="<?= e(url('admin/booking.php?id=' . $a['id'])) ?>" class="flex items-center justify-between py-2 border-b border-slate-100 text-sm hover:text-brand-600">
                    <span><b><?= e(format_date($a['travel_date'], 'M j')) ?></b> · <?= e($a['customer_name']) ?> <span class="text-slate-400">(<?= (int) $a['guests'] ?>)</span></span>
                    <?= status_badge($a['status']) ?>
                </a>
            <?php endforeach; ?>
            <?php if (!$arrivals): ?><p class="text-sm text-slate-400">No arrivals this week.</p><?php endif; ?>
        </div>
        <div class="card p-5">
            <h2 class="font-bold mb-3">Most booked</h2>
            <?php foreach ($topTours as $t): ?>
                <div class="flex justify-between py-1.5 text-sm"><span class="truncate mr-2"><?= e($t['tour_title']) ?></span><b class="whitespace-nowrap"><?= (int) $t['n'] ?> <span class="text-xs text-slate-400 font-normal">bookings</span></b></div>
            <?php endforeach; ?>
            <?php if (!$topTours): ?><p class="text-sm text-slate-400">No data yet.</p><?php endif; ?>
        </div>
    </div>
</div>
<?php admin_footer(); ?>
